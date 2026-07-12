<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Administrator\Model\ClipModel;

\defined('_JEXEC') or die;

/**
 * @brief Controller for the clip list.
 */
class ClipsController extends AdminController
{
	/**
	 * @brief Return the item model.
	 *
	 * @param string $name Model name.
	 * @param string $prefix Model prefix.
	 * @param array $config Model configuration.
	 *
	 * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel|false
	 */
	public function getModel($name = 'Clip', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * @brief Apply category and/or tag changes to selected clips.
	 *
	 * @return void
	 */
	public function batch(): void
	{
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		if (!$user->authorise('core.edit', 'com_audioarchive'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $app->getInput()->post->get('cid', [], 'array')))));
		if (!$ids)
		{
			$app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_BATCH_NO_SELECTION'), 'warning');
			$this->setRedirect(Route::_('index.php?option=com_audioarchive&view=clips', false));
			return;
		}

		$batch = $app->getInput()->post->get('batch', [], 'array');
		/** @var ClipModel $model */
		$model = $this->getModel('Clip', 'Administrator', ['ignore_request' => true]);
		if (!$model->batchUpdate($ids, $batch))
		{
			$app->enqueueMessage($model->getError() ?: Text::_('COM_AUDIOARCHIVE_BATCH_FAILED'), 'error');
		}
		else
		{
			$app->enqueueMessage(Text::plural('COM_AUDIOARCHIVE_BATCH_SUCCESS', count($ids)), 'message');
		}

		$this->setRedirect(Route::_('index.php?option=com_audioarchive&view=clips', false));
	}
}
