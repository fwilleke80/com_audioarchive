<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * @brief Persist generic analysis records and current waveform compatibility data.
 */
final class AnalysisRepositoryService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/**
	 * @brief Construct the repository.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(DatabaseInterface $database)
	{
		$this->database = $database;
	}

	/**
	 * @brief Load one analysis record.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 *
	 * @return object|null Analysis record.
	 */
	public function get(int $clipId, string $analysisType): ?object
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_analyses'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('analysis_type') . ' = :analysisType')
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':analysisType', $analysisType, ParameterType::STRING);
		$result = $this->database->setQuery($query, 0, 1)->loadObject();

		return is_object($result) ? $result : null;
	}

	/**
	 * @brief Mark one analysis as pending.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 * @param array<string, mixed> $parameters Planned generation parameters.
	 *
	 * @return void
	 */
	public function markPending(int $clipId, string $analysisType, array $parameters = []): void
	{
		$this->upsertState($clipId, $analysisType, 'pending', '', $parameters);
		$this->updateClipStatus($clipId, $analysisType, 'pending');
	}

	/**
	 * @brief Store a successfully generated analysis.
	 *
	 * @param int $clipId Clip identifier.
	 * @param AnalysisGenerationResult $result Generated analysis.
	 *
	 * @return string Previous managed storage key, if any.
	 */
	public function saveAvailable(int $clipId, AnalysisGenerationResult $result): string
	{
		$previous = $this->get($clipId, $result->analysisType);
		$previousKey = trim((string) ($previous->storage_key ?? ''));
		$now = Factory::getDate()->toSql();
		$parameters = json_encode(
			$result->parameters,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
		$available = 1;
		$status = 'available';
		$error = '';
		$storageKey = $result->storageKey;
		$dataFormat = $result->dataFormat;
		$generator = $result->generator;
		$generatorVersion = $result->generatorVersion;
		$fileSize = $result->fileSize;
		$this->database->transactionStart();

		try
		{
			if ($previous === null)
			{
				$row = (object) [
					'clip_id' => $clipId,
					'analysis_type' => $result->analysisType,
					'storage_key' => $result->storageKey,
					'data_format' => $result->dataFormat,
					'status' => $status,
					'parameters' => $parameters,
					'generated_at' => $now,
					'generator' => $result->generator,
					'generator_version' => $result->generatorVersion,
					'file_size' => $result->fileSize,
					'is_available' => $available,
					'processing_error' => $error,
				];
				$this->database->insertObject('#__audioarchive_analyses', $row, 'id');
			}
			else
			{
				$previousId = (int) $previous->id;
				$query = $this->database->getQuery(true)
					->update($this->database->quoteName('#__audioarchive_analyses'))
					->set($this->database->quoteName('storage_key') . ' = :storageKey')
					->set($this->database->quoteName('data_format') . ' = :dataFormat')
					->set($this->database->quoteName('status') . ' = :status')
					->set($this->database->quoteName('parameters') . ' = :parameters')
					->set($this->database->quoteName('generated_at') . ' = :generatedAt')
					->set($this->database->quoteName('generator') . ' = :generator')
					->set($this->database->quoteName('generator_version') . ' = :generatorVersion')
					->set($this->database->quoteName('file_size') . ' = :fileSize')
					->set($this->database->quoteName('is_available') . ' = :available')
					->set($this->database->quoteName('processing_error') . ' = :error')
					->where($this->database->quoteName('id') . ' = :id')
					->bind(':storageKey', $storageKey, ParameterType::STRING)
					->bind(':dataFormat', $dataFormat, ParameterType::STRING)
					->bind(':status', $status, ParameterType::STRING)
					->bind(':parameters', $parameters, ParameterType::STRING)
					->bind(':generatedAt', $now, ParameterType::STRING)
					->bind(':generator', $generator, ParameterType::STRING)
					->bind(':generatorVersion', $generatorVersion, ParameterType::STRING)
					->bind(':fileSize', $fileSize, ParameterType::INTEGER)
					->bind(':available', $available, ParameterType::INTEGER)
					->bind(':error', $error, ParameterType::STRING)
					->bind(':id', $previousId, ParameterType::INTEGER);
				$this->database->setQuery($query)->execute();
			}

			if ($result->analysisType === 'waveform')
			{
				$this->saveLegacyWaveform($clipId, $result, $now);
			}

			$this->updateClipStatus($clipId, $result->analysisType, 'available');
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}

		return $previousKey;
	}

	/**
	 * @brief Mark generation as failed while preserving any previous storage key.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 * @param string $error Sanitised error message.
	 * @param array<string, mixed> $parameters Attempted parameters.
	 *
	 * @return void
	 */
	public function markFailed(int $clipId, string $analysisType, string $error, array $parameters = []): void
	{
		$error = mb_substr(trim($error), 0, 4000);
		$this->database->transactionStart();

		try
		{
			$this->upsertState($clipId, $analysisType, 'failed', $error, $parameters);

			if ($analysisType === 'waveform')
			{
				$query = $this->database->getQuery(true)
					->update($this->database->quoteName('#__audioarchive_waveforms'))
					->set($this->database->quoteName('is_available') . ' = 0')
					->set($this->database->quoteName('processing_error') . ' = :error')
					->where($this->database->quoteName('clip_id') . ' = :clipId')
					->bind(':error', $error, ParameterType::STRING)
					->bind(':clipId', $clipId, ParameterType::INTEGER);
				$this->database->setQuery($query)->execute();
			}

			$this->updateClipStatus($clipId, $analysisType, 'failed');
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}
	}


	/**
	 * @brief Mark an existing analysis stale.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 *
	 * @return void
	 */
	public function markStale(int $clipId, string $analysisType): void
	{
		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_analyses'))
			->set($this->database->quoteName('status') . ' = ' . $this->database->quote('stale'))
			->set($this->database->quoteName('is_available') . ' = 0')
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('analysis_type') . ' = :analysisType')
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':analysisType', $analysisType, ParameterType::STRING);
		$this->database->setQuery($query)->execute();
		$this->updateClipStatus($clipId, $analysisType, 'stale');
	}

	/**
	 * @brief Insert or update a non-available analysis state.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 * @param string $status Analysis state.
	 * @param string $error Processing error.
	 * @param array<string, mixed> $parameters Generation parameters.
	 *
	 * @return void
	 */
	private function upsertState(
		int $clipId,
		string $analysisType,
		string $status,
		string $error,
		array $parameters
	): void
	{
		$current = $this->get($clipId, $analysisType);
		$parametersJson = json_encode(
			$parameters,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);

		if ($current === null)
		{
			$row = (object) [
				'clip_id' => $clipId,
				'analysis_type' => $analysisType,
				'storage_key' => '',
				'data_format' => '',
				'status' => $status,
				'parameters' => $parametersJson,
				'generated_at' => null,
				'generator' => '',
				'generator_version' => '',
				'file_size' => 0,
				'is_available' => 0,
				'processing_error' => $error,
			];
			$this->database->insertObject('#__audioarchive_analyses', $row, 'id');
			return;
		}

		$currentId = (int) $current->id;
		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_analyses'))
			->set($this->database->quoteName('status') . ' = :status')
			->set($this->database->quoteName('parameters') . ' = :parameters')
			->set($this->database->quoteName('is_available') . ' = 0')
			->set($this->database->quoteName('processing_error') . ' = :error')
			->where($this->database->quoteName('id') . ' = :id')
			->bind(':status', $status, ParameterType::STRING)
			->bind(':parameters', $parametersJson, ParameterType::STRING)
			->bind(':error', $error, ParameterType::STRING)
			->bind(':id', $currentId, ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Maintain the legacy waveform projection used by existing maintenance code.
	 *
	 * @param int $clipId Clip identifier.
	 * @param AnalysisGenerationResult $result Generated waveform.
	 * @param string $generatedAt Generation date.
	 *
	 * @return void
	 */
	private function saveLegacyWaveform(
		int $clipId,
		AnalysisGenerationResult $result,
		string $generatedAt
	): void
	{
		$query = $this->database->getQuery(true)
			->select('id')
			->from($this->database->quoteName('#__audioarchive_waveforms'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$id = (int) $this->database->setQuery($query, 0, 1)->loadResult();
		$pointCount = max(0, (int) ($result->parameters['point_count'] ?? 0));
		$channelMode = (string) ($result->parameters['channel_mode'] ?? 'mono');
		$storageKey = $result->storageKey;
		$dataFormat = $result->dataFormat;
		$generator = $result->generator;
		$generatorVersion = $result->generatorVersion;

		if ($id <= 0)
		{
			$row = (object) [
				'clip_id' => $clipId,
				'storage_key' => $result->storageKey,
				'data_format' => $result->dataFormat,
				'point_count' => $pointCount,
				'channel_mode' => $channelMode,
				'generated_at' => $generatedAt,
				'generator' => $result->generator,
				'generator_version' => $result->generatorVersion,
				'is_available' => 1,
				'processing_error' => '',
			];
			$this->database->insertObject('#__audioarchive_waveforms', $row, 'id');
			return;
		}

		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_waveforms'))
			->set($this->database->quoteName('storage_key') . ' = :storageKey')
			->set($this->database->quoteName('data_format') . ' = :dataFormat')
			->set($this->database->quoteName('point_count') . ' = :pointCount')
			->set($this->database->quoteName('channel_mode') . ' = :channelMode')
			->set($this->database->quoteName('generated_at') . ' = :generatedAt')
			->set($this->database->quoteName('generator') . ' = :generator')
			->set($this->database->quoteName('generator_version') . ' = :generatorVersion')
			->set($this->database->quoteName('is_available') . ' = 1')
			->set($this->database->quoteName('processing_error') . ' = ' . $this->database->quote(''))
			->where($this->database->quoteName('id') . ' = :id')
			->bind(':storageKey', $storageKey, ParameterType::STRING)
			->bind(':dataFormat', $dataFormat, ParameterType::STRING)
			->bind(':pointCount', $pointCount, ParameterType::INTEGER)
			->bind(':channelMode', $channelMode, ParameterType::STRING)
			->bind(':generatedAt', $generatedAt, ParameterType::STRING)
			->bind(':generator', $generator, ParameterType::STRING)
			->bind(':generatorVersion', $generatorVersion, ParameterType::STRING)
			->bind(':id', $id, ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Update the current denormalised clip status for supported analyses.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $analysisType Analysis type.
	 * @param string $status Status value.
	 *
	 * @return void
	 */
	private function updateClipStatus(int $clipId, string $analysisType, string $status): void
	{
		$statusField = match ($analysisType)
		{
			'waveform' => 'waveform_status',
			'spectrogram' => 'spectrogram_status',
			default => '',
		};

		if ($statusField === '')
		{
			return;
		}

		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_clips'))
			->set($this->database->quoteName($statusField) . ' = :status')
			->where($this->database->quoteName('id') . ' = :clipId')
			->bind(':status', $status, ParameterType::STRING)
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
	}
}
