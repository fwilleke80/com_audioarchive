<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Willeke\Component\Audioarchive\Administrator\Service\SystemCheckService;

\defined('_JEXEC') or die;

/**
 * @brief Administrator dashboard model.
 */
class DashboardModel extends BaseDatabaseModel
{
    /**
     * @brief Return aggregate clip counts.
     *
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS total',
                'SUM(CASE WHEN state = 1 THEN 1 ELSE 0 END) AS published',
                'SUM(CASE WHEN state = 0 THEN 1 ELSE 0 END) AS unpublished',
                'SUM(CASE WHEN state = -2 THEN 1 ELSE 0 END) AS trashed',
                'COALESCE(SUM(play_count), 0) AS play_count',
                'COALESCE(SUM(download_count), 0) AS download_count',
                "SUM(CASE WHEN metadata_status <> 'available' THEN 1 ELSE 0 END) AS missing_metadata",
                "SUM(CASE WHEN preview_status IN ('pending', 'failed', 'stale') THEN 1 ELSE 0 END) AS preview_attention",
                "SUM(CASE WHEN waveform_status IN ('missing', 'pending', 'failed', 'stale') THEN 1 ELSE 0 END) AS waveform_attention",
            ])
            ->from($db->quoteName('#__audioarchive_clips'));

        $row = $db->setQuery($query)->loadAssoc() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'unpublished' => (int) ($row['unpublished'] ?? 0),
            'trashed' => (int) ($row['trashed'] ?? 0),
            'play_count' => (int) ($row['play_count'] ?? 0),
            'download_count' => (int) ($row['download_count'] ?? 0),
            'missing_metadata' => (int) ($row['missing_metadata'] ?? 0),
            'preview_attention' => (int) ($row['preview_attention'] ?? 0),
            'waveform_attention' => (int) ($row['waveform_attention'] ?? 0),
        ];
    }

    /**
     * @brief Reset one aggregate usage counter for every clip.
     *
     * @param string $column Counter column. Only play_count and download_count are accepted.
     *
     * @return int Number of rows changed by the database.
     */
    public function resetCounter(string $column): int
    {
        if (!in_array($column, ['play_count', 'download_count'], true))
        {
            throw new \InvalidArgumentException('Unsupported Audio Archive counter.');
        }

        $database = $this->getDatabase();
        $query = $database->getQuery(true)
            ->update($database->quoteName('#__audioarchive_clips'))
            ->set($database->quoteName($column) . ' = 0')
            ->where($database->quoteName($column) . ' <> 0');
        $database->setQuery($query)->execute();

        return (int) $database->getAffectedRows();
    }

    /**
     * @brief Return the installed component version from Joomla extension metadata.
     *
     * @return string Installed version.
     */
    public function getVersion(): string
    {
        $database = $this->getDatabase();
        $type = 'component';
        $element = 'com_audioarchive';
        $query = $database->getQuery(true)
            ->select($database->quoteName('manifest_cache'))
            ->from($database->quoteName('#__extensions'))
            ->where($database->quoteName('type') . ' = :type')
            ->where($database->quoteName('element') . ' = :element')
            ->bind(':type', $type, ParameterType::STRING)
            ->bind(':element', $element, ParameterType::STRING);
        $manifest = json_decode((string) $database->setQuery($query)->loadResult(), true);

        return is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
    }

    /**
     * @brief Run non-destructive configuration and server diagnostics.
     *
     * @return array<string, mixed> Structured system-check result.
     */
    public function getSystemCheck(): array
    {
        $service = new SystemCheckService(
            $this->getDatabase(),
            ComponentHelper::getParams('com_audioarchive')
        );

        return $service->run();
    }

}
