<?php
namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Administrator\Service\AudioUploadService;
use Punga\Component\Audioarchive\Administrator\Service\UserQuotaService;
use Punga\Component\Audioarchive\Site\Service\ContributionService;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;

\defined('_JEXEC') or die;

/** @brief Authenticated, CSRF-protected frontend clip creation. */
class UploadController extends BaseController
{
	/** @brief Validate and store one real PHP upload; never accept an owner from the browser. */
	public function submit(): void
	{
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$user = $app->getIdentity();
		$policy = new ContributionService($db, $user);
		$params = $policy->settings(true);
		$itemId = $app->getInput()->getInt('Itemid');
		$return = 'index.php?option=com_audioarchive&view=upload&Itemid=' . $itemId;
		$data = $app->getInput()->post->get('jform', [], 'array');
		try
		{
			$model = $this->getModel('Upload', 'Site', ['ignore_request' => true]);
			$form = $model->getForm([], false);
			$filtered = $model->validate($form, $data);
			if ($filtered === false)
			{
				throw new \RuntimeException(implode(' ', array_map(static fn($error): string => $error instanceof \Throwable ? $error->getMessage() : (string) $error, $model->getErrors())));
			}
			$filtered = $policy->sanitise($filtered, $params);
			$files = $app->getInput()->files->get('jform', [], 'array');
			$file = $files['original_audio'] ?? [];
			if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? '')))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_FILE'));
			}
			(new UserQuotaService($db, ComponentHelper::getParams('com_audioarchive'), $user))->assertIncrease((int) $user->id, (int) filesize($file['tmp_name']), 1);
			$upload = new AudioUploadService($db, ComponentHelper::getParams('com_audioarchive'), $user);
			$prepared = $upload->prepare($file);
			if (!$model->savePreparedFile($filtered, $prepared))
			{
				throw new \RuntimeException($model->getError());
			}
			$id = (int) $model->getState('upload.id');
			$app->setUserState('com_audioarchive.upload.data', null);
			$app->enqueueMessage(Text::_((int) $filtered['state'] === 1 ? 'COM_AUDIOARCHIVE_FRONTEND_UPLOAD_SUCCESS' : 'COM_AUDIOARCHIVE_FRONTEND_UPLOAD_PENDING'), 'success');
			$destination = (string) $params->get('upload_redirect', 'myclips');
			if ($destination === 'clip' && (new \Punga\Component\Audioarchive\Administrator\Service\ClipAccessService($db, $user))->canView($model->getItem($id)))
			{
				$return = RouteHelper::getClipRoute($id);
			}
			elseif ($destination !== 'upload')
			{
				$return = $policy->route('myclips');
			}
		}
		catch (\Throwable $exception)
		{
			$app->setUserState('com_audioarchive.upload.data', array_intersect_key($data, array_flip(['title', 'description', 'catid', 'tags', 'access', 'visibility_mode', 'normalization_mode', 'recorded_at', 'com_fields'])));
			$app->enqueueMessage($exception->getMessage(), 'error');
		}
		$this->setRedirect(Route::_($return, false));
	}
}
