<?php
/** @brief Verify capped page retrieval using the production model and shared pagination template. */
require __DIR__ . '/embedded-table.php';

/** @brief Substitute only database retrieval, preserving production page calculations. */
class PagedArchiveFixture extends \Punga\Component\Audioarchive\Site\Model\ArchiveModel
{
    /** @var int Number of eligible fixture clips. */
    public int $eligible = 103;
    /** @var array<int,array{int,int}> Observed database windows. */
    public array $windows = [];

    /** @brief Return the matching count before the author's cap. */
    public function getTotal(): int
    {
        return $this->eligible;
    }

    /** @brief Simulate the ordered SQL window requested by production pagination. */
    public function getItems(): array
    {
        $start = (int) $this->getState('list.start');
        $limit = (int) $this->getState('list.limit');
        $this->windows[] = [$start, $limit];
        $ids = range(1, $this->eligible);
        if ($this->getState('list.direction') === 'DESC')
        {
            $ids = array_reverse($ids);
        }
        return array_slice($ids, $start, $limit);
    }
}

foreach ([
    [103, [], 1, 20, 20, 0, 20],
    [103, ['maxresults' => '100'], 5, 100, 20, 80, 20],
    [103, ['maxresults' => '50', 'perpage' => '20'], 3, 50, 20, 40, 10],
    [103, ['maxresults' => '50', 'perpage' => '10'], 5, 50, 10, 40, 10],
    [27, ['maxresults' => '50', 'perpage' => '20'], 2, 27, 20, 20, 7],
    [3, ['maxresults' => '50', 'perpage' => '20'], 999, 3, 20, 0, 3],
    [103, ['maxresults' => '50', 'perpage' => '20'], PHP_INT_MAX, 50, 20, 40, 10],
    [103, ['maxresults' => '50', 'perpage' => '20'], -7, 50, 20, 0, 20],
    [103, ['maxresults' => '10', 'perpage' => '50'], 1, 10, 50, 0, 10],
    [0, [], 1, 0, 20, 0, 0],
] as [$eligible, $options, $page, $total, $perpage, $start, $count])
{
    $model = new PagedArchiveFixture(['ignore_request' => true]);
    $model->eligible = $eligible;
    $model->configureEmbed($options);
    $result = $model->getEmbeddedPage($page);
    check($result['total'] === $total && $result['start'] === $start && $result['perpage'] === $perpage, 'capped bounds');
    check(count($result['items']) === $count, 'partial last page respects total cap');
    check($model->getState('list.limit') === $perpage, 'restore page size after capped fetch');
    check($model->windows === ($count > 0 ? [[$start, $count]] : []), 'fetch only current page; never unlimited empty query');
    if ($count > 0)
    {
        check($result['items'][0] === $eligible - $start, 'sorting precedes cap and pagination');
    }
}
$view->embedKey = 'aa_list_1';
$view->pagination = (object) ['pagesTotal' => 3, 'pagesCurrent' => 2];
$markup = $view->loadTemplate('pagination');
check(str_contains($markup, 'aa_list_1_page=3'), 'next page link');
check(str_contains($markup, 'aa_list_2_sort=title'), 'other list state preserved');
check(str_contains($markup, 'aria-current="page"'), 'accessible current page');
check(!str_contains($markup, '<form'), 'no archive page-size form or filter submission');
check(str_contains($view->buildUrl(['sort' => 'duration']), 'aa_list_1_page=1'), 'sorting resets this page');
check(str_contains($view->getPaginationPageUrl(100), 'aa_list_1_page=3'), 'links clamp to capped page count');
$view->pagination = (object) ['pagesTotal' => 1, 'pagesCurrent' => 1];
check(trim($view->loadTemplate('pagination')) === '', 'hide pagination for a single page');
echo "Embedded capped pagination, independent navigation and edge cases passed.\n";
