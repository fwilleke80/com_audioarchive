<?php
/** @brief Check placeholder grammar, backwards compatibility and optional list parameters. */
namespace Joomla\Event
{
    interface SubscriberInterface
    {
    }
}
namespace Joomla\CMS\Plugin
{
    class CMSPlugin
    {
        protected object $params;
        /** @brief Supply configured defaults. */
        public function __construct()
        {
            $this->params = new \Joomla\Registry\Registry(['presentation' => 'default', 'preferred_data_view' => 'waveform']);
        }
    }
}
namespace
{
    require __DIR__ . '/archive-state.php';
    require __DIR__ . '/../../../pkg_audioarchive/plg_content_audioarchive/src/Extension/Audioarchive.php';
    $plugin = new \Punga\Plugin\Content\Audioarchive\Extension\Audioarchive();
    $parse = new ReflectionMethod($plugin, 'parseArguments');
    foreach ([
        ['list', 'list'],
        ['list maxresults=100 perpage=20', 'list'],
        ['list search="some clip title or description" length_min=1s length_max=10s tags="funny melodic gnarly" maxresults=10', 'list'],
        ['list tags=""', 'list'],
        ['list recorded_from=2026-01-01 uploaded_to=2026-12-31 category=music owner=7 tag_mode=or', 'list'],
        ['random', 'random'], ['longest count=3 layout=compact', 'longest'],
        ['clip=my-clip layout=featured', 'specific'], ['count category=music', 'count'], ['playtime', 'playtime'],
    ] as [$input, $mode])
    {
        check(($parse->invoke($plugin, $input)['mode'] ?? null) === $mode, 'parse ' . $input);
    }
    check($parse->invoke($plugin, 'list')['filters'] === [], 'bare list has no mandatory filters');
    check($parse->invoke($plugin, 'list search="words with spaces"')['filters']['search'] === 'words with spaces', 'quoted search');
    check($parse->invoke($plugin, 'list random') === null, 'reject mixed modes');
    check($parse->invoke($plugin, 'list unexpected=1') === null, 'reject unsupported attributes');
    echo "Content list grammar and existing placeholder compatibility passed.\n";
}
