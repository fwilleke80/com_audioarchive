<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Clips;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
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
        $this->getDocument()->getWebAssetManager()
            ->useStyle('com_audioarchive.admin')
            ->useScript('com_audioarchive.batch');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->batchCategories = $this->get('BatchCategories');
        $this->batchTags = $this->get('BatchTags');

        // Joomla 6 pagination and search tools operate on registered control
        // fields rather than ad-hoc hidden inputs in the layout.
        $this->filterForm->addControlField('task', '');
        $this->filterForm->addControlField('boxchecked', '0');
        $this->filterForm->addControlField('limitstart', (string) (int) $this->pagination->limitstart);

        if (count($errors = $this->get('Errors')))
        {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }


    /**
     * @brief Build a direct administrator URL for a pagination page.
     *
     * @param int $page One-based page number.
     *
     * @return string Routed administrator URL.
     */
    public function getPaginationUrl(int $page): string
    {
        $totalPages = max(1, (int) $this->pagination->pagesTotal);
        $page = max(1, min($page, $totalPages));
        $limit = max(1, (int) $this->pagination->limit);
        $query = [
            'option' => 'com_audioarchive',
            'view' => 'clips',
            'list' => [
                'fullordering' => (string) $this->state->get('list.ordering', 'a.uploaded_at')
                    . ' ' . strtoupper((string) $this->state->get('list.direction', 'DESC')),
                'limit' => $limit,
            ],
        ];

        $search = trim((string) $this->state->get('filter.search', ''));

        if ($search !== '')
        {
            $query['filter_search'] = $search;
        }

        $state = $this->state->get('filter.state', '');

        if ($state !== '')
        {
            $query['filter_state'] = (int) $state;
        }

        $categoryId = (int) $this->state->get('filter.category_id', 0);

        if ($categoryId > 0)
        {
            $query['filter_category_id'] = $categoryId;
        }

        $access = (int) $this->state->get('filter.access', 0);

        if ($access > 0)
        {
            $query['filter_access'] = $access;
        }

        $limitstart = ($page - 1) * $limit;

        if ($limitstart > 0)
        {
            $query['limitstart'] = $limitstart;
        }

        return Route::_('index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
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
        $isTrashed = (int) $this->state->get('filter.state', 0) === -2;
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

            if (!$isTrashed)
            {
                $toolbar->trash('clips.trash')->listCheck(true);
            }
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

        if ($isTrashed && $user->authorise('core.delete', 'com_audioarchive'))
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
