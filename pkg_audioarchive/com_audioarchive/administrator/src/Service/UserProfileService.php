<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Site\Service\ContributionService;

\defined('_JEXEC') or die;

/** @brief Profile preferences and quota overrides, separate from Joomla's user account fields. */
final class UserProfileService
{
	/** @brief Bind all profile operations to the actual acting identity and application client. */
	public function __construct(private DatabaseInterface $db, private Registry $params, private User $actor, private bool $administrator = false)
	{
	}

	/** @brief Use the target account's current category/access permissions, not the administrator's. */
	private function target(int $id): User
	{
		return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
	}

	/** @brief Permit self-service or authorised administrator editing; never trust a posted owner. */
	public function canEdit(int $id): bool
	{
		if ($id <= 0 || (int) $this->actor->id <= 0) return false;
		if ($id === (int) $this->actor->id) return true;
		return $this->administrator && $this->actor->authorise('core.manage', 'com_users')
			&& $this->actor->authorise('core.edit', 'com_users')
			&& (!$this->target($id)->authorise('core.admin') || $this->actor->authorise('core.admin'));
	}

	/** @brief Quota changes require both administrator user editing and the explicit quota permission. */
	public function canOverride(): bool
	{
		return $this->administrator && (int) $this->actor->id > 0
			&& $this->actor->authorise('core.manage', 'com_users')
			&& $this->actor->authorise('core.edit', 'com_users')
			&& $this->actor->authorise('audioarchive.quota.override', 'com_audioarchive');
	}

	/** @brief Read one accessible account's existing preferences. */
	public function read(int $id): array
	{
		if (!$this->canEdit($id)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		return (array) ($this->db->setQuery('SELECT * FROM ' . $this->db->quoteName('#__audioarchive_user_profiles') . ' WHERE user_id=' . $id)->loadObject() ?: []);
	}

	/** @brief Build permitted profile choices from global contribution policy and collection ownership. */
	public function choices(int $id): array
	{
		if (!$this->canEdit($id)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		$policy = new ContributionService($this->db, $this->target($id));
		$categories = $this->params->get('frontend_upload_enabled', 1) ? $policy->categories($this->params) : [];
		$levels = $policy->accessLevels($this->params);
		$accessIds = array_map(static fn(object $row): int => (int) $row->id, $levels);
		$visibility = ['' => 'PLG_USER_AUDIOARCHIVE_SITE_DEFAULT'];
		if (in_array(1, $accessIds, true)) $visibility['public'] = 'PLG_USER_AUDIOARCHIVE_PUBLIC';
		if (in_array((int) $this->params->get('frontend_registered_access', 2), $accessIds, true)) $visibility['registered'] = 'PLG_USER_AUDIOARCHIVE_REGISTERED';
		if ($this->params->get('allow_private_clips', 0)) $visibility['private'] = 'PLG_USER_AUDIOARCHIVE_PRIVATE';
		if ($levels !== []) $visibility['normal'] = 'PLG_USER_AUDIOARCHIVE_CUSTOM_ACCESS';
		$boards = $this->db->setQuery('SELECT id, title FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE user_id=' . $id . " AND kind='soundboard' ORDER BY title, id")->loadObjectList() ?: [];
		return ['categories' => $categories, 'levels' => $levels, 'visibility' => $visibility, 'boards' => $boards];
	}

	/** @brief Parse a bounded whole number before casting, including malicious form arrays. */
	private static function integer(mixed $value, int $maximum = 2147483647): int
	{
		if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]{1,10}$/D', (string) $value) || (float) $value > $maximum)
		{
			throw new \InvalidArgumentException(Text::_('PLG_USER_AUDIOARCHIVE_INVALID_NUMBER'));
		}
		return (int) $value;
	}

	/** @brief Validate only known fields; stale contribution choices safely fall back to site defaults. */
	public function validate(int $id, array $input): array
	{
		$this->read($id);
		$choices = $this->choices($id);
		$result = [];
		if (array_key_exists('default_visibility', $input))
		{
			$value = is_string($input['default_visibility']) ? $input['default_visibility'] : '';
			$result['default_visibility'] = $choices['categories'] !== [] && array_key_exists($value, $choices['visibility']) ? $value : '';
		}
		foreach (['default_category_id' => 'categories', 'default_access_id' => 'levels', 'default_soundboard_id' => 'boards'] as $field => $list)
		{
			if (!array_key_exists($field, $input)) continue;
			$value = self::integer($input[$field]);
			$allowed = array_map(static fn(object $row): int => (int) $row->id, $choices[$list]);
			if ($field === 'default_soundboard_id' && $value > 0 && !in_array($value, $allowed, true))
			{
				throw new \InvalidArgumentException(Text::_('PLG_USER_AUDIOARCHIVE_INVALID_BOARD'));
			}
			$result[$field] = in_array($value, $allowed, true) ? $value : 0;
		}
		$quotaKeys = ['storage_mode', 'storage_mb', 'clips_mode', 'clips_limit', 'storage_quota_override_bytes', 'clip_quota_override'];
		if (array_intersect(array_keys($input), $quotaKeys) && !$this->canOverride())
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		foreach (['storage' => ['storage_quota_override_bytes', 'storage_mb', 1048576], 'clips' => ['clip_quota_override', 'clips_limit', 1]] as $key => [$column, $valueField, $factor])
		{
			if (!array_key_exists($key . '_mode', $input)) continue;
			$result[$column] = match ($input[$key . '_mode'])
			{
				'inherit' => null,
				'unlimited' => -1,
				'custom' => self::integer($input[$valueField] ?? '') * $factor,
				default => throw new \InvalidArgumentException(Text::_('PLG_USER_AUDIOARCHIVE_INVALID_NUMBER')),
			};
		}
		return $result;
	}

	/** @brief Persist a whitelisted partial update under the same owner lock used by uploads. */
	public function save(int $id, array $input): void
	{
		(new UserQuotaService($this->db, $this->params, $this->actor))->withOwnerLocks([$id], function () use ($id, $input): void
		{
			$data = $this->validate($id, $input);
			$old = $this->read($id);
			if ($data === []) return;
			$this->db->transactionStart();
			try
			{
				$row = (object) ($data + ['user_id' => $id, 'modified' => gmdate('Y-m-d H:i:s')]);
				if ($old !== [])
				{
					$this->db->updateObject('#__audioarchive_user_profiles', $row, 'user_id', true);
				}
				else
				{
					$row->created = $row->modified;
					$this->db->insertObject('#__audioarchive_user_profiles', $row);
				}
				if (isset($data['default_soundboard_id']) && (int) ($old['default_soundboard_id'] ?? 0) !== $data['default_soundboard_id'])
				{
					$revision = $this->db->setQuery('SELECT revision FROM ' . $this->db->quoteName('#__audioarchive_collection_state') . ' WHERE user_id=' . $id)->loadResult();
					$state = (object) ['user_id' => $id, 'revision' => (int) $revision + 1];
					if ($revision !== null) $this->db->updateObject('#__audioarchive_collection_state', $state, 'user_id');
					else $this->db->insertObject('#__audioarchive_collection_state', $state);
				}
				$this->db->transactionCommit();
			}
			catch (\Throwable $error)
			{
				$this->db->transactionRollback();
				throw $error;
			}
		});
	}

	/** @brief Compute read-only counts and effective quota sources for the profile owner. */
	public function summary(int $id): array
	{
		$this->read($id);
		$quota = new UserQuotaService($this->db, $this->params, $this->actor);
		$usage = $quota->getUsage($id);
		$limits = $quota->getEffectiveQuota($id);
		$result = ['owned_clips' => $usage['clips']];
		$result['private_clips'] = (int) $this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->quoteName('#__audioarchive_clips') . ' WHERE created_by=' . $id . " AND visibility_mode='private'")->loadResult();
		$result['storage_usage'] = number_format($usage['storage'] / 1048576, 2) . ' MiB / ' . ($limits['storage'] < 0 ? Text::_('PLG_USER_AUDIOARCHIVE_UNLIMITED') : number_format($limits['storage'] / 1048576, 2) . ' MiB');
		$result['clip_usage'] = $usage['clips'] . ' / ' . ($limits['clips'] < 0 ? Text::_('PLG_USER_AUDIOARCHIVE_UNLIMITED') : $limits['clips']);
		foreach (['playlist', 'soundboard'] as $kind)
		{
			$result[$kind . '_count'] = (int) $this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->quoteName('#__audioarchive_collections') . ' WHERE user_id=' . $id . ' AND kind=' . $this->db->quote($kind))->loadResult();
		}
		$result['quota_source'] = Text::_('PLG_USER_AUDIOARCHIVE_STORAGE') . ': ' . Text::_('PLG_USER_AUDIOARCHIVE_SOURCE_' . strtoupper($limits['storage_source'])) . '; ' . Text::_('PLG_USER_AUDIOARCHIVE_CLIPS') . ': ' . Text::_('PLG_USER_AUDIOARCHIVE_SOURCE_' . strtoupper($limits['clips_source']));
		$result['quota_status'] = Text::_('PLG_USER_AUDIOARCHIVE_' . (($limits['storage'] >= 0 && $usage['storage'] > $limits['storage']) || ($limits['clips'] >= 0 && $usage['clips'] > $limits['clips']) ? 'OVER_QUOTA' : 'WITHIN_QUOTA'));
		return $result;
	}

	/** @brief Preserve archival data after account deletion, but revoke the removed owner's links. */
	public function deleted(int $id): void
	{
		if ($id <= 0) return;
		// The plugin calls this only after Joomla's successful user-deletion event.
		$this->db->setQuery('UPDATE ' . $this->db->quoteName('#__audioarchive_collections') . ' SET share_token=NULL WHERE user_id=' . $id)->execute();
	}
}
