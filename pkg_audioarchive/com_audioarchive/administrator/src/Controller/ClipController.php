<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisManagerService;

\defined('_JEXEC') or die;

/**
 * @brief Controller for editing one clip.
 */
class ClipController extends FormController
{
    /** @var string */
    protected $view_list = 'clips';

    /**
     * @brief Check whether the current user may edit one clip.
     *
     * @param array $data Record data containing the clip identifier.
     * @param string $key Primary-key field name.
     *
     * @return bool True when editing is permitted.
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        $recordId = (int) ($data[$key] ?? 0);

        if ($recordId <= 0)
        {
            return false;
        }

        $user = Factory::getApplication()->getIdentity();
        $asset = 'com_audioarchive.clip.' . $recordId;

        if ($user->authorise('core.edit', $asset))
        {
            return true;
        }

        if (!$user->authorise('core.edit.own', $asset))
        {
            return false;
        }

        $table = $this->getModel()->getTable();

        return $table->load($recordId)
            && (int) $table->created_by === (int) $user->id;
    }

    /**
     * @brief Reinspect the stored original and refresh technical metadata.
     *
     * @return void
     */
    public function reanalyse(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $clipId = $this->getPostedClipId();

        if (!$app->getIdentity()->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            $model = $this->getModel('Clip');
            $warnings = $model->reanalyseOriginal($clipId);
            $app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_REANALYSE_SUCCESS'), 'success');

            foreach ($warnings as $warning)
            {
                $app->enqueueMessage((string) $warning, 'warning');
            }
        }
        catch (\Throwable $exception)
        {
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_REANALYSE_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->getEditRedirect($clipId));
    }

    /**
     * @brief Verify the stored original against its database record.
     *
     * @return void
     */
    public function verify(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $clipId = $this->getPostedClipId();

        if (!$app->getIdentity()->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            $model = $this->getModel('Clip');
            $result = $model->verifyOriginal($clipId);
            $app->enqueueMessage(
                (string) $result['message'],
                (bool) $result['ok'] ? 'success' : 'warning'
            );
        }
        catch (\Throwable $exception)
        {
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_VERIFY_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->getEditRedirect($clipId));
    }

    /**
     * @brief Generate or regenerate waveform analysis for the current clip.
     *
     * @return void
     */
    public function generateWaveform(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $clipId = $this->getPostedClipId();

        if (!$app->getIdentity()->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            $manager = new AnalysisManagerService(
                Factory::getContainer()->get(DatabaseInterface::class),
                ComponentHelper::getParams('com_audioarchive'),
                $app->getIdentity()
            );
            $result = $manager->generate('waveform', $clipId);
            $app->enqueueMessage(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_WAVEFORM_GENERATED_SUCCESS',
                    (int) ($result->parameters['point_count'] ?? 0)
                ),
                'success'
            );
        }
        catch (\Throwable $exception)
        {
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_WAVEFORM_GENERATED_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->getEditRedirect($clipId));
    }

    /**
     * @brief Generate or regenerate spectral analysis for the current clip.
     *
     * @return void
     */
    public function generateSpectrogram(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $clipId = $this->getPostedClipId();

        if (!$app->getIdentity()->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try
        {
            $manager = new AnalysisManagerService(
                Factory::getContainer()->get(DatabaseInterface::class),
                ComponentHelper::getParams('com_audioarchive'),
                $app->getIdentity()
            );
            $result = $manager->generate('spectrogram', $clipId);
            $app->enqueueMessage(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_SPECTROGRAM_GENERATED_SUCCESS',
                    (int) ($result->parameters['width'] ?? 0),
                    (int) ($result->parameters['height'] ?? 0)
                ),
                'success'
            );
        }
        catch (\Throwable $exception)
        {
            $app->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_SPECTROGRAM_GENERATED_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->getEditRedirect($clipId));
    }

    /**
     * @brief Read and validate the clip identifier from the posted edit form.
     *
     * @return int Clip identifier.
     */
    private function getPostedClipId(): int
    {
        $data = Factory::getApplication()->getInput()->post->get('jform', [], 'array');
        $clipId = (int) ($data['id'] ?? 0);

        if ($clipId <= 0)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_INVALID_CLIP_ID'), 400);
        }

        return $clipId;
    }

    /**
     * @brief Build the administrator edit-page redirect.
     *
     * @param int $clipId Clip identifier.
     *
     * @return string Routed edit URL.
     */
    private function getEditRedirect(int $clipId): string
    {
        return Route::_(
            'index.php?option=com_audioarchive&task=clip.edit&id=' . $clipId,
            false
        );
    }
}
