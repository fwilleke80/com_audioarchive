<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Administrator\Service\ManagedStorageService;

\defined('_JEXEC') or die;

/**
 * @brief Administrator dashboard actions.
 */
class DashboardController extends BaseController
{
    /**
     * @brief Create and protect all configured storage directories.
     *
     * @return void
     */
    public function createDirectories(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            $service = new ManagedStorageService(ComponentHelper::getParams('com_audioarchive'));
            $paths = $service->ensureAllDirectories();
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_STORAGE_DIRECTORIES_CREATED', count($paths)),
                'success'
            );
        }
        catch (\Throwable $exception)
        {
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_STORAGE_DIRECTORIES_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_audioarchive&view=dashboard', false));
    }
}
