<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisJobService;
use Willeke\Component\Audioarchive\Administrator\Service\IntegrityService;
use Willeke\Component\Audioarchive\Administrator\Service\ManagedStorageService;
use Willeke\Component\Audioarchive\Administrator\Service\MediaMaintenanceService;

\defined('_JEXEC') or die;

/**
 * @brief Model for archive integrity reporting and media maintenance.
 */
class MaintenanceModel extends BaseDatabaseModel
{
	/**
	 * @brief Build the maintenance page report without automatic filesystem scans.
	 *
	 * Expensive integrity, codec, and stale-file checks run only when selected
	 * explicitly through the maintenance page.
	 *
	 * @return array<string, mixed> Current maintenance data.
	 */
	public function getReport(): array
	{
		$params = ComponentHelper::getParams('com_audioarchive');
		$application = Factory::getApplication();
		$check = strtolower($application->getInput()->getCmd('check', ''));
		$codecFilter = (string) $application->getInput()->getString('codec', '');

		if ($codecFilter !== '')
		{
			$check = 'codecs';
		}

		if (!in_array($check, ['', 'integrity', 'codecs', 'stale'], true))
		{
			$check = '';
		}

		$analysisJobs = new AnalysisJobService(
			$this->getDatabase(),
			$params,
			$application->getIdentity()
		);
		$report = [
			'checked_section' => $check,
			'checked_at' => '',
			'summary' => $this->getBasicSummary(),
			'issues' => [],
			'actionable_clips' => [],
			'codec_inventory' => [],
			'codec_filter' => $codecFilter,
			'codec_clips' => [],
			'stale_items' => [],
			'waveforms' => $analysisJobs->getWaveformSummary(),
			'spectrograms' => $analysisJobs->getSpectrogramSummary(),
		];

		if ($check === 'integrity')
		{
			$integrityReport = (new IntegrityService($this->getDatabase(), $params))->run();
			$report = array_replace($report, $integrityReport);
			$report['checked_section'] = 'integrity';
		}
		elseif ($check === 'codecs')
		{
			$media = new MediaMaintenanceService($this->getDatabase(), $params);
			$report['codec_inventory'] = $media->getCodecInventory();
			$report['codec_clips'] = $media->getClipsByCodec($codecFilter);
			$report['checked_at'] = Factory::getDate()->format('Y-m-d H:i:s');
		}
		elseif ($check === 'stale')
		{
			$staleItems = (new MediaMaintenanceService($this->getDatabase(), $params))->getStaleItems();
			$report['stale_items'] = $staleItems;
			$report['summary']['stale_files'] = count($staleItems);
			$report['checked_at'] = Factory::getDate()->format('Y-m-d H:i:s');
		}

		return $report;
	}

	/**
	 * @brief Generate a complete integrity report for explicit export requests.
	 *
	 * @return array<string, mixed> Complete integrity report.
	 */
	public function getIntegrityReport(): array
	{
		return (new IntegrityService(
			$this->getDatabase(),
			ComponentHelper::getParams('com_audioarchive')
		))->run();
	}

	/**
	 * @brief Queue waveform jobs for one status group or every eligible clip.
	 *
	 * @param string $mode Missing, stale, failed, or all.
	 *
	 * @return int Number of newly queued jobs.
	 */
	public function queueWaveforms(string $mode): int
	{
		return $this->queueAnalysis('waveform', $mode);
	}

	/**
	 * @brief Queue spectrogram jobs for one status group or every eligible clip.
	 *
	 * @param string $mode Missing, stale, failed, or all.
	 *
	 * @return int Number of newly queued jobs.
	 */
	public function queueSpectrograms(string $mode): int
	{
		return $this->queueAnalysis('spectrogram', $mode);
	}

	/**
	 * @brief Process the next pending analysis job.
	 *
	 * @return array<string, mixed> Processing result.
	 */
	public function processNextAnalysisJob(): array
	{
		$service = new AnalysisJobService(
			$this->getDatabase(),
			ComponentHelper::getParams('com_audioarchive'),
			Factory::getApplication()->getIdentity()
		);

		return $service->processNext();
	}

	/**
	 * @brief Delete all generated data for one supported analysis type.
	 *
	 * Completed and failed job rows remain as history. Pending and running jobs
	 * are retained but marked cancelled.
	 *
	 * @param string $analysisType Waveform or spectrogram.
	 *
	 * @return array{records:int,cancelled:int,deleted:int,bytes:int,failed:int} Deletion summary.
	 */
	public function deleteAllAnalysis(string $analysisType): array
	{
		$analysisType = strtolower(trim($analysisType));

		if (!in_array($analysisType, ['waveform', 'spectrogram'], true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_UNSUPPORTED_BULK_TYPE'));
		}

		$database = $this->getDatabase();
		$params = ComponentHelper::getParams('com_audioarchive');
		$statusField = $analysisType === 'waveform' ? 'waveform_status' : 'spectrogram_status';
		$query = $database->getQuery(true)
			->select('COUNT(*)')
			->from($database->quoteName('#__audioarchive_analyses'))
			->where($database->quoteName('analysis_type') . ' = :analysisType')
			->bind(':analysisType', $analysisType, ParameterType::STRING);
		$records = (int) $database->setQuery($query)->loadResult();
		$jobs = new AnalysisJobService($database, $params, Factory::getApplication()->getIdentity());
		$database->transactionStart();

		try
		{
			$cancelled = $jobs->cancelActive($analysisType);
			$query = $database->getQuery(true)
				->delete($database->quoteName('#__audioarchive_analyses'))
				->where($database->quoteName('analysis_type') . ' = :analysisType')
				->bind(':analysisType', $analysisType, ParameterType::STRING);
			$database->setQuery($query)->execute();

			if ($analysisType === 'waveform')
			{
				$database->setQuery(
					$database->getQuery(true)->delete($database->quoteName('#__audioarchive_waveforms'))
				)->execute();
			}

			$query = $database->getQuery(true)
				->update($database->quoteName('#__audioarchive_clips'))
				->set($database->quoteName($statusField) . ' = ' . $database->quote('missing'));
			$database->setQuery($query)->execute();
			$database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$database->transactionRollback();
			throw $exception;
		}

		$files = (new ManagedStorageService($params))->deleteAnalysisTypeFiles($analysisType);

		return [
			'records' => $records,
			'cancelled' => $cancelled,
			'deleted' => (int) ($files['deleted'] ?? 0),
			'bytes' => (int) ($files['bytes'] ?? 0),
			'failed' => (int) ($files['failed'] ?? 0),
		];
	}

	/**
	 * @brief Delete selected revalidated stale media items.
	 *
	 * @param string[] $tokens Selected candidate tokens.
	 *
	 * @return array{succeeded:int,failed:int,messages:string[]} Cleanup result.
	 */
	public function deleteStaleItems(array $tokens): array
	{
		$service = new MediaMaintenanceService(
			$this->getDatabase(),
			ComponentHelper::getParams('com_audioarchive')
		);

		return $service->deleteStaleItems($tokens);
	}

	/**
	 * @brief Queue analysis jobs for one status group or every eligible clip.
	 *
	 * @param string $analysisType Analysis type.
	 * @param string $mode Missing, stale, failed, or all.
	 *
	 * @return int Number of newly queued jobs.
	 */
	private function queueAnalysis(string $analysisType, string $mode): int
	{
		$service = new AnalysisJobService(
			$this->getDatabase(),
			ComponentHelper::getParams('com_audioarchive'),
			Factory::getApplication()->getIdentity()
		);

		if ($mode === 'all')
		{
			return $service->queueAll($analysisType);
		}

		$statuses = match ($mode)
		{
			'missing' => ['missing'],
			'stale' => ['stale'],
			'failed' => ['failed'],
			default => [],
		};

		if ($statuses === [])
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_INVALID_QUEUE_MODE'));
		}

		return $service->queueByStatuses($analysisType, $statuses);
	}

	/**
	 * @brief Return inexpensive database-only summary counts.
	 *
	 * @return array<string, int> Basic summary.
	 */
	private function getBasicSummary(): array
	{
		$database = $this->getDatabase();
		$query = $database->getQuery(true)
			->select('COUNT(*)')
			->from($database->quoteName('#__audioarchive_clips'));
		$clips = (int) $database->setQuery($query)->loadResult();
		$query = $database->getQuery(true)
			->select('COUNT(*)')
			->from($database->quoteName('#__audioarchive_files'))
			->where($database->quoteName('file_role') . ' = ' . $database->quote('original'));
		$originalRecords = (int) $database->setQuery($query)->loadResult();

		return [
			'clips' => $clips,
			'original_records' => $originalRecords,
			'managed_original_files' => 0,
			'errors' => 0,
			'warnings' => 0,
			'issues' => 0,
			'stale_files' => 0,
		];
	}
}
