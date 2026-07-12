<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Clips;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * @brief Administrator clip-list view.
 */
class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $filterForm;
    protected $activeFilters;

    /**
     * @brief Display the clip list.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.admin');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        if (count($errors = $this->get('Errors')))
        {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Configure the list toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        $user = Factory::getApplication()->getIdentity();
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_CLIPS_TITLE'), 'music');

        if ($user->authorise('core.create', 'com_audioarchive'))
        {
            ToolbarHelper::addNew('clip.add');
        }

        if ($user->authorise('core.edit.state', 'com_audioarchive'))
        {
            ToolbarHelper::publish('clips.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('clips.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('clips.archive');
            ToolbarHelper::trash('clips.trash');
        }

        if ($user->authorise('core.delete', 'com_audioarchive'))
        {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'clips.delete');
        }

        if ($user->authorise('core.options', 'com_audioarchive'))
        {
            ToolbarHelper::preferences('com_audioarchive');
        }
    }
}
