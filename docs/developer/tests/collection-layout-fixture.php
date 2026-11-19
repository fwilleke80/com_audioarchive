<?php
/** @brief Render production collection templates with minimal Joomla services for DOM/layout checks. */
namespace Joomla\Registry
{
    class Registry
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return match ($key)
            {
                'soundboard_header_text' => 'Play clips, switch modes and record a performance.',
                'playlist_header_text' => 'Collect clips and play them in order.',
                default => $default,
            };
        }
    }
}
namespace Joomla\CMS\Language
{
    class Text
    {
        public static array $strings = [];
        public static function sprintf(string $key, mixed ...$args): string
        {
            return sprintf(self::_($key), ...$args);
        }
        public static function _(string $key): string
        {
            return self::$strings[$key] ?? match ($key) { 'JCANCEL' => 'Cancel', 'JSAVE' => 'Save', default => $key };
        }
    }
}
namespace Joomla\CMS
{
    class Factory
    {
        public static function getApplication(): object
        {
            return new class
            {
                public function getIdentity(): object { return (object) ['id' => 7]; }
                public function isClient(string $name): bool { return false; }
            };
        }
    }
}
namespace Joomla\CMS\Session
{
    class Session { public static function getFormToken(): string { return 'csrf'; } }
}
namespace Joomla\CMS\Uri
{
    class Uri { public static function root(): string { return '/'; } }
}
namespace Joomla\CMS\Layout
{
    class LayoutHelper
    {
        public static function render(string $name, array $displayData, mixed ...$args): string
        {
            if ($name !== 'interaction.cover') return '<div class="fixture-player">▶ Playlist player</div>';
            ob_start();
            require JPATH_ROOT . '/site/layouts/interaction/cover.php';
            return ob_get_clean();
        }
    }
}
namespace
{
    define('_JEXEC', 1);
    define('JPATH_ROOT', realpath(__DIR__ . '/../../../pkg_audioarchive/com_audioarchive'));
    require JPATH_ROOT . '/site/src/Helper/StyleHelper.php';
    \Joomla\CMS\Language\Text::$strings = parse_ini_file(JPATH_ROOT . '/site/language/en-GB/com_audioarchive.ini');
    $view = new class
    {
        public object $params;
        public string $pageHeading = 'Sound Board', $returnTitle = 'Sound Board', $canonicalUrl = '/soundboard', $streamTemplate = '', $routesUrl = '', $interactionUrl = '', $interactionToken = '', $playCountUrl = '', $playCountToken = '';
        public int $padCount = 12;
        public bool $samplerEnabled = true, $polyphonic = true, $recordingsEnabled = true, $playlistsEnabled = true, $soundboardEnabled = true;
        public function escape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
        public function render(string $page): void
        {
            require JPATH_ROOT . '/site/tmpl/' . $page . '/default.php';
        }
    };
    $view->params = new \Joomla\Registry\Registry();
    $page = ($argv[1] ?? '') === 'playlists' ? 'playlists' : 'soundboard';
    $view->pageHeading = $page === 'playlists' ? 'Playlists' : 'Sound Board';
    $view->render($page);
}
