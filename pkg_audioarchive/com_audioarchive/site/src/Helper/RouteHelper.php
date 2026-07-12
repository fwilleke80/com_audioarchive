<?php

namespace Willeke\Component\Audioarchive\Site\Helper;

\defined('_JEXEC') or die;

/**
 * @brief Build internal routes for Audio Archive site views.
 */
abstract class RouteHelper
{
	/**
	 * @brief Return the internal route for one public clip.
	 *
	 * @param int $id Clip identifier.
	 * @param int $itemId Optional Archive menu item identifier.
	 *
	 * @return string Internal Joomla route.
	 */
	public static function getClipRoute(int $id, int $itemId = 0): string
	{
		$link = 'index.php?option=com_audioarchive&view=clip&id=' . $id;

		if ($itemId > 0)
		{
			$link .= '&Itemid=' . $itemId;
		}

		return $link;
	}

	/**
	 * @brief Return the protected playback route for one clip.
	 *
	 * @param int $id Clip identifier.
	 * @param int $itemId Optional Archive menu item identifier.
	 *
	 * @return string Internal Joomla route.
	 */
	public static function getPlaybackRoute(int $id, int $itemId = 0): string
	{
		$link = 'index.php?option=com_audioarchive&task=stream.play&id=' . $id . '&format=raw';

		if ($itemId > 0)
		{
			$link .= '&Itemid=' . $itemId;
		}

		return $link;
	}

	/**
	 * @brief Return the protected original-download route for one clip.
	 *
	 * @param int $id Clip identifier.
	 * @param int $itemId Optional Archive menu item identifier.
	 *
	 * @return string Internal Joomla route.
	 */
	public static function getDownloadRoute(int $id, int $itemId = 0): string
	{
		$link = 'index.php?option=com_audioarchive&task=stream.download&id=' . $id . '&format=raw';

		if ($itemId > 0)
		{
			$link .= '&Itemid=' . $itemId;
		}

		return $link;
	}
}
