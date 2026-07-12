<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Dashboard;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * @brief Audio Archive dashboard view.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, int> */
    protected array $counts = [];

    /** @var array<string, mixed> */
    protected array $systemCheck = [];

    /**
     * @brief Display the dashboard.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.admin');
        $this->counts = $this->get('Counts');
        $this->systemCheck = $this->get('SystemCheck');
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Configure the administrator toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_DASHBOARD_TITLE'), 'music');

        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.options', 'com_audioarchive'))
        {
            ToolbarHelper::preferences('com_audioarchive');
        }
    }
}
