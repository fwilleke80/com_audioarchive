<?php
namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Punga\Component\Audioarchive\Administrator\Service\UserQuotaService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;
use Punga\Component\Audioarchive\Site\Service\ContributionService;

\defined('_JEXEC') or die;

/** @brief Handle owner-only workspace state changes. */
class MyclipsController extends BaseController
{
	/** @brief Move an owned clip to Trash using normal Joomla state permission. */
	public function trash(): void
	{
		$this->mutate('trash');
	}

	/** @brief Restore an owned trashed clip as unpublished. */
	public function restore(): void
	{
		$this->mutate('restore');
	}

	/** @brief Permanently delete an owned, already-trashed clip after explicit confirmation. */
	public function delete(): void
	{
		$this->mutate('delete');
	}

	/** @brief Repeat owner, permission and CSRF checks before invoking the existing administrator model. */
	private function mutate(string $action): void
	{
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		if ((int) $user->id <= 0)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$id = $app->getInput()->post->getInt('id');
		[$result, $error] = (new UserQuotaService($db, ComponentHelper::getParams('com_audioarchive'), $user))->withOwnerLocks([(int) $user->id], function () use ($db, $id, $user, $action, $app): array
		{
			$item = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_clips') . ' WHERE id=' . $id)->loadObject();
			$access = new ClipAccessService($db, $user);
			if (!$item || (int) $item->created_by !== (int) $user->id || ($action === 'delete' ? ((int) $item->state !== -2 || !$access->canDelete($item) || !$app->getInput()->post->getInt('confirm_delete')) : (!$access->canEditState($item) || ($action === 'restore' && (int) $item->state !== -2))))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
			$model = $app->bootComponent('com_audioarchive')->getMVCFactory()->createModel('Clip', 'Administrator', ['ignore_request' => true]);
			$ids = [$id];
			$result = $action === 'delete' ? $model->delete($ids) : $model->publish($ids, $action === 'restore' ? 0 : -2);
			return [$result, $model->getError()];
		});
		$app->enqueueMessage($result ? Text::_('COM_AUDIOARCHIVE_MY_CLIPS_UPDATED') : $error, $result ? 'success' : 'error');
		$this->setRedirect(Route::_((new ContributionService($db, $user))->route('myclips'), false));
	}
}
