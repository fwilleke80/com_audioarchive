<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** @brief Preserve private performance data in full backups using usernames and clip UUIDs. */
final class RecordingArchiveService
{
	/** @brief Export all account snapshots without local owner or clip identifiers. */
	public static function export(DatabaseInterface $db): array
	{
		$rows = $db->setQuery('SELECT r.payload, u.username FROM ' . $db->quoteName('#__audioarchive_recordings') . ' r LEFT JOIN ' . $db->quoteName('#__users') . ' u ON u.id=r.user_id')->loadAssocList() ?: [];
		foreach ($rows as &$row)
		{
			$row['recordings'] = json_decode($row['payload'], true) ?: [];
			foreach ($row['recordings'] as &$recording)
			{
				$boards = ['board' => $recording['board'] ?? []] + ($recording['layerBoards'] ?? []);
				foreach ($boards as &$board)
				{
					foreach ($board as &$clip)
					{
						if (!is_array($clip)) continue;
						$clip['uuid'] = $clip['uuid'] ?? (string) $db->setQuery('SELECT uuid FROM ' . $db->quoteName('#__audioarchive_clips') . ' WHERE id=' . (int) ($clip['id'] ?? 0))->loadResult();
						unset($clip['id']);
					}
					unset($clip);
				}
				unset($board);
				$recording['board'] = $boards['board'];
				unset($boards['board']);
				$recording['layerBoards'] = $boards;
			}
			unset($recording, $row['payload']);
		}
		unset($row);
		return $rows;
	}

	/** @brief Restore matched accounts inside the existing archive transaction, retaining unresolved owners in the backup. */
	public static function restore(DatabaseInterface $db, array $rows, array $users, array $clips, string $conflict, array &$result): void
	{
		foreach ($rows as $row)
		{
			$owner = (int) ($users[strtolower((string) ($row['username'] ?? ''))] ?? 0);
			$recordings = $row['recordings'] ?? null;
			$existing = $owner > 0 ? $db->setQuery('SELECT payload FROM ' . $db->quoteName('#__audioarchive_recordings') . ' WHERE user_id=' . $owner)->loadResult() : null;
			if ($owner <= 0 || !is_array($recordings) || count($recordings) > 100 || ($existing && $conflict === 'skip'))
			{
				$result['warnings'][] = Text::_('COM_AUDIOARCHIVE_COLLECTION_RESTORE');
				continue;
			}
			foreach ($recordings as &$recording)
			{
				$boards = ['board' => $recording['board'] ?? []] + ($recording['layerBoards'] ?? []);
				foreach ($boards as &$board)
				{
					foreach ($board as &$clip)
					{
						if (!is_array($clip)) continue;
						$clip['id'] = (int) ($clips[$clip['uuid'] ?? ''] ?? 0);
					}
					unset($clip);
				}
				unset($board);
				$recording['board'] = $boards['board'];
				unset($boards['board']);
				$recording['layerBoards'] = $boards;
			}
			unset($recording);
			$payload = json_encode($recordings, JSON_THROW_ON_ERROR);
			if (strlen($payload) > 4 * 1024 * 1024)
			{
				$result['warnings'][] = Text::_('COM_AUDIOARCHIVE_COLLECTION_RESTORE');
				continue;
			}
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__audioarchive_recordings') . ' WHERE user_id=' . $owner)->execute();
			$db->insertObject('#__audioarchive_recordings', (object) ['user_id' => $owner, 'payload' => $payload]);
			$revision = $db->setQuery('SELECT revision FROM ' . $db->quoteName('#__audioarchive_collection_state') . ' WHERE user_id=' . $owner)->loadObject();
			$state = (object) ['user_id' => $owner, 'revision' => (int) ($revision->revision ?? 0) + 1];
			if ($revision)
			{
				$db->updateObject('#__audioarchive_collection_state', $state, 'user_id');
			}
			else
			{
				$db->insertObject('#__audioarchive_collection_state', $state);
			}
		}
	}
}
