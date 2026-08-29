<?php

namespace Punga\Component\Audioarchive\Administrator\Service\Analysis;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Administrator\Service\ExecutableLocatorService;
use Punga\Component\Audioarchive\Administrator\Service\ExternalProcessService;
use Punga\Component\Audioarchive\Administrator\Service\ManagedStorageService;

\defined('_JEXEC') or die;

/**
 * @brief Generate a compact averaged frequency profile with FFmpeg.
 *
 * The generator deliberately reuses FFmpeg's showspectrumpic implementation,
 * which is also used by the spectral-analysis generator. FFmpeg collapses the
 * time axis to one greyscale column, so no separate PHP FFT implementation is
 * required.
 */
final class FrequencyProfileGeneratorService implements AnalysisGeneratorInterface
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/**
	 * @brief Construct the frequency-profile generator.
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
		return 'frequency_profile';
	}

	/**
	 * @brief Generate an averaged frequency profile from the current original file.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return AnalysisGenerationResult Generated profile.
	 */
	public function generate(int $clipId, array $options = []): AnalysisGenerationResult
	{
		if ((int) $this->params->get('enable_frequency_profile_generation', 1) !== 1)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_GENERATION_DISABLED'));
		}

		$source = $this->loadSource($clipId);
		$sourcePath = $this->storage->resolveManagedPath('original', (string) $source->storage_key);

		if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_SOURCE_UNAVAILABLE'));
		}

		$settings = $this->resolveSettings($options);
		$locator = new ExecutableLocatorService($this->params);
		$ffmpeg = $locator->locate('ffmpeg');
		$root = $this->storage->ensureDirectory('analysis');
		$rawPath = tempnam($root, '.audioarchive-frequency-profile-raw-');
		$jsonPath = tempnam($root, '.audioarchive-frequency-profile-json-');

		if ($rawPath === false || $jsonPath === false)
		{
			if (is_string($rawPath))
			{
				@unlink($rawPath);
			}

			if (is_string($jsonPath))
			{
				@unlink($jsonPath);
			}

			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_TEMPORARY_FILE_FAILED'));
		}

		$timeout = max(1, min(3600, (int) $this->params->get('process_timeout', 120)));
		$filter = sprintf(
			'showspectrumpic=s=%dx%d:legend=disabled:color=intensity:saturation=0:scale=log:fscale=%s:start=%d:stop=%d:drange=%d,scale=1:%d:flags=area,format=gray',
			$settings['source_width'],
			$settings['bin_count'],
			$settings['frequency_scale'],
			$settings['start_frequency'],
			$settings['stop_frequency'],
			$settings['dynamic_range'],
			$settings['bin_count']
		);

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
				'-lavfi',
				$filter,
				'-frames:v',
				'1',
				'-an',
				'-f',
				'rawvideo',
				'pipe:1',
			];
			$process = (new ExternalProcessService())->runToFile($command, $rawPath, $timeout);

			if ((int) $process['exit_code'] !== 0)
			{
				$message = trim((string) $process['stderr']);
				throw new \RuntimeException(
					$message !== ''
						? Text::sprintf('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_FFMPEG_FAILED_DETAIL', strtok($message, "\r\n"))
						: Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_FFMPEG_FAILED')
				);
			}

			$profile = $this->readProfile($rawPath, $settings);
			$payload = [
				'version' => 1,
				'analysisType' => 'frequency_profile',
				'dataFormat' => 'json-frequency-profile-v1',
				'binCount' => count($profile['frequencies']),
				'frequencyScale' => $settings['frequency_scale'],
				'startFrequencyHz' => $settings['start_frequency'],
				'stopFrequencyHz' => $settings['stop_frequency'],
				'dynamicRangeDb' => $settings['dynamic_range'],
				'dominantFrequencyHz' => $profile['dominant_frequency'],
				'spectralCentroidHz' => $profile['spectral_centroid'],
				'durationMs' => max(0, (int) $source->duration_ms),
				'frequenciesHz' => $profile['frequencies'],
				'levelsDb' => $profile['levels'],
			];
			$json = json_encode(
				$payload,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);

			if (file_put_contents($jsonPath, $json, LOCK_EX) === false)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_WRITE_FAILED'));
			}

			@chmod($jsonPath, 0640);
			$stored = $this->storage->storeAnalysisFile(
				$jsonPath,
				(string) $source->uuid,
				'frequency_profile',
				'json'
			);
			$version = mb_substr((string) $ffmpeg['version'], 0, 64);

			return new AnalysisGenerationResult(
				'frequency_profile',
				(string) $stored['storage_key'],
				'json-frequency-profile-v1',
				[
					'detail' => $settings['detail'],
					'bin_count' => count($profile['frequencies']),
					'source_width' => $settings['source_width'],
					'frequency_scale' => $settings['frequency_scale'],
					'start_frequency_hz' => $settings['start_frequency'],
					'stop_frequency_hz' => $settings['stop_frequency'],
					'dynamic_range_db' => $settings['dynamic_range'],
					'dominant_frequency_hz' => $profile['dominant_frequency'],
					'spectral_centroid_hz' => $profile['spectral_centroid'],
				],
				'ffmpeg',
				$version,
				max(0, (int) filesize((string) $stored['absolute_path']))
			);
		}
		finally
		{
			@unlink($rawPath);
			@unlink($jsonPath);
		}
	}

	/**
	 * @brief Resolve and validate configurable profile-generation settings.
	 *
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return array{detail:string,bin_count:int,source_width:int,frequency_scale:string,start_frequency:int,stop_frequency:int,dynamic_range:int}
	 */
	private function resolveSettings(array $options): array
	{
		$detail = strtolower(trim((string) (
			$options['detail'] ?? $this->params->get('frequency_profile_detail', 'standard')
		)));
		$detailSettings = match ($detail)
		{
			'low' => [96, 512],
			'high' => [384, 1536],
			'very_high' => [512, 2048],
			default => [192, 1024],
		};

		if (!in_array($detail, ['low', 'standard', 'high', 'very_high'], true))
		{
			$detail = 'standard';
		}

		$frequencyScale = strtolower(trim((string) (
			$options['frequency_scale'] ?? $this->params->get('frequency_profile_frequency_scale', 'log')
		)));

		if (!in_array($frequencyScale, ['lin', 'log'], true))
		{
			$frequencyScale = 'log';
		}

		$startFrequency = max(1, min(19999, (int) (
			$options['start_frequency'] ?? $this->params->get('frequency_profile_start_frequency', 30)
		)));
		$stopFrequency = max(2, min(20000, (int) (
			$options['stop_frequency'] ?? $this->params->get('frequency_profile_stop_frequency', 8000)
		)));
		$dynamicRange = max(20, min(160, (int) (
			$options['dynamic_range'] ?? $this->params->get('frequency_profile_dynamic_range', 80)
		)));

		if ($stopFrequency <= $startFrequency)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_INVALID_FREQUENCY_RANGE'));
		}

		return [
			'detail' => $detail,
			'bin_count' => $detailSettings[0],
			'source_width' => $detailSettings[1],
			'frequency_scale' => $frequencyScale,
			'start_frequency' => $startFrequency,
			'stop_frequency' => $stopFrequency,
			'dynamic_range' => $dynamicRange,
		];
	}

	/**
	 * @brief Convert FFmpeg's time-collapsed greyscale spectrum into profile data.
	 *
	 * @param string $rawPath Raw one-column greyscale frame.
	 * @param array{detail:string,bin_count:int,source_width:int,frequency_scale:string,start_frequency:int,stop_frequency:int,dynamic_range:int} $settings Generation settings.
	 *
	 * @return array{dominant_frequency:float,spectral_centroid:float,frequencies:float[],levels:float[]}
	 */
	private function readProfile(string $rawPath, array $settings): array
	{
		$raw = file_get_contents($rawPath);

		if (!is_string($raw) || strlen($raw) !== $settings['bin_count'])
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_INVALID_DATA'));
		}

		$rows = array_values(unpack('C*', $raw) ?: []);
		$rows = array_reverse($rows);
		$rows = $this->smoothIntensities($rows);
		$maximumIntensity = max($rows);

		if ($maximumIntensity <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_EMPTY_AUDIO'));
		}

		$frequencies = [];
		$levels = [];
		$powerValues = [];
		$dominantIndex = 0;
		$minimumFrequency = (float) $settings['start_frequency'];
		$maximumFrequency = (float) $settings['stop_frequency'];
		$count = count($rows);

		foreach ($rows as $index => $intensity)
		{
			$position = $count > 1 ? $index / ($count - 1) : 0.0;
			$frequency = $settings['frequency_scale'] === 'log'
				? $minimumFrequency * (($maximumFrequency / $minimumFrequency) ** $position)
				: $minimumFrequency + (($maximumFrequency - $minimumFrequency) * $position);
			$level = (($intensity / $maximumIntensity) - 1.0) * $settings['dynamic_range'];
			$frequencies[] = round($frequency, 2);
			$levels[] = round(max(-$settings['dynamic_range'], $level), 2);
			$powerValues[] = 10.0 ** ($level / 10.0);

			if ($intensity > $rows[$dominantIndex])
			{
				$dominantIndex = $index;
			}
		}

		$totalPower = array_sum($powerValues);
		$weightedFrequency = 0.0;

		foreach ($powerValues as $index => $power)
		{
			$weightedFrequency += $frequencies[$index] * $power;
		}

		return [
			'dominant_frequency' => $frequencies[$dominantIndex],
			'spectral_centroid' => round($totalPower > 0.0 ? $weightedFrequency / $totalPower : 0.0, 2),
			'frequencies' => $frequencies,
			'levels' => $levels,
		];
	}

	/**
	 * @brief Smooth adjacent greyscale rows without altering the profile length.
	 *
	 * @param int[] $values Raw greyscale intensities.
	 *
	 * @return int[] Smoothed intensities.
	 */
	private function smoothIntensities(array $values): array
	{
		$count = count($values);
		$smoothed = $values;

		for ($index = 0; $index < $count; $index++)
		{
			$sum = 0;
			$weight = 0;

			for ($offset = -2; $offset <= 2; $offset++)
			{
				$sourceIndex = $index + $offset;

				if ($sourceIndex < 0 || $sourceIndex >= $count)
				{
					continue;
				}

				$currentWeight = 3 - abs($offset);
				$sum += $values[$sourceIndex] * $currentWeight;
				$weight += $currentWeight;
			}

			$smoothed[$index] = $weight > 0 ? (int) round($sum / $weight) : $values[$index];
		}

		return $smoothed;
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
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_SOURCE_UNAVAILABLE'));
		}

		return $row;
	}
}
