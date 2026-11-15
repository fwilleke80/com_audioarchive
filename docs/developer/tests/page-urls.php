<?php
/** @brief Verify page-number requests, legacy offsets, precedence and filter reset behavior. */
require __DIR__ . '/archive-state.php';
use Punga\Component\Audioarchive\Site\Helper\PaginationHelper as Pages;
foreach ([
    [[], 20, 0], [['page' => 3], 20, 40], [['page' => 3], 50, 100],
    [['start' => 40], 20, 40], [['limitstart' => 60], 20, 60],
    [['page' => 2, 'start' => 80, 'limitstart' => 100], 20, 20],
    [['page' => 0], 20, 0], [['page' => -3], 20, 0], [['page' => 'invalid'], 20, 0],
] as [$request, $limit, $offset])
{
    check(Pages::offset($request, $limit) === $offset, 'page/legacy conversion');
}
check(is_int(Pages::offset(['page' => PHP_INT_MAX], 1000)), 'huge page cannot overflow');
foreach ([
    [['page' => 3], 40], [['start' => 40], 40], [['limitstart' => 40], 40],
    [['page' => 2, 'limitstart' => 100], 20],
    [['page' => 3, 'task' => 'archive.applyFilters'], 0],
] as [$request, $expected])
{
    $app = new Application(new Input($request));
    \Joomla\CMS\Factory::$application = $app;
    $model = new ArchiveUnderTest(new \Joomla\Registry\Registry());
    check($model->getState('list.start') === $expected, 'production archive page state');
}
check(in_array(null, Pages::compact(100, 50), true), 'large lists retain compact ellipses');
check(Pages::compact(3, 2) === [1, 2, 3], 'small lists show every page');
echo "Page URLs, legacy compatibility, precedence and filter reset passed.\n";
