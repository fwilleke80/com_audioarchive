<?php
namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;
use Punga\Component\Audioarchive\Administrator\Service\UserQuotaService;

\defined('_JEXEC') or die;

/** @brief Personal collections with owner checks, current clip ACL and revision-checked writes. */
final class CollectionService
{
	/** @brief Bind every request to the authenticated identity, never a posted owner. */
	public function __construct(private DatabaseInterface $db, private Registry $params, private User $user)
	{
	}

	/** @brief Resolve component policy and a permitted user preference. */
	public function backend(): string
	{
		if ((int) $this->user->id <= 0)
		{
			return $this->params->get('collections_guest_browser', 1) ? 'browser' : 'disabled';
		}
		$mode = (string) $this->params->get('collections_storage', 'browser');
		if ($mode === 'choice')
		{
			$profile = $this->profile();
			$mode = (string) ($profile->collection_storage_preference ?? '');
			if (!in_array($mode, ['browser', 'server'], true))
			{
				$mode = (string) $this->params->get('collections_default', 'server');
			}
		}
		return $mode === 'server' ? 'server' : 'browser';
	}

	/** @brief Get the caller's own profile only. */
	private function profile(): ?object
	{
		return $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_user_profiles') . ' WHERE user_id=' . (int) $this->user->id)->loadObject();
	}

	/** @brief Deny server persistence unless the signed-in account's policy permits it. */
	private function requireServer(): void
	{
		if ((int) $this->user->id <= 0 || $this->backend() !== 'server')
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/** @brief Read a collection only if it belongs to this identity. */
	private function owned(string $uuid, ?string $kind = null): object
	{
		$row = $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE uuid=' . $this->db->quote($uuid) . ' AND user_id=' . (int) $this->user->id)->loadObject();
		if (!$row || ($kind !== null && $row->kind !== $kind))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_NOT_FOUND'), 404);
		}
		return $row;
	}

	/** @brief Resolve current metadata; never trust stored/client titles or clip IDs on export. */
	private function serialise(object $row, bool $shared = false): array
	{
		$items = $row->kind === 'soundboard' ? array_fill(0, min(36, (int) $row->pad_count), null) : [];
		$refs = $this->db->setQuery('SELECT i.position, c.* FROM ' . $this->db->quoteName('#__audioarchive_collection_items') . ' i JOIN ' . $this->db->quoteName('#__audioarchive_clips') . ' c ON c.id=i.clip_id WHERE i.collection_id=' . (int) $row->id . ' ORDER BY i.position')->loadObjectList() ?: [];
		$policy = new ClipAccessService($this->db, $this->user);
		$unavailable = 0;
		foreach ($refs as $clip)
		{
			$visible = $policy->canView($clip);
			if (!$visible)
			{
				$unavailable++;
			}
			// The owner may retain an opaque UUID to preserve temporarily unavailable entries.
			$entry = $visible ? ['uuid' => $clip->uuid, 'id' => (int) $clip->id, 'title' => $clip->title] : ($shared ? null : ['uuid' => $clip->uuid, 'id' => 0, 'title' => '']);
			if ($row->kind === 'soundboard')
			{
				$items[(int) $clip->position] = $entry;
			}
			elseif ($entry !== null)
			{
				$items[] = $entry;
			}
		}
		return ['id' => $row->uuid, 'name' => $row->title, 'kind' => $row->kind, 'items' => $items,
			'created' => strtotime($row->created . ' UTC') * 1000, 'modified' => strtotime($row->modified . ' UTC') * 1000,
			'shared' => !empty($row->share_token), 'unavailable' => $unavailable];
	}

	/** @brief Load account data; guest/browser modes never receive stored server collections. */
	public function state(): array
	{
		if ($this->backend() !== 'server')
		{
			return $this->readState();
		}
		return (new UserQuotaService($this->db, $this->params, $this->user))->withOwnerLocks([(int) $this->user->id], fn(): array => $this->readState());
	}

	/** @brief Read a coherent account snapshot while holding its owner lock. */
	private function readState(): array
	{
		$backend = $this->backend();
		$result = ['backend' => $backend, 'userId' => (int) $this->user->id, 'revision' => 0, 'playlists' => [], 'boards' => [], 'defaultBoard' => ''];
		if ($backend !== 'server')
		{
			return $result;
		}
		$rows = $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE user_id=' . (int) $this->user->id . ' ORDER BY id')->loadObjectList() ?: [];
		$default = (int) ($this->profile()->default_soundboard_id ?? 0);
		foreach ($rows as $row)
		{
			$result[$row->kind === 'playlist' ? 'playlists' : 'boards'][] = $this->serialise($row);
			if ($row->kind === 'soundboard' && (int) $row->id === $default)
			{
				$result['defaultBoard'] = $row->uuid;
			}
		}
		$result['defaultBoard'] = $result['defaultBoard'] ?: ($result['boards'][0]['id'] ?? '');
		$result['revision'] = (int) $this->db->setQuery('SELECT revision FROM ' . $this->db->quoteName('#__audioarchive_collection_state') . ' WHERE user_id=' . (int) $this->user->id)->loadResult();
		return $result;
	}

	/** @brief Serialize a bounded write under an owner lock and reject stale browser tabs. */
	private function mutate(int $revision, callable $action): array
	{
		$this->requireServer();
		return (new UserQuotaService($this->db, $this->params, $this->user))->withOwnerLocks([(int) $this->user->id], function () use ($revision, $action): array
		{
			$db = $this->db;
			$db->transactionStart();
			try
			{
				$current = $db->setQuery('SELECT revision FROM ' . $db->quoteName('#__audioarchive_collection_state') . ' WHERE user_id=' . (int) $this->user->id)->loadObject();
				if ((int) ($current->revision ?? 0) !== $revision)
				{
					throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_CONFLICT'), 409);
				}
				$extra = $action();
				$state = (object) ['user_id' => (int) $this->user->id, 'revision' => $revision + 1];
				if ($current)
				{
					$db->updateObject('#__audioarchive_collection_state', $state, 'user_id');
				}
				else
				{
					$db->insertObject('#__audioarchive_collection_state', $state);
				}
				$result = ['revision' => $revision + 1, 'state' => $this->readState()] + (array) $extra;
				$db->transactionCommit();
				return $result;
			}
			catch (\Throwable $error)
			{
				$db->transactionRollback();
				throw $error;
			}
		});
	}

	/** @brief Validate metadata and save one owned collection with bounded, ACL-checked membership. */
	private function save(string $kind, array $data): object
	{
		$uuid = (string) ($data['id'] ?? '');
		$title = trim((string) ($data['name'] ?? ''));
		$items = $data['items'] ?? null;
		if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $uuid) || $title === '' || mb_strlen($title) > 120 || !is_array($items) || !array_is_list($items) || count($items) > ($kind === 'playlist' ? 2000 : 36))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_INVALID'), 400);
		}
		$db = $this->db;
		$existing = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_collections') . ' WHERE uuid=' . $db->quote($uuid))->loadObject();
		if ($existing && ((int) $existing->user_id !== (int) $this->user->id || $existing->kind !== $kind))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_NOT_FOUND'), 404);
		}
		$count = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__audioarchive_collections') . ' WHERE user_id=' . (int) $this->user->id . ' AND kind=' . $db->quote($kind))->loadResult();
		if (!$existing && $count >= 100)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_LIMIT'), 400);
		}
		$retained = $existing ? $db->setQuery('SELECT clip_id FROM ' . $db->quoteName('#__audioarchive_collection_items') . ' WHERE collection_id=' . (int) $existing->id)->loadColumn() : [];
		$retained = array_map('intval', $retained ?: []);
		$policy = new ClipAccessService($db, $this->user);
		$resolved = [];
		foreach ($items as $position => $item)
		{
			if ($item === null && $kind === 'soundboard')
			{
				continue;
			}
			if (!is_array($item))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_INVALID'), 400);
			}
			$clipUuid = (string) ($item['uuid'] ?? '');
			// Numeric IDs are accepted only for legacy board import; never exported as identity.
			$where = $clipUuid !== '' ? 'uuid=' . $db->quote($clipUuid) : 'id=' . max(0, (int) ($item['id'] ?? 0));
			$clip = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_clips') . ' WHERE ' . $where)->loadObject();
			if (!$clip || (!$policy->canView($clip) && !in_array((int) $clip->id, $retained, true)))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_UNAVAILABLE'), 400);
			}
			$resolved[(int) $position] = (int) $clip->id;
		}
		$row = (object) ['id' => (int) ($existing->id ?? 0), 'uuid' => $uuid, 'user_id' => (int) $this->user->id, 'kind' => $kind, 'title' => $title, 'pad_count' => $kind === 'soundboard' ? count($items) : 0, 'modified' => gmdate('Y-m-d H:i:s')];
		if ($existing)
		{
			$db->updateObject('#__audioarchive_collections', $row, 'id');
		}
		else
		{
			$row->created = $row->modified;
			$db->insertObject('#__audioarchive_collections', $row, 'id');
		}
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__audioarchive_collection_items') . ' WHERE collection_id=' . (int) $row->id)->execute();
		foreach ($resolved as $position => $clipId)
		{
			$entry = (object) ['collection_id' => (int) $row->id, 'position' => $position, 'clip_id' => $clipId];
			$db->insertObject('#__audioarchive_collection_items', $entry);
		}
		return $row;
	}

	/** @brief Atomically persist the typed playlist store, preserving order and excluding other owners. */
	public function playlists(array $playlists, int $revision): array
	{
		if (!array_is_list($playlists) || count($playlists) > 100)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_LIMIT'), 400);
		}
		return $this->mutate($revision, function () use ($playlists): array
		{
			$ids = [];
			foreach ($playlists as $playlist)
			{
				if (!is_array($playlist) || in_array($playlist['id'] ?? '', $ids, true))
				{
					throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_INVALID'), 400);
				}
				$ids[] = $this->save('playlist', $playlist)->uuid;
			}
			$rows = $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE user_id=' . (int) $this->user->id . " AND kind='playlist'")->loadObjectList() ?: [];
			foreach ($rows as $row)
			{
				if (!in_array($row->uuid, $ids, true))
				{
					$this->remove($row);
				}
			}
			return [];
		});
	}

	/** @brief Create or replace a named board without affecting any other board. */
	public function board(array $board, int $revision): array
	{
		return $this->mutate($revision, function () use ($board): array
		{
			$row = $this->save('soundboard', $board);
			return ['id' => $row->uuid];
		});
	}

	/** @brief Delete dependent membership and then its owned collection. */
	private function remove(object $row): void
	{
		$this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__audioarchive_collection_items') . ' WHERE collection_id=' . (int) $row->id)->execute();
		$this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE id=' . (int) $row->id . ' AND user_id=' . (int) $this->user->id)->execute();
	}

	/** @brief Permanently remove one owned board and its sharing token. */
	public function deleteBoard(string $id, int $revision): array
	{
		return $this->mutate($revision, function () use ($id): array
		{
			$this->remove($this->owned($id, 'soundboard'));
			return [];
		});
	}

	/** @brief Set the account's preferred board after verifying ownership. */
	public function defaultBoard(string $id, int $revision): array
	{
		return $this->mutate($revision, function () use ($id): array
		{
			$row = $this->owned($id, 'soundboard');
			$profile = (object) ['user_id' => (int) $this->user->id, 'default_soundboard_id' => (int) $row->id, 'modified' => gmdate('Y-m-d H:i:s')];
			if ($this->profile())
			{
				$this->db->updateObject('#__audioarchive_user_profiles', $profile, 'user_id');
			}
			else
			{
				$profile->created = $profile->modified;
				$this->db->insertObject('#__audioarchive_user_profiles', $profile);
			}
			return [];
		});
	}

	/** @brief Explicitly import a new browser collection, reporting inaccessible entries without copying them. */
	public function importCollection(string $kind, array $data, int $revision): array
	{
		return $this->mutate($revision, function () use ($kind, $data): array
		{
			$items = $data['items'] ?? [];
			if (!in_array($kind, ['playlist', 'soundboard'], true) || !is_array($items) || count($items) > ($kind === 'playlist' ? 2000 : 36))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_INVALID'), 400);
			}
			$clean = [];
			$skipped = 0;
			$policy = new ClipAccessService($this->db, $this->user);
			foreach (array_values($items) as $item)
			{
				$clip = null;
				if (is_array($item))
				{
					$where = !empty($item['uuid']) ? 'uuid=' . $this->db->quote((string) $item['uuid']) : 'id=' . (int) ($item['id'] ?? 0);
					$clip = $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_clips') . ' WHERE ' . $where)->loadObject();
				}
				if ($clip && $policy->canView($clip))
				{
					$clean[] = ['uuid' => $clip->uuid];
				}
				else
				{
					$skipped += $item !== null ? 1 : 0;
					if ($kind === 'soundboard')
					{
						$clean[] = null;
					}
				}
			}
			$row = $this->save($kind, ['id' => bin2hex(random_bytes(16)), 'name' => $data['name'] ?? 'Imported', 'items' => $clean]);
			return ['id' => $row->uuid, 'skipped' => $skipped];
		});
	}

	/** @brief Rotate or revoke an unguessable share token; links never confer clip permissions. */
	public function share(string $id, bool $revoke, int $revision): array
	{
		return $this->mutate($revision, function () use ($id, $revoke): array
		{
			if (!$this->params->get('collections_sharing', 1))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
			$row = $this->owned($id);
			$token = $revoke ? '' : ((string) ($row->share_token ?? '') ?: bin2hex(random_bytes(24)));
			$this->db->setQuery('UPDATE ' . $this->db->quoteName('#__audioarchive_collections') . ' SET share_token=' . ($revoke ? 'NULL' : $this->db->quote($token)) . ' WHERE id=' . (int) $row->id)->execute();
			return ['token' => $token, 'kind' => $row->kind];
		});
	}

	/** @brief Resolve a link for this visitor, omitting all inaccessible clip identities and metadata. */
	public function shared(string $token): array
	{
		if (!$this->params->get('collections_sharing', 1) || !preg_match('/^[a-f0-9]{48}$/D', $token))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_NOT_FOUND'), 404);
		}
		$row = $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE share_token=' . $this->db->quote($token))->loadObject();
		if (!$row)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_NOT_FOUND'), 404);
		}
		return $this->serialise($row, true);
	}
}
