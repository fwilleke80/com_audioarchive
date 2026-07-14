<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
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
		$report['summary']['stale_files'] = count($staleItems);

		return $report;
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
