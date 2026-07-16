<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Clip;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisRepositoryService;

\defined('_JEXEC') or die;

/**
 * @brief Administrator clip edit view.
 */
class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;
    protected $state;
    protected $originalFile;
    protected bool $canManageFiles = false;
    protected bool $canProcess = false;
    protected string $playbackUrl = '';
    protected string $waveformUrl = '';
    protected string $spectrogramUrl = '';

    /**
     * @brief Display the edit form.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $this->getDocument()->getWebAssetManager()
            ->useStyle('com_audioarchive.admin')
            ->useStyle('com_audioarchive.player-style')
            ->useScript('com_audioarchive.player');
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $this->originalFile = $this->get('OriginalFile');
        $user = Factory::getApplication()->getIdentity();
        $this->canManageFiles = $user->authorise('audioarchive.managefiles', 'com_audioarchive');
        $this->canProcess = $user->authorise('audioarchive.process', 'com_audioarchive');

        if (
            (int) ($this->item->id ?? 0) > 0
            && $this->originalFile !== null
            && (int) ($this->originalFile->is_available ?? 0) === 1
        )
        {
            $this->playbackUrl = Route::_(
                'index.php?option=com_audioarchive&task=media.play&id=' . (int) $this->item->id
                . '&' . Session::getFormToken() . '=1',
                false
            );
            $repository = new AnalysisRepositoryService(
                Factory::getContainer()->get(DatabaseInterface::class)
            );
            $waveform = $repository->get((int) $this->item->id, 'waveform');

            if (
                $waveform !== null
                && (int) ($waveform->is_available ?? 0) === 1
                && (string) ($waveform->status ?? '') === 'available'
            )
            {
                $this->waveformUrl = Route::_(
                    'index.php?option=com_audioarchive&task=media.analysis&id=' . (int) $this->item->id
                    . '&type=waveform&' . Session::getFormToken() . '=1',
                    false
                );
            }
            $spectrogram = $repository->get((int) $this->item->id, 'spectrogram');

            if (
                $spectrogram !== null
                && (int) ($spectrogram->is_available ?? 0) === 1
                && (string) ($spectrogram->status ?? '') === 'available'
            )
            {
                $this->spectrogramUrl = Route::_(
                    'index.php?option=com_audioarchive&task=media.analysis&id=' . (int) $this->item->id
                    . '&type=spectrogram&' . Session::getFormToken() . '=1',
                    false
                );
            }
        }

        if (count($errors = $this->get('Errors')))
        {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Configure the edit toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        $isNew = empty($this->item->id);
        $title = $isNew ? Text::_('COM_AUDIOARCHIVE_CLIP_NEW') : Text::_('COM_AUDIOARCHIVE_CLIP_EDIT');
        ToolbarHelper::title($title, 'music');
        ToolbarHelper::apply('clip.apply');
        ToolbarHelper::save('clip.save');
        ToolbarHelper::save2new('clip.save2new');

        if (!$isNew)
        {
            ToolbarHelper::save2copy('clip.save2copy');
        }

        ToolbarHelper::cancel('clip.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
