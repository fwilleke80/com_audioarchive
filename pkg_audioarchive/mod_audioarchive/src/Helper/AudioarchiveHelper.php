<?php

namespace Willeke\Module\Audioarchive\Site\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Helper\TagDescriptionHelper;
use Willeke\Component\Audioarchive\Site\Service\ArchiveMenuItemResolver;
use Willeke\Component\Audioarchive\Site\Service\DownloadAccessService;

\defined('_JEXEC') or die;

/**
 * @brief Select and prepare public Audio Archive clips for the module.
 */
abstract class AudioarchiveHelper
{
	/**
	 * @brief Return clips selected by the configured module mode.
	 *
	 * @param Registry $params Module parameters.
	 * @param object $module Joomla module record.
	 *
	 * @return object[] Prepared public clip records.
	 */
	public static function getItems(Registry $params, object $module): array
	{
		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$query = self::getEligibleQuery($database, $params);
		$mode = (string) $params->get('selection_mode', 'latest');
		$count = max(1, min(50, (int) $params->get('count', 5)));

		if ($mode === 'specific')
		{
			$specificId = (int) $params->get('specific_clip', 0);
			if ($specificId <= 0)
			{
				return [];
			}
			$query->where($database->quoteName('a.id') . ' = :specificClip')
				->bind(':specificClip', $specificId, ParameterType::INTEGER);
			$count = 1;
		}

		if ($mode === 'latest')
		{
			$dateColumn = match ((string) $params->get('latest_date', 'uploaded'))
			{
				'recorded' => 'a.recorded_at',
				'published' => 'a.publish_up',
				default => 'a.uploaded_at',
			};
			$query->order($database->quoteName($dateColumn) . ' DESC')->order($database->quoteName('a.id') . ' DESC');
		}
		elseif ($mode === 'longest')
		{
			$query->order($database->quoteName('a.duration_ms') . ' DESC')->order($database->quoteName('a.recorded_at') . ' DESC')->order($database->quoteName('a.id') . ' DESC');
		}
		elseif ($mode === 'shortest')
		{
			$query->order($database->quoteName('a.duration_ms') . ' ASC')->order($database->quoteName('a.recorded_at') . ' DESC')->order($database->quoteName('a.id') . ' DESC');
		}
		elseif ($mode === 'most_played')
		{
			$query->order($database->quoteName('a.play_count') . ' DESC')->order($database->quoteName('a.id') . ' DESC');
		}
		elseif ($mode === 'most_downloaded')
		{
			$query->order($database->quoteName('a.download_count') . ' DESC')->order($database->quoteName('a.id') . ' DESC');
		}
		elseif ($mode === 'specific')
		{
			$query->order($database->quoteName('a.id') . ' ASC');
		}

		if (in_array($mode, ['random', 'daily'], true))
		{
			$allItems = (array) $database->setQuery($query)->loadObjectList();
			$items = $mode === 'random'
				? self::selectRandom($allItems, $count)
				: self::selectDaily($allItems, $count, (int) ($module->id ?? 0));
		}
		else
		{
			$items = (array) $database->setQuery($query, 0, $count)->loadObjectList();
		}

		return self::prepareItems(
			$database,
			$items,
			(string) $params->get('player_presentation', 'default') === 'featured'
		);
	}

	/**
	 * @brief Build the common eligibility and restriction query.
	 */
	private static function getEligibleQuery(DatabaseInterface $database, Registry $params)
	{
		$app = Factory::getApplication();
		$levels = array_values(array_unique(array_map('intval', $app->getIdentity()->getAuthorisedViewLevels())));
		$levels = $levels !== [] ? $levels : [1];
		$published = 1;
		$available = 1;
		$original = 'original';
		$extension = 'com_audioarchive';
		$now = Factory::getDate()->toSql();
		$language = $app->getLanguage()->getTag();

		$query = $database->getQuery(true)
			->select([
				$database->quoteName('a.id'), $database->quoteName('a.title'), $database->quoteName('a.alias'),
				$database->quoteName('a.description'), $database->quoteName('a.duration_ms'),
				$database->quoteName('a.recorded_at'), $database->quoteName('a.uploaded_at'),
				$database->quoteName('a.publish_up'), $database->quoteName('a.catid'),
				$database->quoteName('a.language'), $database->quoteName('a.play_count'),
				$database->quoteName('a.download_count'), $database->quoteName('c.title', 'category_title'),
				$database->quoteName('c.lft', 'category_lft'), $database->quoteName('c.rgt', 'category_rgt'),
				$database->quoteName('f.mime_type'), $database->quoteName('f.file_extension'),
			])
			->from($database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin($database->quoteName('#__categories', 'c') . ' ON ' . $database->quoteName('c.id') . ' = ' . $database->quoteName('a.catid'))
			->innerJoin(
				$database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $database->quoteName('f.clip_id') . ' = ' . $database->quoteName('a.id')
				. ' AND ' . $database->quoteName('f.file_role') . ' = :fileRole'
				. ' AND ' . $database->quoteName('f.is_available') . ' = :fileAvailable'
			)
			->where($database->quoteName('a.state') . ' = :clipPublished')
			->where($database->quoteName('c.published') . ' = :categoryPublished')
			->where($database->quoteName('c.extension') . ' = :categoryExtension')
			->whereIn($database->quoteName('a.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('c.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('a.language'), ['*', $language], ParameterType::STRING)
			->extendWhere('AND', [$database->quoteName('a.publish_up') . ' IS NULL', $database->quoteName('a.publish_up') . ' <= :publishNow'], 'OR')
			->extendWhere('AND', [$database->quoteName('a.publish_down') . ' IS NULL', $database->quoteName('a.publish_down') . ' >= :unpublishNow'], 'OR')
			->bind(':fileRole', $original, ParameterType::STRING)
			->bind(':fileAvailable', $available, ParameterType::INTEGER)
			->bind(':clipPublished', $published, ParameterType::INTEGER)
			->bind(':categoryPublished', $published, ParameterType::INTEGER)
			->bind(':categoryExtension', $extension, ParameterType::STRING)
			->bind(':publishNow', $now, ParameterType::STRING)
			->bind(':unpublishNow', $now, ParameterType::STRING);

		$ancestor = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__categories', 'ancestor'))
			->where($database->quoteName('ancestor.extension') . ' = :ancestorExtension')
			->where($database->quoteName('ancestor.lft') . ' < ' . $database->quoteName('c.lft'))
			->where($database->quoteName('ancestor.rgt') . ' > ' . $database->quoteName('c.rgt'))
			->where('(' . $database->quoteName('ancestor.published') . ' <> :ancestorPublished OR ' . $database->quoteName('ancestor.access') . ' NOT IN (' . implode(',', $levels) . '))');
		$query->where('NOT EXISTS (' . $ancestor . ')')
			->bind(':ancestorExtension', $extension, ParameterType::STRING)
			->bind(':ancestorPublished', $published, ParameterType::INTEGER);

		$categories = self::normaliseIds($params->get('categories', []));
		if ($categories !== [])
		{
			$query->whereIn($database->quoteName('a.catid'), $categories, ParameterType::INTEGER);
		}

		$tags = self::normaliseIds($params->get('tags', []));
		if ($tags !== [])
		{
			$typeAlias = 'com_audioarchive.clip';
			if ((string) $params->get('tag_mode', 'all') === 'any')
			{
				$tagQuery = $database->getQuery(true)
					->select('1')->from($database->quoteName('#__contentitem_tag_map', 'tm'))
					->where($database->quoteName('tm.content_item_id') . ' = ' . $database->quoteName('a.id'))
					->where($database->quoteName('tm.type_alias') . ' = :tagType')
					->whereIn($database->quoteName('tm.tag_id'), $tags, ParameterType::INTEGER);
				$query->where('EXISTS (' . $tagQuery . ')')->bind(':tagType', $typeAlias, ParameterType::STRING);
			}
			else
			{
				foreach ($tags as $index => $tagId)
				{
					$placeholder = ':tagId' . $index;
					$typePlaceholder = ':tagType' . $index;
					$tagQuery = $database->getQuery(true)
						->select('1')->from($database->quoteName('#__contentitem_tag_map', 'tm' . $index))
						->where($database->quoteName('tm' . $index . '.content_item_id') . ' = ' . $database->quoteName('a.id'))
						->where($database->quoteName('tm' . $index . '.type_alias') . ' = ' . $typePlaceholder)
						->where($database->quoteName('tm' . $index . '.tag_id') . ' = ' . $placeholder);
					$query->where('EXISTS (' . $tagQuery . ')')
						->bind($placeholder, $tagId, ParameterType::INTEGER)
						->bind($typePlaceholder, $typeAlias, ParameterType::STRING);
				}
			}
		}

		return $query;
	}

	/**
	 * @brief Add routes, tags, download state, and optional waveform routes to selected clips.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param object[] $items Selected clip records.
	 * @param bool $loadWaveforms Whether protected waveform routes are required.
	 *
	 * @return object[] Prepared clip records.
	 */
	private static function prepareItems(
		DatabaseInterface $database,
		array $items,
		bool $loadWaveforms
	): array
	{
		if ($items === [])
		{
			return [];
		}
		$ids = array_map(static fn(object $item): int => (int) $item->id, $items);
		$tagData = (new TagsHelper())->getMultipleItemTags('com_audioarchive.clip', $ids, true);
		$tagData = TagDescriptionHelper::enrichGroups($database, $tagData);
		$resolver = new ArchiveMenuItemResolver($database);
		$app = Factory::getApplication();
		$levels = $app->getIdentity()->getAuthorisedViewLevels();
		$preferredItemId = $app->getInput()->getInt('Itemid', 0);
		$componentParams = ComponentHelper::getParams('com_audioarchive');
		$canDownload = DownloadAccessService::canDownload($componentParams, $app->getIdentity());
		$waveformClipIds = $loadWaveforms
			? self::getAvailableWaveformClipIds($database, $ids)
			: [];

		foreach ($items as $item)
		{
			$item->tags = $tagData[(int) $item->id] ?? [];
			$tagIds = array_map(static fn(object $tag): int => (int) $tag->id, $item->tags);
			$item->itemid = $resolver->resolve((string) $item->language, (int) $item->catid, $tagIds, $preferredItemId, $levels);
			$item->detail_url = Route::_(RouteHelper::getClipRoute((int) $item->id, (int) $item->itemid));
			$item->stream_url = Route::_(RouteHelper::getPlaybackRoute((int) $item->id, (int) $item->itemid));
			$item->waveform_url = isset($waveformClipIds[(int) $item->id])
				? Route::_(RouteHelper::getAnalysisRoute((int) $item->id, 'waveform', (int) $item->itemid))
				: '';
			$item->can_download = $canDownload;
			$item->download_url = $item->can_download
				? Route::_(RouteHelper::getDownloadRoute((int) $item->id, (int) $item->itemid))
				: '';
		}
		return $items;
	}

	/**
	 * @brief Return clip identifiers with an available waveform analysis.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param int[] $clipIds Clip identifiers.
	 *
	 * @return array<int, true> Available waveform identifiers as a lookup map.
	 */
	private static function getAvailableWaveformClipIds(DatabaseInterface $database, array $clipIds): array
	{
		if ($clipIds === [])
		{
			return [];
		}

		$analysisType = 'waveform';
		$status = 'available';
		$available = 1;
		$query = $database->getQuery(true)
			->select($database->quoteName('clip_id'))
			->from($database->quoteName('#__audioarchive_analyses'))
			->whereIn($database->quoteName('clip_id'), $clipIds, ParameterType::INTEGER)
			->where($database->quoteName('analysis_type') . ' = :analysisType')
			->where($database->quoteName('status') . ' = :analysisStatus')
			->where($database->quoteName('is_available') . ' = :analysisAvailable')
			->bind(':analysisType', $analysisType, ParameterType::STRING)
			->bind(':analysisStatus', $status, ParameterType::STRING)
			->bind(':analysisAvailable', $available, ParameterType::INTEGER);
		$ids = array_map('intval', (array) $database->setQuery($query)->loadColumn());

		return array_fill_keys($ids, true);
	}

	/** @return object[] */
	private static function selectRandom(array $items, int $count): array
	{
		shuffle($items);
		return array_slice($items, 0, $count);
	}

	/** @return object[] */
	private static function selectDaily(array $items, int $count, int $moduleId): array
	{
		$date = Factory::getDate('now', Factory::getApplication()->get('offset', 'UTC'))->format('Y-m-d');
		usort($items, static function(object $left, object $right) use ($date, $moduleId): int
		{
			return strcmp(hash('sha256', $date . ':' . $moduleId . ':' . $left->id), hash('sha256', $date . ':' . $moduleId . ':' . $right->id));
		});
		return array_slice($items, 0, $count);
	}

	/** @return int[] */
	private static function normaliseIds(mixed $value): array
	{
		$values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string) $value));
		return array_values(array_unique(array_filter(array_map('intval', $values ?: []), static fn(int $id): bool => $id > 0)));
	}
}
