<?php
/** @brief Render both modes and verify escaped URLs, anonymous embed proofs and one script. */
namespace Joomla\CMS\Component
{
    class ComponentHelper
    {
        /** @brief Return mutable mode fixture. */
        public static function getParams(string $name): object
        {
            return new class
            {
                /** @brief Read global mode. */
                public function get(string $key, mixed $default = null): mixed
                {
                    return $GLOBALS['mode'];
                }
            };
        }
    }
}
namespace Joomla\CMS\Language
{
    class Text
    {
        /** @brief Return language identifiers. */
        public static function _(string $key): string
        {
            return $key;
        }
    }
}
namespace Joomla\CMS\Uri
{
    class Uri
    {
        /** @brief Return fixture base. */
        public static function root(): string
        {
            return 'https://audio.test/';
        }
    }
}
namespace Joomla\CMS
{
    class Factory
    {
        /** @brief Supply rendering dependencies. */
        public static function getApplication(): object
        {
            return new class
            {
                /** @brief Supply signing secret. */
                public function get(string $key): string
                {
                    return 'fixture-secret';
                }
                /** @brief Supply signed-in actor. */
                public function getIdentity(): object
                {
                    return (object) ['id' => 12];
                }
                /** @brief Supply language loader. */
                public function getLanguage(): object
                {
                    return new class
                    {
                        /** @brief No-op translation loader. */
                        public function load(string $name, string $path): void
                        {
                        }
                    };
                }
            };
        }
    }
}
namespace
{
    define('_JEXEC', 1);
    define('JPATH_SITE', __DIR__);
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/DownloadTicketService.php';
    use Punga\Component\Audioarchive\Site\Service\DownloadTicketService as Tickets;
    /** @brief Render the actual shared layout. */
    function render(string $mode, string $context): string
    {
        $GLOBALS['mode'] = $mode;
        $displayData = ['clipId' => 7, 'context' => $context, 'url' => '/index.php?id=7&amp;task=stream.download'];
        ob_start();
        include __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/layouts/interaction/download.php';
        return ob_get_clean();
    }
    /** @brief Fail on incorrect rendered behavior. */
    function check(bool $value): void
    {
        if (!$value)
        {
            throw new \RuntimeException('Download layout assertion failed');
        }
    }
    $plain = render('direct', 'site');
    check(str_contains($plain, '<a ') && !str_contains($plain, '<button'));
    check(!str_contains($plain, '&amp;amp;'));
    $site = render('ticket', 'site');
    check(str_contains($site, '<button') && !str_contains($site, 'href='));
    check(str_contains($site, 'stream.downloadTicket') && str_contains($site, 'download.js'));
    preg_match('/data-download-proof="([^"]+)"/', $site, $matches);
    check(Tickets::validProof($matches[1], 7, 12, 'site'));
    $embed = render('ticket', 'embed');
    check(str_contains($embed, 'embed.downloadTicket') && !str_contains($embed, 'download.js'));
    preg_match('/data-download-proof="([^"]+)"/', $embed, $matches);
    check(Tickets::validProof($matches[1], 7, 0, 'embed'));
    echo "Download layout tests passed.\n";
}
