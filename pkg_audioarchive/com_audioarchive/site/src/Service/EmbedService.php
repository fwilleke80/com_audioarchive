<?php

namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
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

	/**
	 * @brief Resolve frame colours and visibility from component settings and hide-only URL choices.
	 * @param array<string, int> $choices Per-embed title/links preferences.
	 * @return array<string, mixed> Safe frame styling and effective visibility.
	 */
	public static function appearance(array $choices = []): array
	{
		$params = ComponentHelper::getParams('com_audioarchive');
		$color = static function (mixed $value, string $fallback): string
		{
			$value = trim((string) $value);
			return preg_match('/^#[0-9a-f]{6}$/iD', $value) === 1 ? $value : $fallback;
		};
		$background = $color($params->get('player_background_color'), '#f8f9fa');
		$text = $color($params->get('player_text_color'), '#212529');
		$link = $color($params->get('archive_list_link_color'), $color($params->get('player_control_color'), '#0d6efd'));
		$link = $color($params->get('embed_link_color'), $link);
		return [
			'background' => $color($params->get('embed_background_color'), $background),
			'text' => $color($params->get('embed_text_color'), $text),
			'link' => $link,
			'title' => $color($params->get('embed_title_color'), $link),
			'link_title' => (int) $params->get('embed_link_title', 1) === 1 && (int) ($choices['link_title'] ?? 1) === 1,
			'show_title' => (int) $params->get('embed_show_title', 1) === 1 && (int) ($choices['title'] ?? 1) === 1,
			'show_original' => (int) $params->get('embed_show_original', 1) === 1 && (int) ($choices['links'] ?? 1) === 1,
			'show_download' => (int) $params->get('embed_show_download', 1) === 1 && (int) ($choices['links'] ?? 1) === 1,
		];
	}

	/** @brief Build an absolute SEF player URL, or an internal media/event task URL. */
	public static function url(string $task, int $id, array $extra = []): string
	{
		if ($task === 'player')
		{
			$app = Factory::getApplication();
			$query = ['option' => 'com_audioarchive', 'view' => 'embed', 'id' => $id];
			$language = $app->getLanguage()->getTag();
			$candidates = [];
			foreach ($app->getMenu()->getItems('component', 'com_audioarchive') ?: [] as $item)
			{
				if (($item->query['view'] ?? '') === 'embed' && (int) $item->access === 1
					&& in_array((string) $item->language, ['*', $language], true))
				{
					$candidates[] = $item;
				}
			}
			usort($candidates, static fn(object $a, object $b): int =>
				[(string) $a->language !== $language, (int) $a->id] <=> [(string) $b->language !== $language, (int) $b->id]);
			if ($candidates !== [])
			{
				$query['Itemid'] = (int) $candidates[0]->id;
			}
			else
			{
				// Do not borrow an unrelated active menu item for an unkeyed embed view.
				$query['Itemid'] = 0;
			}
			// Appearance parameters cannot override the view, clip ID or selected menu item.
			$query += array_intersect_key($extra, array_flip(['presentation', 'title', 'links']));
			return Route::_('index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), false, Route::TLS_IGNORE, true);
		}
		return Uri::root() . 'index.php?'  . http_build_query(array_merge([
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
