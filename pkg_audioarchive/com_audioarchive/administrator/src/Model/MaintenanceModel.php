<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisJobService;
use Willeke\Component\Audioarchive\Administrator\Service\IntegrityService;
use Willeke\Component\Audioarchive\Administrator\Service\MediaMaintenanceService;

\defined('_JEXEC') or die;

/**
 * @brief Model for archive integrity reporting and media maintenance.
 */
class MaintenanceModel extends BaseDatabaseModel
{
	/**
	 * @brief Generate the current integrity and media report.
	 *
	 * @return array<string, mixed> Combined report.
	 */
	public function getReport(): array
	{
		$params = ComponentHelper::getParams('com_audioarchive');
		$integrity = new IntegrityService($this->getDatabase(), $params);
		$media = new MediaMaintenanceService($this->getDatabase(), $params);
		$codecFilter = (string) Factory::getApplication()->getInput()->getString('codec', '');
		$report = $integrity->run();
		$staleItems = $media->getStaleItems();
		$report['codec_inventory'] = $media->getCodecInventory();
		$report['codec_filter'] = $codecFilter;
		$report['codec_clips'] = $media->getClipsByCodec($codecFilter);
		$report['stale_items'] = $staleItems;
		$report['waveforms'] = (new AnalysisJobService(
			$this->getDatabase(),
			$params,
			Factory::getApplication()->getIdentity()
		))->getWaveformSummary();
		$report['summary']['stale_files'] = count($staleItems);

		return $report;
	}

	/**
	 * @brief Queue waveform jobs for one status group.
	 *
	 * @param string $mode Missing, stale, or failed.
	 *
	 * @return int Number of newly queued jobs.
	 */
	public function queueWaveforms(string $mode): int
	{
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

		$service = new AnalysisJobService(
			$this->getDatabase(),
			ComponentHelper::getParams('com_audioarchive'),
			Factory::getApplication()->getIdentity()
		);

		return $service->queueByStatuses('waveform', $statuses);
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
}
