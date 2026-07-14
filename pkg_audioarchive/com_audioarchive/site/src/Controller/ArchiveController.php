<?php

namespace Willeke\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\ArchiveModel;

\defined('_JEXEC') or die;

/**
 * @brief Handle public archive filter submissions.
 */
class ArchiveController extends BaseController
{
	/**
	 * @brief Redirect submitted filters to one concise canonical SEF URL.
	 *
	 * @return static
	 */
	public function applyFilters(): static
	{
		/** @var ArchiveModel $model */
		$model = $this->getModel('Archive');
		$model->getState();
		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
		$url = Route::_(RouteHelper::getArchiveRoute($itemId, $model->getCanonicalQueryValues()));
		$this->setRedirect($url);

		return $this;
	}
}
