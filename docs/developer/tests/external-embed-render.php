<?php
/** @brief Render production embed/share/player templates for isolated browser regression checks. */
namespace Joomla\Registry
{
	class Registry
	{
		/** @brief Return renderer defaults. */
		public function get(string $key, mixed $default = null): mixed
		{
			return $default;
		}
	}
}
namespace Joomla\CMS\Language
{
	class Text
	{
		/** @brief Load actual English interface strings. */
		public static function _(string $key): string
		{
			return $GLOBALS['strings'][$key] ?? ($key === 'JCLOSE' ? 'Close' : $key);
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		/** @brief Return the standalone render application. */
		public static function getApplication(): object
		{
			return $GLOBALS['app'];
		}
	}
}
namespace Joomla\CMS\Component
{
	class ComponentHelper
	{
		/** @brief Provide player default settings. */
		public static function getParams(string $name): \Joomla\Registry\Registry
		{
			return new \Joomla\Registry\Registry();
		}
	}
}
namespace Punga\Component\Audioarchive\Administrator\Service
{
	class PlaybackNormalizationService
	{
		/** @brief Avoid database access for the deterministic audio fixture. */
		public static function forClip(int $id): float
		{
			return 1;
		}
	}
}
namespace Punga\Component\Audioarchive\Site\Service
{
	class EmbedService
	{
		/** @brief The browser fixture contains one eligible public clip. */
		public static function isAllowed(int $id): bool
		{
			return true;
		}
		/** @brief Build mocked endpoint URLs using the real parameter format. */
		public static function url(string $task, int $id): string
		{
			return 'https://audio.test/index.php?option=com_audioarchive&task=embed.' . $task . '&format=raw&id=' . $id;
		}
		/** @brief Server signature correctness is covered by external-embeds.php. */
		public static function token(int $id, string $purpose): string
		{
			return 'test-' . $purpose;
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	define('JPATH_SITE', '/fixture');
	$root = __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/';
	$strings = parse_ini_file($root . 'site/language/en-GB/com_audioarchive.ini', false, INI_SCANNER_RAW);
	$app = new class
	{
		/** @brief Provide the language hooks consumed by the real layout. */
		public function getLanguage(): object
		{
			return new class
			{
				/** @brief Translations are loaded from the production INI above. */
				public function load(...$args): void {}
				/** @brief Set the test document language. */
				public function getTag(): string { return 'en-GB'; }
				/** @brief Set the test document direction. */
				public function isRtl(): bool { return false; }
			};
		}
	};
	$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	$clip = (object) ['title' => 'Public clip & "quoted" title'];
	$assetRoot = 'https://audio.test/media/com_audioarchive/';
	$detailUrl = 'https://audio.test/clips/42';
	$downloadUrl = '';
	$eventUrl = \Punga\Component\Audioarchive\Site\Service\EmbedService::url('countPlay', 42);
	$eventToken = 'test-play';
	$documents = [];
	foreach (['minimal', 'compact', 'default', 'featured'] as $mode)
	{
		$displayData = ['audioId' => 'embed-audio', 'clipId' => 42, 'presentation' => $mode, 'title' => $clip->title,
			'streamUrl' => 'https://audio.test/audio.wav', 'durationMs' => 1000, 'mime' => 'audio/wav',
			'waveformUrl' => $mode === 'featured' ? 'https://audio.test/waveform.json' : ''];
		ob_start();
		require $root . 'site/layouts/player/unified.php';
		$html = ob_get_clean();
		ob_start();
		require $root . 'site/tmpl/embed/document.php';
		$documents[$mode] = ob_get_clean();
	}
	$displayData = ['clipId' => 42, 'url' => $detailUrl, 'title' => $clip->title];
	ob_start();
	require $root . 'site/layouts/interaction/share.php';
	$documents['share'] = ob_get_clean();
	echo json_encode($documents);
}
