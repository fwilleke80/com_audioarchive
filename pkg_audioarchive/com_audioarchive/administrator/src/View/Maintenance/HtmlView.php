<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Maintenance;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * @brief Integrity and maintenance view.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    public array $report = [];

    /**
     * @brief Display the maintenance page.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->report = $this->get('Report');
        $this->getDocument()->getWebAssetManager()
            ->useStyle('com_audioarchive.admin')
            ->useScript('com_audioarchive.analysis-maintenance');
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Configure the maintenance toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_TITLE'), 'wrench');

        if (Factory::getApplication()->getIdentity()->authorise('core.options', 'com_audioarchive'))
        {
            ToolbarHelper::preferences('com_audioarchive');
        }
    }
}
