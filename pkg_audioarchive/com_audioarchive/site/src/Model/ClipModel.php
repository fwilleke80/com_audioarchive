<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Public model for one Audio Archive clip.
 */
class ClipModel extends BaseDatabaseModel
{
	/** @var Registry|null */
	private ?Registry $resolvedParams = null;

	/**
	 * @brief Load the requested public clip.
	 *
	 * @param int|null $id Optional clip identifier.
	 *
	 * @return object|null Public clip or null.
	 */
	public function getItem(?int $id = null): ?object
	{
		$id ??= Factory::getApplication()->getInput()->getInt('id', 0);
		$service = new PublicMediaService($this->getDatabase(), $this->getResolvedParams(), $this->getCurrentUser());

		return $service->getPublicClip($id, true);
	}

	/**
	 * @brief Return global settings with active menu-item overrides.
	 *
	 * @return Registry
	 */
	public function getResolvedParams(): Registry
	{
		if ($this->resolvedParams !== null)
		{
			return $this->resolvedParams;
		}

		$params = clone ComponentHelper::getParams('com_audioarchive');
		$item = Factory::getApplication()->getMenu()->getActive();

		if ($item)
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
}
