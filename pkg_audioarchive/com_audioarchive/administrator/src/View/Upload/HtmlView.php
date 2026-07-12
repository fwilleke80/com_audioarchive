<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Upload;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * @brief Browser bulk-upload view.
 */
class HtmlView extends BaseHtmlView
{
    /** @var Form */
    public Form $form;

    /**
     * @brief Display the bulk-upload page.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        if (!Factory::getApplication()->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $form = $this->get('Form');

        if (!$form instanceof Form)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_BULK_UPLOAD_FORM'), 500);
        }

        $this->form = $form;
        $document = $this->getDocument();
        $assets = $document->getWebAssetManager();
        $assets->useStyle('com_audioarchive.admin');
        $assets->useScript('com_audioarchive.bulk-upload');
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Configure the upload toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_TITLE'), 'upload');

        if (Factory::getApplication()->getIdentity()->authorise('core.options', 'com_audioarchive'))
        {
            ToolbarHelper::preferences('com_audioarchive');
        }
    }
}
