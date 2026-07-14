<?php

namespace Willeke\Module\AudioarchiveTags\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Service\TagDirectoryService;

\defined('_JEXEC') or die;

/**
 * @brief Prepare Audio Archive tags for the tag-directory module.
 */
abstract class AudioarchiveTagsHelper
{
	/**
	 * @brief Return the configured public tag directory.
	 *
	 * @param Registry $params Module parameters.
	 *
	 * @return object Object containing items and archive_item_id.
	 */
	public static function getDirectory(Registry $params): object
	{
		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$service = new TagDirectoryService($database);

		return $service->getDirectory(
			$params,
			Factory::getApplication()->getInput()->getInt('Itemid', 0)
		);
	}
}
