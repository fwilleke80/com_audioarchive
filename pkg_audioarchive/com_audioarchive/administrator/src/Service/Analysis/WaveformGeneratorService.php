<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Administrator\Service\ExecutableLocatorService;
use Willeke\Component\Audioarchive\Administrator\Service\ExternalProcessService;
use Willeke\Component\Audioarchive\Administrator\Service\ManagedStorageService;

\defined('_JEXEC') or die;

/**
 * @brief Generate compact JSON waveform peak data with FFmpeg.
 */
final class WaveformGeneratorService implements AnalysisGeneratorInterface
{
	/** @var int */
	private const SAMPLE_RATE = 8000;

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/**
	 * @brief Construct the waveform generator.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @param User $user Current administrator.
	 */
	public function __construct(DatabaseInterface $database, Registry $params, User $user)
	{
		$this->database = $database;
		$this->params = $params;
		$this->user = $user;
		$this->storage = new ManagedStorageService($params);
	}

	/**
	 * @brief Return the stable analysis type.
	 *
	 * @return string Analysis type.
	 */
	public function getAnalysisType(): string
	{
		return 'waveform';
	}

	/**
	 * @brief Generate waveform peaks from the current original file.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return AnalysisGenerationResult Generated waveform.
	 */
	public function generate(int $clipId, array $options = []): AnalysisGenerationResult
	{
		if ((int) $this->params->get('enable_waveform_generation', 1) !== 1)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_GENERATION_DISABLED'));
		}

		$source = $this->loadSource($clipId);
		$sourcePath = $this->storage->resolveManagedPath('original', (string) $source->storage_key);

		if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_SOURCE_UNAVAILABLE'));
		}

		$locator = new ExecutableLocatorService($this->params);
		$ffmpeg = $locator->locate('ffmpeg');
		$root = $this->storage->ensureDirectory('waveform');
		$pcmPath = tempnam($root, '.audioarchive-waveform-pcm-');
		$jsonPath = tempnam($root, '.audioarchive-waveform-json-');

		if ($pcmPath === false || $jsonPath === false)
		{
			if (is_string($pcmPath))
			{
				@unlink($pcmPath);
			}

			if (is_string($jsonPath))
			{
				@unlink($jsonPath);
			}

			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_TEMPORARY_FILE_FAILED'));
		}

		$pointCount = max(128, min(8192, (int) ($options['point_count'] ?? $this->params->get('waveform_point_count', 1024))));
		$timeout = max(1, min(3600, (int) $this->params->get('process_timeout', 120)));

		try
		{
			$command = [
				(string) $ffmpeg['path'],
				'-v',
				'error',
				'-nostdin',
				'-i',
				$sourcePath,
				'-map',
				'0:a:0',
				'-vn',
				'-sn',
				'-dn',
				'-ac',
				'1',
				'-ar',
				(string) self::SAMPLE_RATE,
				'-acodec',
				'pcm_s16le',
				'-f',
				's16le',
				'pipe:1',
			];
			$process = (new ExternalProcessService())->runToFile($command, $pcmPath, $timeout);

			if ((int) $process['exit_code'] !== 0)
			{
				$message = trim((string) $process['stderr']);
				throw new \RuntimeException(
					$message !== ''
						? Text::sprintf('COM_AUDIOARCHIVE_WAVEFORM_FFMPEG_FAILED_DETAIL', strtok($message, "\r\n"))
						: Text::_('COM_AUDIOARCHIVE_WAVEFORM_FFMPEG_FAILED')
				);
			}

			$peaks = $this->calculatePeaks($pcmPath, $pointCount);
			$actualPointCount = count($peaks);
			$payload = [
				'version' => 1,
				'analysisType' => 'waveform',
				'dataFormat' => 'json-peaks-v1',
				'sampleRate' => self::SAMPLE_RATE,
				'channelMode' => 'mono',
				'pointCount' => $actualPointCount,
				'durationMs' => max(0, (int) $source->duration_ms),
				'peaks' => $peaks,
			];
			$json = json_encode(
				$payload,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);

			if (file_put_contents($jsonPath, $json, LOCK_EX) === false)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_WRITE_FAILED'));
			}

			@chmod($jsonPath, 0640);
			$stored = $this->storage->storeAnalysisFile(
				$jsonPath,
				(string) $source->uuid,
				'waveform',
				'json'
			);
			$version = mb_substr((string) $ffmpeg['version'], 0, 64);

			return new AnalysisGenerationResult(
				'waveform',
				(string) $stored['storage_key'],
				'json-peaks-v1',
				[
					'point_count' => $actualPointCount,
					'requested_point_count' => $pointCount,
					'sample_rate' => self::SAMPLE_RATE,
					'channel_mode' => 'mono',
				],
				'ffmpeg',
				$version,
				max(0, (int) filesize((string) $stored['absolute_path']))
			);
		}
		finally
		{
			@unlink($pcmPath);
			@unlink($jsonPath);
		}
	}

	/**
	 * @brief Load the current original and owning clip UUID.
	 *
	 * @param int $clipId Clip identifier.
	 *
	 * @return object Source row.
	 */
	private function loadSource(int $clipId): object
	{
		$role = 'original';
		$available = 1;
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('a.id'),
				$this->database->quoteName('a.uuid'),
				$this->database->quoteName('a.duration_ms'),
				$this->database->quoteName('f.storage_key'),
			])
			->from($this->database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
			)
			->where($this->database->quoteName('a.id') . ' = :clipId')
			->where($this->database->quoteName('f.file_role') . ' = :role')
			->where($this->database->quoteName('f.is_available') . ' = :available')
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':role', $role, ParameterType::STRING)
			->bind(':available', $available, ParameterType::INTEGER);
		$row = $this->database->setQuery($query, 0, 1)->loadObject();

		if (!is_object($row))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_SOURCE_UNAVAILABLE'));
		}

		return $row;
	}

	/**
	 * @brief Calculate exact min/max peak pairs from raw signed 16-bit PCM.
	 *
	 * @param string $pcmPath Raw PCM file.
	 * @param int $requestedPointCount Requested number of points.
	 *
	 * @return array<int, array{0:int,1:int}> Peak pairs.
	 */
	private function calculatePeaks(string $pcmPath, int $requestedPointCount): array
	{
		$fileSize = is_file($pcmPath) ? (int) filesize($pcmPath) : 0;
		$totalSamples = intdiv($fileSize, 2);

		if ($totalSamples <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_EMPTY_AUDIO'));
		}

		$pointCount = min($requestedPointCount, $totalSamples);
		$handle = fopen($pcmPath, 'rb');

		if ($handle === false)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_READ_FAILED'));
		}

		$peaks = [];

		try
		{
			for ($point = 0; $point < $pointCount; $point++)
			{
				$start = intdiv($point * $totalSamples, $pointCount);
				$end = intdiv(($point + 1) * $totalSamples, $pointCount);
				$sampleCount = max(1, $end - $start);
				$remainingBytes = $sampleCount * 2;
				$minimum = 32767;
				$maximum = -32768;

				while ($remainingBytes > 0)
				{
					$bytesToRead = min(16384, $remainingBytes);
					$chunk = '';

					while (strlen($chunk) < $bytesToRead)
					{
						$part = fread($handle, $bytesToRead - strlen($chunk));

						if ($part === false || $part === '')
						{
							throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_READ_FAILED'));
						}

						$chunk .= $part;
					}

					$values = unpack('v*', $chunk);

					if (!is_array($values))
					{
						throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_WAVEFORM_READ_FAILED'));
					}

					foreach ($values as $value)
					{
						$sample = $value >= 32768 ? $value - 65536 : $value;
						$minimum = min($minimum, $sample);
						$maximum = max($maximum, $sample);
					}

					$remainingBytes -= $bytesToRead;
				}

				$peaks[] = [$minimum, $maximum];
			}
		}
		finally
		{
			fclose($handle);
		}

		return $peaks;
	}
}
