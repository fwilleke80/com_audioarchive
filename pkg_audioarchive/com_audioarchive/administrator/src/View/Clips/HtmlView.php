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
    public $filterForm;
    public $activeFilters;
    public array $batchCategories = [];
    public array $batchTags = [];

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
        $this->batchCategories = $this->get('BatchCategories');
        $this->batchTags = $this->get('BatchTags');

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
        $toolbar = $this->getDocument()->getToolbar();
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_CLIPS_TITLE'), 'music');

        if ($user->authorise('core.create', 'com_audioarchive'))
        {
            $toolbar->addNew('clip.add');
        }

        if ($user->authorise('core.edit.state', 'com_audioarchive'))
        {
            $toolbar->publish('clips.publish')->listCheck(true);
            $toolbar->unpublish('clips.unpublish')->listCheck(true);
            $toolbar->archive('clips.archive')->listCheck(true);
            $toolbar->trash('clips.trash')->listCheck(true);
        }

        if ($user->authorise('core.edit', 'com_audioarchive'))
        {
            $toolbar->popupButton('batch', 'JTOOLBAR_BATCH')
                ->popupType('inline')
                ->textHeader(Text::_('COM_AUDIOARCHIVE_BATCH_TITLE'))
                ->url('#joomla-dialog-audioarchive-batch')
                ->modalWidth('800px')
                ->modalHeight('fit-content')
                ->listCheck(true);
        }

        if ($user->authorise('core.delete', 'com_audioarchive'))
        {
            $toolbar->delete('clips.delete')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($user->authorise('core.options', 'com_audioarchive'))
        {
            $toolbar->preferences('com_audioarchive');
        }
    }
}
