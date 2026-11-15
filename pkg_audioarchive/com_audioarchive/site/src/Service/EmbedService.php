<?php

namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;

\defined('_JEXEC') or die;

/** @brief Public-only embed permissions and cookie-independent event signatures. */
final class EmbedService
{
	/** @var array<int, bool>|null Request-local eligibility, shared by all Share menus. */
	private static ?array $eligible = null;

	/** @brief Allow the four single-clip player presentations. @return string Safe presentation. */
	public static function presentation(string $value): string
	{
		return in_array($value, ['minimal', 'compact', 'default', 'featured'], true) ? $value : 'default';
	}

	/** @brief Build a stable, absolute task URL independent of active menu items and SEF. */
	public static function url(string $task, int $id, array $extra = []): string
	{
		return Uri::root() . 'index.php?' . http_build_query(array_merge([
			'option' => 'com_audioarchive', 'task' => 'embed.' . $task, 'format' => 'raw', 'id' => $id,
		], $extra), '', '&', PHP_QUERY_RFC3986);
	}

	/** @brief Test public access without using the current visitor's login or editor privileges. */
	public static function isAllowed(int $id): bool
	{
		if ((int) ComponentHelper::getParams('com_audioarchive')->get('enable_external_embeds', 0) !== 1
			|| (int) ComponentHelper::getParams('com_audioarchive')->get('frontend_access_level', 1) !== 1 || $id <= 0)
		{
			return false;
		}
		if (self::$eligible === null)
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)->select('a.id')->from($db->quoteName('#__audioarchive_clips', 'a'))
				->where('a.allow_embed = 1')->where('a.access = 1');
			(new ClipAccessService($db, new User()))->applyPublicVisibilityFilter($query);
			// Guest-only categories are not public, even though a guest can view them.
			$query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories') . ' AS ec'
				. ' WHERE ec.id = a.catid AND ec.access = 1 AND NOT EXISTS (SELECT 1 FROM '
				. $db->quoteName('#__categories') . ' AS ea WHERE ea.extension = ' . $db->quote('com_audioarchive')
				. ' AND ea.lft < ec.lft AND ea.rgt > ec.rgt AND ea.access <> 1))')
				->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__audioarchive_files')
				. ' AS ef WHERE ef.clip_id = a.id AND ef.file_role = ' . $db->quote('original') . ' AND ef.is_available = 1)');
			self::$eligible = array_fill_keys(array_map('intval', $db->setQuery($query)->loadColumn()), true);
		}
		return isset(self::$eligible[$id]);
	}

	/** @brief Load only an embeddable public clip. @return object|null Public clip record. */
	public static function clip(int $id): ?object
	{
		if (!self::isAllowed($id))
		{
			return null;
		}
		return self::media()->getPublicClip($id, false);
	}

	/** @brief Construct a media service that never gains signed-in user privileges. */
	public static function media(): PublicMediaService
	{
		return new PublicMediaService(Factory::getContainer()->get(DatabaseInterface::class),
			ComponentHelper::getParams('com_audioarchive'), new User());
	}

	/** @brief Sign a short-lived event token usable when third-party cookies are blocked. */
	public static function token(int $id, string $purpose, ?int $expires = null): string
	{
		$expires ??= time() + 43200;
		$secret = (string) Factory::getApplication()->get('secret');
		return $expires . '.' . hash_hmac('sha256', 'audioarchive.embed:' . $purpose . ':' . $id . ':' . $expires, $secret);
	}

	/** @brief Validate event purpose, clip binding, expiry and signature. */
	public static function validToken(string $token, int $id, string $purpose): bool
	{
		if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/D', $token, $match))
		{
			return false;
		}
		$expires = (int) $match[1];
		return $expires >= time() && $expires <= time() + 43200
			&& hash_equals(self::token($id, $purpose, $expires), $token);
	}
}
