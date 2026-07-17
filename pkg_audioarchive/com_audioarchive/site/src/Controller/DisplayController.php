<?php

namespace Willeke\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\ArchiveModel;

\defined('_JEXEC') or die;

/**
 * @brief Default site controller.
 */
class DisplayController extends BaseController
{
	/** @var string */
	protected $default_view = 'archive';

	/**
	 * @brief Display a site view or complete a transient archive-state reset.
	 *
	 * @param bool $cachable Whether the view may be cached.
	 * @param array $urlparams Safe URL parameters for caching.
	 *
	 * @return static
	 */
	public function display($cachable = false, $urlparams = [])
	{
		$application = Factory::getApplication();
		$input = $application->getInput();
		$view = $input->getCmd('view', $this->default_view);

		if ($view === 'archive' && $input->getInt('audioarchive_reset', 0) === 1)
		{
			$itemId = $input->getInt('Itemid', 0);

			if ($itemId <= 0)
			{
				$itemId = (int) ($application->getMenu()->getActive()?->id ?? 0);
			}

			$application->setUserState(ArchiveModel::getStateSessionKey($itemId), null);
			$application->setUserState(ArchiveModel::getQuerySessionKey($itemId), null);
			$this->setRedirect(
				Route::_(RouteHelper::getArchiveRoute($itemId), false)
			);

			return $this;
		}

		return parent::display($cachable, $urlparams);
	}
}
