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
			try
			{
				$grant = (new \Punga\Component\Audioarchive\Site\Service\UploadAnalysisService($db, ComponentHelper::getParams('com_audioarchive'), $user))->grant($id, Route::_($return, false));
				if ($grant['jobs'] !== [])
				{
					$grants = (array) $app->getUserState('com_audioarchive.upload.analyses', []);
					$grants = array_filter($grants, static fn(array $entry): bool => (int) ($entry['expires'] ?? 0) >= time());
					$grants[$id] = $grant;
					$app->setUserState('com_audioarchive.upload.analyses', $grants);
					$return = 'index.php?option=com_audioarchive&view=upload&layout=processing&id=' . $id . '&Itemid=' . $itemId;
				}
			}
			catch (\Throwable)
			{
				$app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_ERROR'), 'warning');
			}

		}
		catch (\Throwable $exception)
		{
			$app->setUserState('com_audioarchive.upload.data', array_intersect_key($data, array_flip(['title', 'description', 'catid', 'tags', 'access', 'visibility_mode', 'normalization_mode', 'recorded_at', 'com_fields'])));
			$app->enqueueMessage($exception->getMessage(), 'error');
		}
		$this->setRedirect(Route::_($return, false));
	}
	/** @brief CSRF-protected incremental processing of server-issued upload jobs only. */
	public function processAnalysis(): void
	{
		$app = Factory::getApplication();
		$app->setHeader('Cache-Control', 'private, no-store', true);
		try
		{
			if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !Session::checkToken('post'))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
			$id = $app->getInput()->post->getInt('id');
			$grants = (array) $app->getUserState('com_audioarchive.upload.analyses', []);
			$service = new \Punga\Component\Audioarchive\Site\Service\UploadAnalysisService(Factory::getContainer()->get(DatabaseInterface::class), ComponentHelper::getParams('com_audioarchive'), $app->getIdentity());
			$result = $service->process($id, (array) ($grants[$id] ?? []));
			if ($result['done'])
			{
				$grants[$id]['done'] = true;
				$app->setUserState('com_audioarchive.upload.analyses', $grants);
			}
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: private, no-store');
			echo new \Joomla\CMS\Response\JsonResponse($result);
		}
		catch (\Throwable $error)
		{
			http_response_code($error->getCode() === 403 ? 403 : 500);
			header('Content-Type: application/json; charset=utf-8');
			echo new \Joomla\CMS\Response\JsonResponse(null, Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_ERROR'), true);
		}
		$app->close();
	}

}
