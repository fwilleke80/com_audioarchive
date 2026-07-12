<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
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
            'missing_metadata' => (int) ($row['missing_metadata'] ?? 0),
            'preview_attention' => (int) ($row['preview_attention'] ?? 0),
            'waveform_attention' => (int) ($row['waveform_attention'] ?? 0),
        ];
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
