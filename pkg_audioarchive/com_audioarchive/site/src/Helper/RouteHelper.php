<?php

namespace Willeke\Component\Audioarchive\Site\Helper;

\defined('_JEXEC') or die;

/**
 * @brief Build internal routes for Audio Archive site views.
 */
abstract class RouteHelper
{

	/**
	 * @brief Return the internal route for the public archive.
	 *
	 * @param int $itemId Optional Archive menu item identifier.
	 * @param array<string, mixed> $query Public filter and list query values.
	 *
	 * @return string Internal Joomla route.
	 */
	public static function getArchiveRoute(int $itemId = 0, array $query = []): string
	{
		$link = 'index.php?option=com_audioarchive&view=archive';

		if ($itemId > 0)
		{
			$link .= '&Itemid=' . $itemId;
		}

		if ($query !== [])
		{
			$encodedQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
			$encodedQuery = str_replace(['%2C', '%3A'], [',', ':'], $encodedQuery);
			$link .= '&' . $encodedQuery;
		}

		return $link;
	}

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
	 * @brief Return the endpoint used to record an actual playback start.
	 *
	 * @param int $itemId Optional Archive menu item identifier.
	 *
	 * @return string Internal Joomla route.
	 */
	public static function getPlayCountRoute(int $itemId = 0): string
	{
		$link = 'index.php?option=com_audioarchive&task=stream.countPlay&format=json';

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
