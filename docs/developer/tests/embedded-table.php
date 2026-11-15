<?php
/** @brief Render actual shared table templates twice and verify unique players and scoped sorting. */
namespace Joomla\CMS\MVC\View
{
    class HtmlView
    {
        /** @brief Escape template output. */
        public function escape(mixed $value): string
        {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
        /** @brief Load production archive templates in the view scope. */
        public function loadTemplate(?string $part = null): string
        {
            ob_start();
            require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/tmpl/archive/default' . ($part ? '_' . $part : '') . '.php';
            return ob_get_clean();
        }
    }
}
namespace Joomla\CMS\Language
{
    class Text
    {
        /** @brief Provide deterministic labels. */
        public static function _(string $key): string
        {
            return $key;
        }
        /** @brief Provide deterministic formatted labels. */
        public static function sprintf(string $key, mixed ...$arguments): string
        {
            return $key;
        }
    }
}
namespace Joomla\CMS\HTML
{
    class HTMLHelper
    {
        /** @brief Use ISO dates in the fixture. */
        public static function _(string $name, mixed ...$arguments): string
        {
            return (string) ($arguments[0] ?? '');
        }
    }
}
namespace Joomla\CMS\Layout
{
    class LayoutHelper
    {
        /** @brief Exercise the real list-player layout and capture unified player IDs. */
        public static function render(string $name, array $displayData, mixed ...$arguments): string
        {
            if ($name === 'player.list')
            {
                ob_start();
                require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/layouts/player/list.php';
                return ob_get_clean();
            }
            return '<audio id="' . htmlspecialchars($displayData['audioId'] ?? '', ENT_QUOTES, 'UTF-8') . '"></audio>';
        }
    }
}
namespace Joomla\CMS\Uri
{
    class Uri
    {
        private array $query = ['keep' => 'article', 'aa_list_2_sort' => 'title'];
        private string $fragment = '';
        /** @brief Supply an article URL with a second list's sort state. */
        public static function getInstance(): self
        {
            return new self();
        }
        /** @brief Replace a query parameter. */
        public function setVar(string $name, mixed $value): void
        {
            $this->query[$name] = $value;
        }
        /** @brief Set the scroll target. */
        public function setFragment(string $value): void
        {
            $this->fragment = $value;
        }
        /** @brief Serialize the local URL. */
        public function toString(array $parts): string
        {
            return '/article?' . http_build_query($this->query) . '#' . $this->fragment;
        }
    }
}
namespace
{
    require __DIR__ . '/archive-state.php';
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/View/Archive/HtmlView.php';
    $view = new \Punga\Component\Audioarchive\Site\View\Archive\HtmlView();
    $view->params = new \Joomla\Registry\Registry(['archive_column_rating' => 0, 'archive_column_actions' => 0]);
    $view->state = new \Joomla\Registry\Registry(['list.ordering' => 'uploaded', 'list.direction' => 'DESC']);
    $view->items = [(object) ['id' => 7, 'title' => '<unsafe>', 'duration_ms' => 1234, 'mime_type' => 'audio/wav', 'stream_url' => '/audio/7', 'detail_url' => '/clip/7', 'recorded_at' => null, 'uploaded_at' => '2026-11-15', 'tags' => []]];
    $output = '';
    foreach (['aa_list_1', 'aa_list_2'] as $key)
    {
        $view->embedKey = $key;
        $output .= $view->loadTemplate('table');
    }
    preg_match_all('/<audio id="([^"]+)"/', $output, $matches);
    check(count($matches[1]) === 4 && count(array_unique($matches[1])) === 4, 'desktop/mobile player IDs unique across lists');
    check(!str_contains($output, '<unsafe>') && str_contains($output, '&lt;unsafe&gt;'), 'titles escaped');
    check(str_contains($output, '/article?keep=article&amp;aa_list_2_sort=title&amp;aa_list_1_sort=title'), 'sort preserves article and other lists');
    check(str_contains($output, '#aa_list_1'), 'sort returns to embedded list');
    echo "Shared embedded table rendering and scoped sort links passed.\n";
}
