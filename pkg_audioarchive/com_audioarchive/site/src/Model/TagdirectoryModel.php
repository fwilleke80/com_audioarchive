<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Service\TagDirectoryService;

\defined('_JEXEC') or die;

/**
 * @brief Public Audio Archive tag-directory model.
 */
class TagdirectoryModel extends BaseDatabaseModel
{
	/** @var Registry|null */
	private ?Registry $resolvedParams = null;

	/** @var object|null */
	private ?object $directory = null;

	/**
	 * @brief Return component settings with menu-item parameters applied.
	 *
	 * @return Registry Resolved parameters.
	 */
	public function getResolvedParams(): Registry
	{
		if ($this->resolvedParams !== null)
		{
			return $this->resolvedParams;
		}

		$params = clone ComponentHelper::getParams('com_audioarchive');
		$item = Factory::getApplication()->getMenu()->getActive();

		if ($item !== null)
		{
			foreach ($item->getParams()->toArray() as $key => $value)
			{
				if ($value !== '' && $value !== null)
				{
					$params->set($key, $value);
				}
			}
		}

		$this->resolvedParams = $params;

		return $this->resolvedParams;
	}

	/**
	 * @brief Return the prepared tag directory.
	 *
	 * @return object Object containing items and archive_item_id.
	 */
	public function getDirectory(): object
	{
		if ($this->directory !== null)
		{
			return $this->directory;
		}

		$app = Factory::getApplication();
		$service = new TagDirectoryService($this->getDatabase());
		$this->directory = $service->getDirectory(
			$this->getResolvedParams(),
			$app->getInput()->getInt('Itemid', 0)
		);

		return $this->directory;
	}
}
