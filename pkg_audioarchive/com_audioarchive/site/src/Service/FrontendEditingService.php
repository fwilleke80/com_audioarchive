<?php

namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\User\User;

\defined('_JEXEC') or die;

/**
 * @brief Resolve frontend clip-editing availability and permissions.
 */
abstract class FrontendEditingService
{
	/**
	 * @brief Return whether Audio Archive frontend editing is enabled.
	 *
	 * @param CMSApplicationInterface $application Current Joomla application.
	 *
	 * @return bool True when frontend editing is enabled.
	 */
	public static function isEnabled(CMSApplicationInterface $application): bool
	{
		return (bool) \Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive')->get('frontend_editing_enabled', 1);
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
		return (new \Punga\Component\Audioarchive\Administrator\Service\ClipAccessService(
			\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), $user
		))->canEdit($item);
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
