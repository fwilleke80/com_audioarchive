<?php

namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** @brief Resolve and enforce original-byte and retained-clip quotas. */
final class UserQuotaService
{
	/** @brief Construct the quota service. */
	public function __construct(private DatabaseInterface $database, private Registry $params, private User $actor)
	{
	}

	/** @brief Resolve per-user, additive group, then site limits; -1 means unlimited. */
	public function getEffectiveQuota(int $userId): array
	{
		$db = $this->database;
		$profile = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__audioarchive_user_profiles'))
			->where('user_id = ' . $userId))->loadObject();
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
		$groups = implode(',', array_map('intval', $user->getAuthorisedGroups())) ?: '0';
		$rules = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__audioarchive_group_quotas'))
			->where('group_id IN (' . $groups . ')'))->loadObjectList() ?: [];
		$result = [];
		foreach (['storage' => ['storage_quota_bytes', 'storage_quota_override_bytes'], 'clips' => ['clip_quota', 'clip_quota_override']] as $dimension => [$column, $override])
		{
			$limit = $dimension === 'storage' ? max(0, (int) $this->params->get('quota_storage_default_mb', 1024)) * 1048576 : max(0, (int) $this->params->get('quota_clips_default', 250));
			if ($this->params->get('quota_' . $dimension . '_mode', 'unlimited') !== 'custom')
			{
				$limit = -1;
			}
			$source = 'default';
			$values = [];
			foreach ($rules as $rule)
			{
				if ($rule->$column !== null)
				{
					$values[] = (int) $rule->$column;
				}
			}
			if ($values !== [])
			{
				$limit = in_array(-1, $values, true) ? -1 : max($values);
				$source = 'group';
			}
			if ($profile !== null && $profile->$override !== null)
			{
				$limit = (int) $profile->$override;
				$source = 'user';
			}
			if ($userId === 0 || !(bool) $this->params->get('quota_' . $dimension . '_enabled', 0))
			{
				$limit = -1;
				$source = 'disabled';
			}
			$result[$dimension] = max(-1, $limit);
			$result[$dimension . '_source'] = $source;
		}
		return $result;
	}

	/** @brief Count every retained clip and original, including trash and unavailable files. */
	public function getUsage(int $userId): array
	{
		$db = $this->database;
		$clips = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__audioarchive_clips'))
			->where('created_by = ' . $userId))->loadResult();
		$bytes = (int) $db->setQuery($db->getQuery(true)->select('COALESCE(SUM(f.file_size), 0)')
			->from($db->quoteName('#__audioarchive_files', 'f'))->innerJoin($db->quoteName('#__audioarchive_clips', 'c') . ' ON c.id = f.clip_id')
			->where('c.created_by = ' . $userId)->where('f.file_role = ' . $db->quote('original')))->loadResult();
		return ['storage' => $bytes, 'clips' => $clips];
	}

	/** @brief Reject increases beyond either quota; reductions always remain possible. */
	public function assertIncrease(int $userId, int $bytes, int $clips, bool $confirmedOverride = false): void
	{
		if ($userId <= 0 || ($bytes <= 0 && $clips <= 0))
		{
			return;
		}
		$limits = $this->getEffectiveQuota($userId);
		$usage = $this->getUsage($userId);
		foreach (['storage' => $bytes, 'clips' => $clips] as $dimension => $increase)
		{
			if ($increase > 0 && $limits[$dimension] >= 0 && $usage[$dimension] + $increase > $limits[$dimension])
			{
				if ($confirmedOverride && $this->actor->authorise('audioarchive.quota.override', 'com_audioarchive'))
				{
					Factory::getApplication()->enqueueMessage(Text::_('COM_AUDIOARCHIVE_QUOTA_OVERRIDE_WARNING'), 'warning');
					continue;
				}
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_QUOTA_EXCEEDED'));
			}
		}
	}

	/** @brief Serialize all mutations for the same owners; callback must commit before returning. */
	public function withOwnerLocks(array $userIds, callable $operation): mixed
	{
		$userIds = array_values(array_unique(array_map('intval', $userIds)));
		sort($userIds, SORT_NUMERIC);
		$locks = [];
		try
		{
			foreach ($userIds as $id)
			{
				if ($id <= 0)
				{
					continue;
				}
				$key = 'aa-quota-' . substr(hash('sha256', $this->database->getPrefix() . ':' . JPATH_ROOT), 0, 24) . '-' . $id;
				if ((int) $this->database->setQuery('SELECT GET_LOCK(' . $this->database->quote($key) . ', 10)')->loadResult() !== 1)
				{
					throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_QUOTA_BUSY'));
				}
				$locks[] = $key;
			}
			return $operation();
		}
		finally
		{
			foreach (array_reverse($locks) as $key)
			{
				$this->database->setQuery('SELECT RELEASE_LOCK(' . $this->database->quote($key) . ')')->loadResult();
			}
		}
	}
}
