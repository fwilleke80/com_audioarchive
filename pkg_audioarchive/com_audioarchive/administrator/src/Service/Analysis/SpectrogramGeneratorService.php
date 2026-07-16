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
 * @brief Generate a protected time-frequency spectrogram image with FFmpeg.
 */
final class SpectrogramGeneratorService implements AnalysisGeneratorInterface
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
	 * @brief Construct the spectrogram generator.
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
		return 'spectrogram';
	}

	/**
	 * @brief Generate a spectrogram from the current original file.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return AnalysisGenerationResult Generated spectrogram.
	 */
	public function generate(int $clipId, array $options = []): AnalysisGenerationResult
	{
		if ((int) $this->params->get('enable_spectrogram_generation', 1) !== 1)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_GENERATION_DISABLED'));
		}

		$source = $this->loadSource($clipId);
		$sourcePath = $this->storage->resolveManagedPath('original', (string) $source->storage_key);

		if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_SOURCE_UNAVAILABLE'));
		}

		[$width, $height, $detail] = $this->resolveDimensions($options);
		$locator = new ExecutableLocatorService($this->params);
		$ffmpeg = $locator->locate('ffmpeg');
		$root = $this->storage->ensureDirectory('analysis');
		$pngPath = tempnam($root, '.audioarchive-spectrogram-');

		if ($pngPath === false)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_TEMPORARY_FILE_FAILED'));
		}

		$timeout = max(1, min(3600, (int) $this->params->get('process_timeout', 120)));
		$filter = sprintf(
			'showspectrumpic=s=%dx%d:legend=disabled:color=intensity:scale=log:fscale=log',
			$width,
			$height
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
				'-c:v',
				'png',
				'-f',
				'image2',
				'pipe:1',
			];
			$process = (new ExternalProcessService())->runToFile($command, $pngPath, $timeout);

			if ((int) $process['exit_code'] !== 0)
			{
				$message = trim((string) $process['stderr']);
				throw new \RuntimeException(
					$message !== ''
						? Text::sprintf('COM_AUDIOARCHIVE_SPECTROGRAM_FFMPEG_FAILED_DETAIL', strtok($message, "\r\n"))
						: Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_FFMPEG_FAILED')
				);
			}

			$imageInfo = @getimagesize($pngPath);

			if (
				!is_array($imageInfo)
				|| (int) ($imageInfo[0] ?? 0) !== $width
				|| (int) ($imageInfo[1] ?? 0) !== $height
				|| (int) ($imageInfo[2] ?? 0) !== IMAGETYPE_PNG
			)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_INVALID_IMAGE'));
			}

			@chmod($pngPath, 0640);
			$stored = $this->storage->storeAnalysisFile(
				$pngPath,
				(string) $source->uuid,
				'spectrogram',
				'png'
			);
			$version = mb_substr((string) $ffmpeg['version'], 0, 64);

			return new AnalysisGenerationResult(
				'spectrogram',
				(string) $stored['storage_key'],
				'png-spectrogram-v1',
				[
					'detail' => $detail,
					'width' => $width,
					'height' => $height,
					'colour_mode' => 'intensity',
					'amplitude_scale' => 'log',
					'frequency_scale' => 'log',
				],
				'ffmpeg',
				$version,
				max(0, (int) filesize((string) $stored['absolute_path']))
			);
		}
		finally
		{
			@unlink($pngPath);
		}
	}

	/**
	 * @brief Resolve the configured spectrogram dimensions.
	 *
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return array{0:int,1:int,2:string} Width, height, and stable detail name.
	 */
	private function resolveDimensions(array $options): array
	{
		$detail = strtolower(trim((string) (
			$options['detail'] ?? $this->params->get('spectrogram_detail', 'standard')
		)));
		$dimensions = match ($detail)
		{
			'low' => [512, 128],
			'high' => [1536, 256],
			'very_high' => [2048, 320],
			default => [1024, 192],
		};

		if (!in_array($detail, ['low', 'standard', 'high', 'very_high'], true))
		{
			$detail = 'standard';
		}

		return [$dimensions[0], $dimensions[1], $detail];
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
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_SOURCE_UNAVAILABLE'));
		}

		return $row;
	}
}
