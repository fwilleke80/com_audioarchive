<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Queue and process generic audio-analysis jobs incrementally.
 */
final class AnalysisJobService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/** @var AnalysisRepositoryService */
	private AnalysisRepositoryService $repository;

	/**
	 * @brief Construct the analysis job service.
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
		$this->repository = new AnalysisRepositoryService($database);
	}

	/**
	 * @brief Queue a waveform for one clip unless an active job already exists.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 * @param int $priority Job priority.
	 *
	 * @return bool True when a new job was queued.
	 */
	public function queueWaveform(int $clipId, array $options = [], int $priority = 0): bool
	{
		return $this->queueAnalysis('waveform', $clipId, $options, $priority);
	}

	/**
	 * @brief Queue a spectrogram for one clip unless an active job already exists.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 * @param int $priority Job priority.
	 *
	 * @return bool True when a new job was queued.
	 */
	public function queueSpectrogram(int $clipId, array $options = [], int $priority = 0): bool
	{
		return $this->queueAnalysis('spectrogram', $clipId, $options, $priority);
	}

	/**
	 * @brief Queue one registered analysis type for a clip.
	 *
	 * @param string $analysisType Stable analysis type.
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 * @param int $priority Job priority.
	 *
	 * @return bool True when a new job was queued.
	 */
	public function queueAnalysis(
		string $analysisType,
		int $clipId,
		array $options = [],
		int $priority = 0
	): bool
	{
		$analysisType = strtolower(trim($analysisType));

		if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $analysisType))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_INVALID_TYPE'));
		}

		return $this->queue($analysisType, $clipId, $options, $priority);
	}

	/**
	 * @brief Queue analyses for all clips whose denormalised status matches.
	 *
	 * @param string $analysisType Analysis type.
	 * @param string[] $statuses Clip statuses.
	 *
	 * @return int Number of newly queued jobs.
	 */
	public function queueByStatuses(string $analysisType, array $statuses): int
	{
		$analysisType = strtolower(trim($analysisType));

		if (!in_array($analysisType, ['waveform', 'spectrogram'], true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_UNSUPPORTED_BULK_TYPE'));
		}

		$statuses = array_values(array_unique(array_filter(array_map('strval', $statuses))));

		if ($statuses === [])
		{
			return 0;
		}

		$statusField = $analysisType === 'waveform' ? 'waveform_status' : 'spectrogram_status';
		$query = $this->database->getQuery(true)
			->select($this->database->quoteName('a.id'))
			->from($this->database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
				. ' AND ' . $this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original')
				. ' AND ' . $this->database->quoteName('f.is_available') . ' = 1'
			)
			->whereIn($this->database->quoteName('a.' . $statusField), $statuses, ParameterType::STRING)
			->order($this->database->quoteName('a.id') . ' ASC');
		$clipIds = array_map('intval', $this->database->setQuery($query)->loadColumn() ?: []);
		$queued = 0;

		foreach ($clipIds as $clipId)
		{
			if ($this->queueAnalysis($analysisType, $clipId))
			{
				$queued++;
			}
		}

		return $queued;
	}


	/**
	 * @brief Queue one analysis type for every clip with an available original file.
	 *
	 * Active pending or running jobs are retained and are not duplicated.
	 *
	 * @param string $analysisType Analysis type.
	 *
	 * @return int Number of newly queued jobs.
	 */
	public function queueAll(string $analysisType): int
	{
		$analysisType = strtolower(trim($analysisType));

		if (!in_array($analysisType, ['waveform', 'spectrogram'], true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_UNSUPPORTED_BULK_TYPE'));
		}

		$query = $this->database->getQuery(true)
			->select('DISTINCT ' . $this->database->quoteName('a.id'))
			->from($this->database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_files', 'f')
					. ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
					. ' AND ' . $this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original')
					. ' AND ' . $this->database->quoteName('f.is_available') . ' = 1'
			)
			->order($this->database->quoteName('a.id') . ' ASC');
		$clipIds = array_map('intval', $this->database->setQuery($query)->loadColumn() ?: []);
		$queued = 0;

		foreach ($clipIds as $clipId)
		{
			if ($this->queueAnalysis($analysisType, $clipId))
			{
				$queued++;
			}
		}

		return $queued;
	}

	/**
	 * @brief Return waveform status and queue counts for maintenance UI.
	 *
	 * @return array<string, int> Summary counts.
	 */
	public function getWaveformSummary(): array
	{
		return $this->getAnalysisSummary('waveform', 'waveform_status');
	}

	/**
	 * @brief Return spectrogram status and queue counts for maintenance UI.
	 *
	 * @return array<string, int> Summary counts.
	 */
	public function getSpectrogramSummary(): array
	{
		return $this->getAnalysisSummary('spectrogram', 'spectrogram_status');
	}

	/**
	 * @brief Return status and queue counts for one denormalised analysis type.
	 *
	 * @param string $analysisType Stable analysis type.
	 * @param string $statusField Clip status column.
	 *
	 * @return array<string, int> Summary counts.
	 */
	private function getAnalysisSummary(string $analysisType, string $statusField): array
	{
		$statusColumn = $this->database->quoteName($statusField);
		$query = $this->database->getQuery(true)
			->select([
				"SUM(CASE WHEN {$statusColumn} = 'available' THEN 1 ELSE 0 END) AS available_count",
				"SUM(CASE WHEN {$statusColumn} = 'missing' THEN 1 ELSE 0 END) AS missing_count",
				"SUM(CASE WHEN {$statusColumn} = 'pending' THEN 1 ELSE 0 END) AS pending_count",
				"SUM(CASE WHEN {$statusColumn} = 'failed' THEN 1 ELSE 0 END) AS failed_count",
				"SUM(CASE WHEN {$statusColumn} = 'stale' THEN 1 ELSE 0 END) AS stale_count",
			])
			->from($this->database->quoteName('#__audioarchive_clips'));
		$row = (array) ($this->database->setQuery($query)->loadAssoc() ?: []);
		$jobType = $this->jobType($analysisType);
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_jobs'))
			->where($this->database->quoteName('job_type') . ' = :jobType')
			->where($this->database->quoteName('state') . ' = ' . $this->database->quote('pending'))
			->bind(':jobType', $jobType, ParameterType::STRING);
		$queued = (int) $this->database->setQuery($query)->loadResult();

		return [
			'available' => (int) ($row['available_count'] ?? 0),
			'missing' => (int) ($row['missing_count'] ?? 0),
			'pending' => (int) ($row['pending_count'] ?? 0),
			'failed' => (int) ($row['failed_count'] ?? 0),
			'stale' => (int) ($row['stale_count'] ?? 0),
			'queued' => $queued,
		];
	}

	/**
	 * @brief Process the next pending analysis job.
	 *
	 * @return array<string, mixed> Processing result.
	 */
	public function processNext(): array
	{
		$this->releaseExpiredJobs();
		$job = $this->claimNextJob();

		if ($job === null)
		{
			return [
				'processed' => false,
				'success' => true,
				'remaining' => 0,
			];
		}

		$payload = json_decode((string) $job->payload, true);
		$payload = is_array($payload) ? $payload : [];
		$analysisType = $this->analysisTypeFromJob($job, $payload);
		$options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
		$success = false;
		$message = '';

		try
		{
			$result = (new AnalysisManagerService($this->database, $this->params, $this->user))
				->generate($analysisType, (int) $job->clip_id, $options);
			$success = true;
			$message = match ($analysisType)
			{
				'waveform' => Text::sprintf(
					'COM_AUDIOARCHIVE_WAVEFORM_JOB_SUCCESS',
					(int) ($result->parameters['point_count'] ?? 0)
				),
				'spectrogram' => Text::sprintf(
					'COM_AUDIOARCHIVE_SPECTROGRAM_JOB_SUCCESS',
					(int) ($result->parameters['width'] ?? 0),
					(int) ($result->parameters['height'] ?? 0)
				),
				default => Text::sprintf('COM_AUDIOARCHIVE_ANALYSIS_JOB_SUCCESS', $analysisType),
			};
			$this->finishJob((int) $job->id, 'completed', '');
		}
		catch (\Throwable $exception)
		{
			$message = $exception->getMessage();

			if (preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $analysisType))
			{
				try
				{
					$this->repository->markFailed((int) $job->clip_id, $analysisType, $message, $options);
				}
				catch (\Throwable)
				{
					// Preserve the original generation failure as the job result.
				}
			}

			$this->finishJob((int) $job->id, 'failed', $message);
		}

		return [
			'processed' => true,
			'success' => $success,
			'job_id' => (int) $job->id,
			'clip_id' => (int) $job->clip_id,
			'clip_title' => (string) ($job->clip_title ?? ''),
			'message' => $message,
			'remaining' => $this->countPending(),
		];
	}

	/**
	 * @brief Queue one generic analysis job.
	 *
	 * @param string $analysisType Analysis type.
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 * @param int $priority Priority.
	 *
	 * @return bool True when inserted.
	 */
	private function queue(string $analysisType, int $clipId, array $options = [], int $priority = 0): bool
	{
		if ($clipId <= 0)
		{
			return false;
		}

		if (
			($analysisType === 'waveform' && (int) $this->params->get('enable_waveform_generation', 1) !== 1)
			|| ($analysisType === 'spectrogram' && (int) $this->params->get('enable_spectrogram_generation', 1) !== 1)
		)
		{
			return false;
		}

		$jobType = $this->jobType($analysisType);
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_jobs'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('job_type') . ' = :jobType')
			->whereIn($this->database->quoteName('state'), ['pending', 'running'], ParameterType::STRING)
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':jobType', $jobType, ParameterType::STRING);

		if ((int) $this->database->setQuery($query)->loadResult() > 0)
		{
			return false;
		}

		$maximumAttempts = max(1, min(20, (int) $this->params->get('maximum_attempts', 3)));
		$row = (object) [
			'clip_id' => $clipId,
			'job_type' => $jobType,
			'state' => 'pending',
			'priority' => $priority,
			'attempts' => 0,
			'maximum_attempts' => $maximumAttempts,
			'payload' => json_encode(
				[
					'analysis_type' => $analysisType,
					'options' => $options,
				],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			),
			'last_error' => '',
			'created' => Factory::getDate()->toSql(),
			'started' => null,
			'finished' => null,
			'locked_by' => '',
			'locked_until' => null,
		];
		$this->database->transactionStart();

		try
		{
			$this->database->insertObject('#__audioarchive_jobs', $row, 'id');
			$this->repository->markPending($clipId, $analysisType, $options);
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}

		return true;
	}

	/**
	 * @brief Recover analysis jobs left running after an interrupted request.
	 *
	 * @return void
	 */
	private function releaseExpiredJobs(): void
	{
		$now = Factory::getDate()->toSql();
		$pending = 'pending';
		$running = 'running';
		$retryMessage = Text::_('COM_AUDIOARCHIVE_ANALYSIS_JOB_INTERRUPTED_RETRY');
		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_jobs'))
			->set($this->database->quoteName('state') . ' = :pending')
			->set($this->database->quoteName('last_error') . ' = :retryMessage')
			->set($this->database->quoteName('locked_by') . ' = ' . $this->database->quote(''))
			->set($this->database->quoteName('locked_until') . ' = NULL')
			->where($this->database->quoteName('state') . ' = :running')
			->where($this->database->quoteName('job_type') . ' LIKE ' . $this->database->quote('generate_analysis_%'))
			->where($this->database->quoteName('locked_until') . ' IS NOT NULL')
			->where($this->database->quoteName('locked_until') . ' < :now')
			->where($this->database->quoteName('attempts') . ' < ' . $this->database->quoteName('maximum_attempts'))
			->bind(':pending', $pending, ParameterType::STRING)
			->bind(':retryMessage', $retryMessage, ParameterType::STRING)
			->bind(':running', $running, ParameterType::STRING)
			->bind(':now', $now, ParameterType::STRING);
		$this->database->setQuery($query)->execute();

		$failed = 'failed';
		$failureMessage = Text::_('COM_AUDIOARCHIVE_ANALYSIS_JOB_INTERRUPTED_FAILED');
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('clip_id'),
				$this->database->quoteName('payload'),
				$this->database->quoteName('job_type'),
			])
			->from($this->database->quoteName('#__audioarchive_jobs'))
			->where($this->database->quoteName('state') . ' = :running')
			->where($this->database->quoteName('job_type') . ' LIKE ' . $this->database->quote('generate_analysis_%'))
			->where($this->database->quoteName('locked_until') . ' IS NOT NULL')
			->where($this->database->quoteName('locked_until') . ' < :now')
			->where($this->database->quoteName('attempts') . ' >= ' . $this->database->quoteName('maximum_attempts'))
			->bind(':running', $running, ParameterType::STRING)
			->bind(':now', $now, ParameterType::STRING);
		$exhaustedJobs = $this->database->setQuery($query)->loadObjectList() ?: [];

		foreach ($exhaustedJobs as $exhaustedJob)
		{
			$payload = json_decode((string) ($exhaustedJob->payload ?? ''), true);
			$payload = is_array($payload) ? $payload : [];
			$analysisType = $this->analysisTypeFromJob($exhaustedJob, $payload);

			if ($analysisType !== '' && (int) ($exhaustedJob->clip_id ?? 0) > 0)
			{
				$this->repository->markFailed(
					(int) $exhaustedJob->clip_id,
					$analysisType,
					$failureMessage
				);
			}
		}

		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_jobs'))
			->set($this->database->quoteName('state') . ' = :failed')
			->set($this->database->quoteName('last_error') . ' = :failureMessage')
			->set($this->database->quoteName('finished') . ' = :finished')
			->set($this->database->quoteName('locked_by') . ' = ' . $this->database->quote(''))
			->set($this->database->quoteName('locked_until') . ' = NULL')
			->where($this->database->quoteName('state') . ' = :running')
			->where($this->database->quoteName('job_type') . ' LIKE ' . $this->database->quote('generate_analysis_%'))
			->where($this->database->quoteName('locked_until') . ' IS NOT NULL')
			->where($this->database->quoteName('locked_until') . ' < :now')
			->where($this->database->quoteName('attempts') . ' >= ' . $this->database->quoteName('maximum_attempts'))
			->bind(':failed', $failed, ParameterType::STRING)
			->bind(':failureMessage', $failureMessage, ParameterType::STRING)
			->bind(':finished', $now, ParameterType::STRING)
			->bind(':running', $running, ParameterType::STRING)
			->bind(':now', $now, ParameterType::STRING);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Atomically claim the next pending analysis job.
	 *
	 * @return object|null Claimed job.
	 */
	private function claimNextJob(): ?object
	{
		$this->database->transactionStart();

		try
		{
			$query = $this->database->getQuery(true)
				->select([
					$this->database->quoteName('j') . '.*',
					$this->database->quoteName('a.title', 'clip_title'),
				])
				->from($this->database->quoteName('#__audioarchive_jobs', 'j'))
				->leftJoin(
					$this->database->quoteName('#__audioarchive_clips', 'a')
					. ' ON ' . $this->database->quoteName('a.id') . ' = ' . $this->database->quoteName('j.clip_id')
				)
				->where($this->database->quoteName('j.state') . ' = ' . $this->database->quote('pending'))
				->where($this->database->quoteName('j.job_type') . ' LIKE ' . $this->database->quote('generate_analysis_%'))
				->where($this->database->quoteName('j.attempts') . ' < ' . $this->database->quoteName('j.maximum_attempts'))
				->order([
					$this->database->quoteName('j.priority') . ' DESC',
					$this->database->quoteName('j.created') . ' ASC',
					$this->database->quoteName('j.id') . ' ASC',
				]);
			$job = $this->database->setQuery($query, 0, 1)->loadObject();

			if (!is_object($job))
			{
				$this->database->transactionCommit();
				return null;
			}

			$running = 'running';
			$started = Factory::getDate()->toSql();
			$lockSeconds = max(60, min(7200, (int) $this->params->get('process_timeout', 120) + 60));
			$lockedUntil = Factory::getDate('+' . $lockSeconds . ' seconds')->toSql();
			$lockId = bin2hex(random_bytes(16));
			$jobId = (int) $job->id;
			$query = $this->database->getQuery(true)
				->update($this->database->quoteName('#__audioarchive_jobs'))
				->set($this->database->quoteName('state') . ' = :state')
				->set($this->database->quoteName('attempts') . ' = ' . $this->database->quoteName('attempts') . ' + 1')
				->set($this->database->quoteName('started') . ' = :started')
				->set($this->database->quoteName('locked_by') . ' = :lockedBy')
				->set($this->database->quoteName('locked_until') . ' = :lockedUntil')
				->where($this->database->quoteName('id') . ' = :id')
				->where($this->database->quoteName('state') . ' = ' . $this->database->quote('pending'))
				->bind(':state', $running, ParameterType::STRING)
				->bind(':started', $started, ParameterType::STRING)
				->bind(':lockedBy', $lockId, ParameterType::STRING)
				->bind(':lockedUntil', $lockedUntil, ParameterType::STRING)
				->bind(':id', $jobId, ParameterType::INTEGER);
			$this->database->setQuery($query)->execute();

			if ($this->database->getAffectedRows() !== 1)
			{
				$this->database->transactionRollback();
				return null;
			}

			$this->database->transactionCommit();
			$job->attempts = (int) $job->attempts + 1;

			return $job;
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}
	}

	/**
	 * @brief Mark one claimed job finished.
	 *
	 * @param int $jobId Job identifier.
	 * @param string $state completed or failed.
	 * @param string $error Error message.
	 *
	 * @return void
	 */
	private function finishJob(int $jobId, string $state, string $error): void
	{
		$finished = Factory::getDate()->toSql();
		$error = mb_substr(trim($error), 0, 4000);
		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_jobs'))
			->set($this->database->quoteName('state') . ' = :state')
			->set($this->database->quoteName('last_error') . ' = :error')
			->set($this->database->quoteName('finished') . ' = :finished')
			->set($this->database->quoteName('locked_by') . ' = ' . $this->database->quote(''))
			->set($this->database->quoteName('locked_until') . ' = NULL')
			->where($this->database->quoteName('id') . ' = :id')
			->bind(':state', $state, ParameterType::STRING)
			->bind(':error', $error, ParameterType::STRING)
			->bind(':finished', $finished, ParameterType::STRING)
			->bind(':id', $jobId, ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Count pending analysis jobs.
	 *
	 * @return int Pending jobs.
	 */
	private function countPending(): int
	{
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_jobs'))
			->where($this->database->quoteName('state') . ' = ' . $this->database->quote('pending'))
			->where($this->database->quoteName('job_type') . ' LIKE ' . $this->database->quote('generate_analysis_%'));

		return (int) $this->database->setQuery($query)->loadResult();
	}

	/**
	 * @brief Resolve an analysis type from a job payload or its stable job type.
	 *
	 * @param object $job Job record.
	 * @param array<string, mixed> $payload Decoded job payload.
	 *
	 * @return string Valid analysis type or an empty string.
	 */
	private function analysisTypeFromJob(object $job, array $payload): string
	{
		$analysisType = strtolower(trim((string) ($payload['analysis_type'] ?? '')));
		$prefix = 'generate_analysis_';

		if ($analysisType === '' && str_starts_with((string) ($job->job_type ?? ''), $prefix))
		{
			$analysisType = substr((string) $job->job_type, strlen($prefix));
		}

		return preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $analysisType)
			? $analysisType
			: '';
	}

	/**
	 * @brief Build a stable generic analysis job type.
	 *
	 * @param string $analysisType Analysis type.
	 *
	 * @return string Job type.
	 */
	private function jobType(string $analysisType): string
	{
		return 'generate_analysis_' . strtolower(trim($analysisType));
	}
}
