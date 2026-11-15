<?php
/** @brief Verify generated page links and sorting resets in the production views. */
namespace Joomla\CMS\Router
{
    class Route
    {
        /** @brief Preserve internal routes without a Joomla router. */
        public static function _(string $url): string
        {
            return $url;
        }
    }
}
namespace
{
    require __DIR__ . '/embedded-table.php';
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Helper/RouteHelper.php';
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/View/Myclips/HtmlView.php';
    /** @brief Supply canonical filters without a database. */
    class ArchiveLinksFixture extends \Punga\Component\Audioarchive\Site\View\Archive\HtmlView
    {
        /** @brief Simulate a filtered later archive page. */
        public function getQueryValues(): array
        {
            return ['q' => 'tone', 'page' => 3, 'limitstart' => 40, 'start' => 40];
        }
    }
    $links = new ArchiveLinksFixture();
    $links->params = new \Joomla\Registry\Registry();
    $links->pagination = (object) ['pagesTotal' => 10, 'pagesCurrent' => 3];
    foreach ([1, 4, 100] as $page)
    {
        parse_str(parse_url($links->getPaginationPageUrl($page), PHP_URL_QUERY), $query);
        check(($query['page'] ?? 1) == min(10, $page), 'page links use bounded page number');
        check($query['q'] === 'tone' && !isset($query['start']) && !isset($query['limitstart']), 'links preserve filters without offsets');
    }
    parse_str(parse_url($links->buildUrl(['sort' => 'title']), PHP_URL_QUERY), $query);
    check(!isset($query['page']) && !isset($query['start']) && !isset($query['limitstart']), 'sorting resets all pagination state');
    /** @brief Supply the string accessor used by My Clips filters. */
    class WorkspaceInput extends Input
    {
        /** @brief Read a string request value. */
        public function getString(string $key, string $default = ''): string
        {
            return (string) ($this->values[$key] ?? $default);
        }
    }
    \Joomla\CMS\Factory::$application = new Application(new WorkspaceInput(['search' => 'tone']));
    $mine = new \Punga\Component\Audioarchive\Site\View\Myclips\HtmlView();
    $mine->pagination = $links->pagination;
    parse_str(parse_url($mine->getPaginationPageUrl(4), PHP_URL_QUERY), $query);
    check($query['view'] === 'myclips' && $query['page'] === '4', 'My Clips uses page-number URLs');
    echo "Production archive and My Clips page links passed.\n";
}
