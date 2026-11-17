<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Portable private covers, referenced by entity UUID and validated again on restore. */
final class CoverArchiveService
{
    /** @brief Include cover bytes in authenticated archive backups, never public collection sharing. */
    public static function export(DatabaseInterface $db): array
    {
        $rows = [];
        foreach (['clip' => '#__audioarchive_clips', 'playlist' => '#__audioarchive_collections'] as $kind => $table)
        {
            $batch = $db->setQuery('SELECT a.*, e.uuid FROM #__audioarchive_covers a INNER JOIN ' . $table . ' e ON e.id=a.entity_id WHERE a.entity_type=' . $db->quote($kind))->loadAssocList() ?: [];
            foreach ($batch as $row)
            {
                unset($row['entity_id']);
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** @brief Restore only entities the surrounding archive transaction actually restored. */
    public static function restore(DatabaseInterface $db, array $rows, array $clipIds, array $mutableClips, array $collectionIds): void
    {
        foreach ($rows as $data)
        {
            $kind = (string) ($data['entity_type'] ?? '');
            $uuid = (string) ($data['uuid'] ?? '');
            $id = 0;
            if ($kind === 'clip' && isset($mutableClips[$uuid]))
            {
                $id = (int) ($clipIds[$uuid] ?? 0);
            }
            elseif ($kind === 'playlist')
            {
                $id = (int) $db->setQuery('SELECT id FROM #__audioarchive_collections WHERE kind=' . $db->quote('playlist') . ' AND uuid=' . $db->quote($uuid))->loadResult();
                if (!in_array($id, $collectionIds, true)) $id = 0;
            }
            if ($id <= 0) continue;
            $encoded = (string) ($data['image_data'] ?? '');
            if (strlen($encoded) > 4 * (int) ceil(CoverService::MAX_BYTES / 3))
            {
                throw new \RuntimeException('Cover image exceeds the archive size limit.');
            }
            $bytes = base64_decode($encoded, true);
            $image = CoverService::inspect($bytes === false ? '' : $bytes);
            $record = (object) (['entity_type' => $kind, 'entity_id' => $id, 'modified' => gmdate('Y-m-d H:i:s'), 'image_data' => base64_encode($bytes)] + $image);
            $db->setQuery('DELETE FROM #__audioarchive_covers WHERE entity_type=' . $db->quote($kind) . ' AND entity_id=' . $id)->execute();
            $db->insertObject('#__audioarchive_covers', $record);
        }
    }
}
