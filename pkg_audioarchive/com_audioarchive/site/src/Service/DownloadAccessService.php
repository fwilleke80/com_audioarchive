<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Authorise original-file downloads for the current visitor.
 */
abstract class DownloadAccessService
{
	/**
	 * @brief Determine whether the current visitor may download originals.
	 *
	 * @param Registry $params Component parameters.
	 * @param User $user Current visitor.
	 *
	 * @return bool True when downloads are enabled and the visitor belongs to
	 * the configured Joomla Viewing Access Level.
	 */
	public static function canDownload(Registry $params, User $user): bool
	{
		if ((int) $params->get('allow_original_downloads', 1) !== 1)
		{
			return false;
		}

		$requiredLevel = max(1, (int) $params->get('download_access_level', 1));
		$authorisedLevels = array_values(array_unique(array_filter(
			array_map('intval', $user->getAuthorisedViewLevels()),
			static fn(int $level): bool => $level > 0
		)));

		return in_array($requiredLevel, $authorisedLevels, true);
	}
}
