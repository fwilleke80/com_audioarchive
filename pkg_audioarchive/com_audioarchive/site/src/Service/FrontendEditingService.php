<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\User\User;

\defined('_JEXEC') or die;

/**
 * @brief Resolve frontend clip-editing availability and permissions.
 */
abstract class FrontendEditingService
{
	/**
	 * @brief Return whether Joomla frontend editing is enabled globally.
	 *
	 * @param CMSApplicationInterface $application Current Joomla application.
	 *
	 * @return bool True when frontend editing is enabled.
	 */
	public static function isEnabled(CMSApplicationInterface $application): bool
	{
		return (int) $application->get('frontediting', 0) > 0;
	}

	/**
	 * @brief Check whether a user may edit one existing clip.
	 *
	 * @param User $user Current Joomla user.
	 * @param object $item Clip record containing id and created_by.
	 *
	 * @return bool True when the user may edit the clip.
	 */
	public static function canEdit(User $user, object $item): bool
	{
		if ((int) $user->id <= 0 || (int) ($item->id ?? 0) <= 0)
		{
			return false;
		}

		$asset = 'com_audioarchive.clip.' . (int) $item->id;

		if ($user->authorise('core.edit', $asset))
		{
			return true;
		}

		return (int) ($item->created_by ?? 0) === (int) $user->id
			&& $user->authorise('core.edit.own', $asset);
	}

	/**
	 * @brief Check whether a user may change publication-related fields.
	 *
	 * @param User $user Current Joomla user.
	 * @param object $item Clip record.
	 *
	 * @return bool True when publication state may be edited.
	 */
	public static function canEditState(User $user, object $item): bool
	{
		return (int) ($item->id ?? 0) > 0
			&& $user->authorise('core.edit.state', 'com_audioarchive.clip.' . (int) $item->id);
	}
}
