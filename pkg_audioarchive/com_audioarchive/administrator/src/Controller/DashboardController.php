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
    /**
     * @brief Reset all public playback counters.
     *
     * @return void
     */
    public function resetPlayCounts(): void
    {
        $this->resetCounter('play_count', 'COM_AUDIOARCHIVE_PLAY_COUNTS_RESET');
    }

    /**
     * @brief Reset all original-download counters.
     *
     * @return void
     */
    public function resetDownloadCounts(): void
    {
        $this->resetCounter('download_count', 'COM_AUDIOARCHIVE_DOWNLOAD_COUNTS_RESET');
    }

    /**
     * @brief Reset one allow-listed aggregate counter.
     *
     * @param string $column Database counter column.
     * @param string $messageKey Success message language key.
     *
     * @return void
     */
    private function resetCounter(string $column, string $messageKey): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $application = Factory::getApplication();
        $user = $application->getIdentity();

        if (!$user->authorise('core.edit.state', 'com_audioarchive') && !$user->authorise('core.admin', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            /** @var \Willeke\Component\Audioarchive\Administrator\Model\DashboardModel $model */
            $model = $this->getModel('Dashboard');
            $changed = $model->resetCounter($column);
            $application->enqueueMessage(Text::sprintf($messageKey, $changed), 'success');
        }
        catch (\Throwable $exception)
        {
            $application->enqueueMessage(Text::sprintf('COM_AUDIOARCHIVE_COUNTER_RESET_FAILED', $exception->getMessage()), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_audioarchive&view=dashboard', false));
    }

}
