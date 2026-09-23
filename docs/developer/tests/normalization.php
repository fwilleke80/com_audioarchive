<?php
/** @brief Test normalization policy, artifact freshness and the waveform peak parser. */
namespace Joomla\Database
{
	interface DatabaseInterface
	{
	}
}
namespace Joomla\CMS\Component
{
	class ComponentHelper
	{
		public static object $params;
		/** @brief Provide test options. */
		public static function getParams(string $name): object
		{
			return self::$params;
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		public static object $database;
		/** @brief Return the test database through a minimal container. */
		public static function getContainer(): object
		{
			return new class
			{
				/** @brief Resolve the database used by the production helper. */
				public function get(string $name): object
				{
					return Factory::$database;
				}
			};
		}
	}
}
namespace Punga\Component\Audioarchive\Administrator\Service
{
	class ManagedStorageService
	{
		public static string $directory;
		/** @brief Accept production options. */
		public function __construct(object $params)
		{
		}
		/** @brief Resolve a controlled test artifact. */
		public function resolveManagedPath(string $role, string $key): string
		{
			return self::$directory . '/' . $key;
		}
	}
}
namespace
{
	define('_JEXEC',1);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/PlaybackNormalizationService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/Analysis/AnalysisGeneratorInterface.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/Analysis/WaveformGeneratorService.php';
	use Punga\Component\Audioarchive\Administrator\Service\PlaybackNormalizationService as N;
	use Punga\Component\Audioarchive\Administrator\Service\Analysis\WaveformGeneratorService as W;
	$checks = 0;
	/** @brief Assert finite values and exact fallback behavior. */
	function check(bool $condition, string $message): void
	{
		global $checks;
		$checks++;
		if (!$condition)
		{
			throw new RuntimeException($message);
		}
	}
	foreach ([false,true] as $global)
	{
		foreach (['inherit','enabled','disabled'] as $override)
		{
			$enabled = $override === 'enabled' || ($global && $override === 'inherit');
			check(abs(N::gain($global,$override,-8,-1) - ($enabled ? pow(10,7/20) : 1)) < 0.000001, 'tri-state policy');
			check(N::gain($global,$override,null,-1) === 1.0, 'missing peak always falls back');
		}
	}
	foreach ([-8,-1,0,1] as $peak)
	{
		check(abs(N::gain(true,'inherit',$peak,-1) - pow(10,(-1-$peak)/20)) < 0.000001, 'positive and negative gain');
	}
	foreach ([null,INF,NAN,-INF,'bad',-150] as $peak)
	{
		check(N::gain(true,'inherit',$peak) === 1.0, 'invalid and silent input');
	}
	check(N::gain(true,'inherit',-80) === 1.0, 'near-zero amplification safety');
	check(N::gain(true,'inherit',-8,1) === 1.0, 'positive target rejected');
	check(W::extractGlobalPeak('Overall Peak level dB: -6.020600') === -6.0206, 'FFmpeg peak parser');
	check(W::extractGlobalPeak('Peak level dB: -inf') === null, 'silence parser');
	check(W::extractGlobalPeak('decoder failure') === null, 'malformed parser');
	$options = new class
	{
		/** @brief Return enabled normalization options. */
		public function get(string $key, mixed $default = null): mixed
		{
			return ['normalize_playback'=>1,'normalization_target_dbfs'=>-1][$key] ?? $default;
		}
	};
	Joomla\CMS\Component\ComponentHelper::$params = $options;
	$db = new class
	{
		public object $row;
		/** @brief Mirror a loaded analysis status record. */
		public function setQuery(string $sql): self
		{
			return $this;
		}
		/** @brief Preserve the production SQL identifiers. */
		public function quoteName(string $name): string
		{
			return $name;
		}
		/** @brief Supply controlled artifact freshness and policy. */
		public function loadObject(): object
		{
			return $this->row;
		}
	};
	Joomla\CMS\Factory::$database = $db;
	$directory = sys_get_temp_dir() . '/audioarchive-normalization-' . bin2hex(random_bytes(6));
	mkdir($directory);
	Punga\Component\Audioarchive\Administrator\Service\ManagedStorageService::$directory = $directory;
	$db->row = (object) ['normalization_mode'=>'inherit','waveform_status'=>'available','storage_key'=>'wave.json'];
	try
	{
		file_put_contents($directory.'/wave.json','{"peaks":[[0,1]],"global_peak_dbfs":-8}');
		check(abs(N::forClip(1)-pow(10,7/20))<0.000001,'current artifact applies gain');
		$db->row->waveform_status = 'stale';
		check(N::forClip(1) === 1.0,'replacement invalidates old peak');
		$db->row->waveform_status = 'available';
		file_put_contents($directory.'/wave.json','{"peaks":[[0,1]]}');
		check(N::forClip(1) === 1.0,'legacy artifact remains playable');
		file_put_contents($directory.'/wave.json','broken');
		check(N::forClip(1) === 1.0,'malformed artifact falls back');
		file_put_contents($directory.'/wave.json','{"global_peak_dbfs":0}');
		check(abs(N::forClip(1)-pow(10,-1/20))<0.000001,'regeneration replaces peak');
	}
	finally
	{
		unlink($directory.'/wave.json');
		rmdir($directory);
	}
	echo $checks . " normalization assertions passed.\n";
}
