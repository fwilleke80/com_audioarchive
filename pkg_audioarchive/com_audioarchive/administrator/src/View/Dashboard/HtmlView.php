<?php

namespace Willeke\Component\Audioarchive\Administrator\View\Dashboard;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * @brief Audio Archive dashboard view.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, int> */
    protected array $counts = [];

    /** @var array<string, mixed> */
    protected array $systemCheck = [];

    /** @var string */
    protected string $version = '';

    /** @var bool */
    protected bool $finderInstalled = false;

    /**
     * @brief Display the dashboard.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.admin');
        $this->counts = $this->get('Counts');
        $this->version = $this->get('Version');
        $this->finderInstalled = $this->isFinderPluginInstalled();
        $this->systemCheck = $this->get('SystemCheck');
        $this->formatSystemCheckTimestamp();
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * @brief Format the system-check timestamp in the current administrator's time zone.
     *
     * @return void
     */
    private function formatSystemCheckTimestamp(): void
    {
        $checkedAtValue = trim((string) ($this->systemCheck['checked_at'] ?? ''));

        if ($checkedAtValue === '')
        {
            $this->systemCheck['checked_at_display'] = '';
            return;
        }

        $application = Factory::getApplication();
        $timezoneName = (string) $application->getIdentity()->getParam(
            'timezone',
            $application->get('offset', 'UTC')
        );

        try
        {
            $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
        }
        catch (\Throwable $exception)
        {
            $timezone = new \DateTimeZone('UTC');
        }

        $checkedAt = Factory::getDate($checkedAtValue, 'UTC');
        $checkedAt->setTimezone($timezone);
        $this->systemCheck['checked_at_display'] = $checkedAt->format(Text::_('DATE_FORMAT_LC2'), true);
    }

    /**
     * @brief Determine whether the Audio Archive Smart Search plugin is installed.
     *
     * @return bool True when the finder plugin extension record exists.
     */
    private function isFinderPluginInstalled(): bool
    {
        try
        {
            $database = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $database->getQuery(true)
                ->select('COUNT(*)')
                ->from($database->quoteName('#__extensions'))
                ->where($database->quoteName('type') . ' = ' . $database->quote('plugin'))
                ->where($database->quoteName('folder') . ' = ' . $database->quote('finder'))
                ->where($database->quoteName('element') . ' = ' . $database->quote('audioarchive'));

            return (int) $database->setQuery($query)->loadResult() > 0;
        }
        catch (\Throwable $exception)
        {
            return false;
        }
    }

    /**
     * @brief Configure the administrator toolbar.
     *
     * @return void
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_DASHBOARD_TITLE'), 'music');

        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.options', 'com_audioarchive'))
        {
            ToolbarHelper::preferences('com_audioarchive');
        }
    }
}
