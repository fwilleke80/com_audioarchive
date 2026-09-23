<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Portable collection data uses usernames and clip UUIDs, never local IDs or live share tokens. */
final class CollectionArchiveService
{
	/** @brief Export owned collections for an already-authorized full archive backup. */
	public static function export(DatabaseInterface $db): array
	{
		$rows = $db->setQuery('SELECT c.*, u.username, p.default_soundboard_id FROM ' . $db->quoteName('#__audioarchive_collections') . ' c LEFT JOIN ' . $db->quoteName('#__users') . ' u ON u.id=c.user_id LEFT JOIN ' . $db->quoteName('#__audioarchive_user_profiles') . ' p ON p.user_id=c.user_id ORDER BY c.id')->loadAssocList() ?: [];
		foreach ($rows as &$row)
		{
			$row['items'] = $db->setQuery('SELECT i.position, c.uuid FROM ' . $db->quoteName('#__audioarchive_collection_items') . ' i JOIN ' . $db->quoteName('#__audioarchive_clips') . ' c ON c.id=i.clip_id WHERE i.collection_id=' . (int) $row['id'] . ' ORDER BY i.position')->loadAssocList() ?: [];
			$row['is_default'] = (int) $row['default_soundboard_id'] === (int) $row['id'];
			unset($row['id'], $row['user_id'], $row['share_token'], $row['default_soundboard_id']);
		}
		unset($row);
		return $rows;
	}

	/** @brief Restore within the caller's locked transaction; unresolved identities warn and are skipped. */
	public static function restore(DatabaseInterface $db, array $rows, array $users, array $clips, string $conflict, bool $preferences, array &$result): void
	{
		foreach ($rows as $data)
		{
			$owner = (int) ($users[strtolower((string) ($data['username'] ?? ''))] ?? 0);
			$uuid = (string) ($data['uuid'] ?? '');
			$kind = (string) ($data['kind'] ?? '');
			$title = trim((string) ($data['title'] ?? ''));
			if ($owner <= 0 || !preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $uuid) || !in_array($kind, ['playlist', 'soundboard'], true) || $title === '' || mb_strlen($title) > 120)
			{
				$result['warnings'][] = Text::_('COM_AUDIOARCHIVE_COLLECTION_RESTORE');
				continue;
			}
			$old = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_collections') . ' WHERE uuid=' . $db->quote($uuid))->loadObject();
			if ($old && ((int) $old->user_id !== $owner || $old->kind !== $kind || $conflict === 'skip'))
			{
				$result['warnings'][] = Text::_('COM_AUDIOARCHIVE_COLLECTION_RESTORE');
				continue;
			}
			$row = (object) ['id' => (int) ($old->id ?? 0), 'uuid' => $uuid, 'user_id' => $owner, 'kind' => $kind, 'title' => $title, 'pad_count' => $kind === 'soundboard' ? max(0, min(36, (int) ($data['pad_count'] ?? 0))) : 0, 'share_token' => null, 'created' => gmdate('Y-m-d H:i:s'), 'modified' => gmdate('Y-m-d H:i:s')];
			if ($old)
			{
				$db->updateObject('#__audioarchive_collections', $row, 'id', true);
			}
			else
			{
				$db->insertObject('#__audioarchive_collections', $row, 'id');
			}
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__audioarchive_collection_items') . ' WHERE collection_id=' . (int) $row->id)->execute();
			$seen = [];
			foreach (array_slice((array) ($data['items'] ?? []), 0, $kind === 'soundboard' ? 36 : 2000) as $entry)
			{
				$clipId = (int) ($clips[(string) ($entry['uuid'] ?? '')] ?? 0);
				$position = (int) ($entry['position'] ?? -1);
				if ($clipId <= 0 || $position < 0 || $position >= ($kind === 'soundboard' ? $row->pad_count : 2000) || isset($seen[$position]))
				{
					$result['warnings'][] = Text::_('COM_AUDIOARCHIVE_COLLECTION_RESTORE');
					continue;
				}
				$seen[$position] = true;
				$item = (object) ['collection_id' => (int) $row->id, 'position' => $position, 'clip_id' => $clipId];
				$db->insertObject('#__audioarchive_collection_items', $item);
			}
			if ($preferences && $kind === 'soundboard' && !empty($data['is_default']))
			{
				$profile = (object) ['user_id' => $owner, 'default_soundboard_id' => (int) $row->id, 'created' => gmdate('Y-m-d H:i:s')];
				if ($db->setQuery('SELECT user_id FROM ' . $db->quoteName('#__audioarchive_user_profiles') . ' WHERE user_id=' . $owner)->loadResult())
				{
					$db->updateObject('#__audioarchive_user_profiles', $profile, 'user_id');
				}
				else
				{
					$db->insertObject('#__audioarchive_user_profiles', $profile);
				}
			}
			$state = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_collection_state') . ' WHERE user_id=' . $owner)->loadObject();
			$next = (object) ['user_id' => $owner, 'revision' => (int) ($state->revision ?? 0) + 1];
			if ($state)
			{
				$db->updateObject('#__audioarchive_collection_state', $next, 'user_id');
			}
			else
			{
				$db->insertObject('#__audioarchive_collection_state', $next);
			}
		}
	}
}
