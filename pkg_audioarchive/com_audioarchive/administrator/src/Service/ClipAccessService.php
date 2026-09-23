<?php

namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Shared clip visibility policy for discovery and direct access. */
final class ClipAccessService
{
	/** @brief Construct a policy for one visitor. */
	public function __construct(private DatabaseInterface $database, private User $user)
	{
	}

	/** @brief Check the additional privacy restriction without granting other ACL actions. */
	public function canAccessPrivate(object $clip): bool
	{
		if (($clip->visibility_mode ?? 'normal') === 'normal')
		{
			return true;
		}
		return (int) $this->user->id > 0 && (
			(int) $clip->created_by === (int) $this->user->id
			|| $this->user->authorise('audioarchive.manage.private', 'com_audioarchive.clip.' . (int) $clip->id)
		);
	}

	/** @brief Check editing independently of ownership and privacy. */
	public function canEdit(object $clip): bool
	{
		$asset = 'com_audioarchive.clip.' . (int) $clip->id;
		return (int) $this->user->id > 0 && $this->canAccessPrivate($clip)
			&& ($this->user->authorise('core.edit', $asset)
				|| ((int) $clip->created_by === (int) $this->user->id && $this->user->authorise('core.edit.own', $asset)));
	}

	/** @brief Require standard ACL in addition to private-content access. */
	public function canEditState(object $clip): bool
	{
		return $this->canAccessPrivate($clip) && $this->user->authorise('core.edit.state', 'com_audioarchive.clip.' . (int) $clip->id);
	}

	/** @brief Require standard delete ACL in addition to private-content access. */
	public function canDelete(object $clip): bool
	{
		return $this->canAccessPrivate($clip) && $this->user->authorise('core.delete', 'com_audioarchive.clip.' . (int) $clip->id);
	}

	/** @brief Apply complete publication, access, category ancestry and discovery privacy rules. */
	public function applyPublicVisibilityFilter(object $query, string $alias = 'a'): void
	{
		$db = $this->database;
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $alias))
		{
			throw new \InvalidArgumentException('Invalid clip alias.');
		}
		$levels = implode(',', array_map('intval', $this->user->getAuthorisedViewLevels())) ?: '0';
		$now = $db->quote(gmdate('Y-m-d H:i:s'));
		$q = static fn(string $name): string => $db->quoteName($alias . '.' . $name);
		$query->where($q('visibility_mode') . ' = ' . $db->quote('normal'))
			->where($q('state') . ' = 1')
			->where($q('access') . ' IN (' . $levels . ')')
			->where('(' . $q('publish_up') . ' IS NULL OR ' . $q('publish_up') . ' <= ' . $now . ')')
			->where('(' . $q('publish_down') . ' IS NULL OR ' . $q('publish_down') . ' >= ' . $now . ')');
		$category = $db->getQuery(true)->select('1')->from($db->quoteName('#__categories', 'visibilityCategory'))
			->where('visibilityCategory.id = ' . $q('catid'))
			->where('visibilityCategory.extension = ' . $db->quote('com_audioarchive'))
			->where('visibilityCategory.published = 1')->where('visibilityCategory.access IN (' . $levels . ')')
			->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories') . ' AS visibilityAncestor'
				. ' WHERE visibilityAncestor.extension = ' . $db->quote('com_audioarchive')
				. ' AND visibilityAncestor.lft < visibilityCategory.lft AND visibilityAncestor.rgt > visibilityCategory.rgt'
				. ' AND (visibilityAncestor.published <> 1 OR visibilityAncestor.access NOT IN (' . $levels . ')))');
		$query->where('EXISTS (' . $category . ')');
	}

	/** @brief Filter private records without altering publication rules in administrative workspaces. */
	public function applyPrivacyFilter(object $query, string $alias = 'a'): void
	{
		$db = $this->database;
		$categories = $db->setQuery($db->getQuery(true)->select('id')->from($db->quoteName('#__categories'))->where('extension = ' . $db->quote('com_audioarchive')))->loadColumn();
		$allowed = [];
		foreach ($categories as $categoryId)
		{
			if ($this->user->authorise('audioarchive.manage.private', 'com_audioarchive.category.' . (int) $categoryId))
			{
				$allowed[] = (int) $categoryId;
			}
		}
		$condition = $db->quoteName($alias . '.visibility_mode') . ' = ' . $db->quote('normal');
		if ((int) $this->user->id > 0)
		{
			$condition .= ' OR ' . $db->quoteName($alias . '.created_by') . ' = ' . (int) $this->user->id;
		}
		if ($allowed !== [])
		{
			$condition .= ' OR ' . $db->quoteName($alias . '.catid') . ' IN (' . implode(',', $allowed) . ')';
		}
		$query->where('(' . $condition . ')');
	}

	/** @brief Check direct access; editor preview never grants discovery visibility. */
	public function canView(object $clip): bool
	{
		if (!$this->canAccessPrivate($clip) || (int) $clip->state === -2)
		{
			return false;
		}
		if ($this->canEdit($clip))
		{
			return true;
		}
		// Reuse public eligibility on a projected normal visibility value: privacy was checked above.
		$db = $this->database;
		$fields = ['id', 'catid', 'state', 'access', 'publish_up', 'publish_down'];
		$select = array_map(static fn(string $field): string => $db->quoteName($field), $fields);
		$select[] = $db->quote('normal') . ' AS visibility_mode';
		$inner = $db->getQuery(true)->select($select)->from($db->quoteName('#__audioarchive_clips'))
			->where('id = ' . (int) $clip->id);
		$query = $db->getQuery(true)->select('a.id')->from('(' . $inner . ') AS a');
		$this->applyPublicVisibilityFilter($query);
		return (bool) $db->setQuery($query)->loadResult();
	}
}
