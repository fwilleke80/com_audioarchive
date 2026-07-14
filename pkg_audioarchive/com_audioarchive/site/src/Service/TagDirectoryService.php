<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;

\defined('_JEXEC') or die;

/**
 * @brief Build the public Audio Archive tag directory.
 */
class TagDirectoryService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/**
	 * @brief Construct the tag-directory service.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(DatabaseInterface $database)
	{
		$this->database = $database;
	}

	/**
	 * @brief Load and prepare tags for a component view or module.
	 *
	 * The returned tag counts use the same public eligibility rules as the
	 * Archive. When a target Archive menu item restricts category or tags, those
	 * restrictions are also applied to the directory and its counts.
	 *
	 * @param Registry $params Directory configuration.
	 * @param int $preferredItemId Preferred current menu item.
	 *
	 * @return object Object containing items and archive_item_id.
	 */
	public function getDirectory(Registry $params, int $preferredItemId = 0): object
	{
		$app = Factory::getApplication();
		$levels = array_values(array_unique(array_map(
			'intval',
			$app->getIdentity()->getAuthorisedViewLevels()
		)));
		$levels = $levels !== [] ? $levels : [1];
		$language = trim((string) $app->getLanguage()->getTag());
		$language = $language !== '' ? $language : '*';
		$configuredItemId = (int) $params->get('tag_directory_archive_itemid', 0);
		$target = $this->resolveArchiveTarget(
			$configuredItemId,
			$preferredItemId,
			$language,
			$levels
		);
		$selectedTagIds = $this->normaliseIds($params->get('tag_directory_tags', []));
		$items = $this->loadTags($selectedTagIds, $language, $levels);
		$tagIds = array_map(static fn(object $item): int => (int) $item->id, $items);
		$counts = $this->loadCounts($tagIds, $language, $levels, $target->params);

		foreach ($items as $item)
		{
			$item->clip_count = (int) ($counts[(int) $item->id] ?? 0);
			$item->url = Route::_(RouteHelper::getArchiveRoute(
				(int) $target->id,
				['tags' => (string) $item->alias]
			));
		}

		if ((int) $params->get('tag_directory_hide_empty', 1) === 1)
		{
			$items = array_values(array_filter(
				$items,
				static fn(object $item): bool => (int) $item->clip_count > 0
			));
		}

		$this->sortItems(
			$items,
			(string) $params->get('tag_directory_ordering', 'title'),
			$selectedTagIds
		);

		return (object) [
			'items' => $items,
			'archive_item_id' => (int) $target->id,
		];
	}

	/**
	 * @brief Load visible published Joomla tags.
	 *
	 * @param int[] $selectedTagIds Optional selected identifiers.
	 * @param string $language Current site language.
	 * @param int[] $levels Authorised view levels.
	 *
	 * @return object[] Tag records.
	 */
	private function loadTags(array $selectedTagIds, string $language, array $levels): array
	{
		$database = $this->database;
		$published = 1;
		$rootTagId = 1;
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('t.id'),
				$database->quoteName('t.title'),
				$database->quoteName('t.alias'),
				$database->quoteName('t.description'),
				$database->quoteName('t.path'),
				$database->quoteName('t.language'),
				$database->quoteName('t.lft'),
				$database->quoteName('t.rgt'),
			])
			->from($database->quoteName('#__tags', 't'))
			->where($database->quoteName('t.id') . ' > :tagRootId')
			->where($database->quoteName('t.published') . ' = :tagPublished')
			->where($database->quoteName('t.alias') . " <> ''")
			->whereIn($database->quoteName('t.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('t.language'), ['*', $language], ParameterType::STRING)
			->bind(':tagRootId', $rootTagId, ParameterType::INTEGER)
			->bind(':tagPublished', $published, ParameterType::INTEGER);

		$ancestor = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__tags', 'tagAncestor'))
			->where($database->quoteName('tagAncestor.lft') . ' < ' . $database->quoteName('t.lft'))
			->where($database->quoteName('tagAncestor.rgt') . ' > ' . $database->quoteName('t.rgt'))
			->where($database->quoteName('tagAncestor.id') . ' > :tagAncestorRoot')
			->where(
				'(' . $database->quoteName('tagAncestor.published') . ' <> :tagAncestorPublished'
				. ' OR ' . $database->quoteName('tagAncestor.access')
				. ' NOT IN (' . implode(',', $levels) . '))'
			);
		$query
			->where('NOT EXISTS (' . $ancestor . ')')
			->bind(':tagAncestorRoot', $rootTagId, ParameterType::INTEGER)
			->bind(':tagAncestorPublished', $published, ParameterType::INTEGER);

		if ($selectedTagIds !== [])
		{
			$query->whereIn($database->quoteName('t.id'), $selectedTagIds, ParameterType::INTEGER);
		}
		else
		{
			$tagMapTypeAlias = 'com_audioarchive.clip';
			$tagMap = $database->getQuery(true)
				->select('1')
				->from($database->quoteName('#__contentitem_tag_map', 'directoryTagMap'))
				->where(
					$database->quoteName('directoryTagMap.tag_id')
					. ' = ' . $database->quoteName('t.id')
				)
				->where($database->quoteName('directoryTagMap.type_alias') . ' = :directoryTagMapType');
			$query
				->where('EXISTS (' . $tagMap . ')')
				->bind(':directoryTagMapType', $tagMapTypeAlias, ParameterType::STRING);
		}

		$query
			->order($database->quoteName('t.title') . ' ASC')
			->order($database->quoteName('t.id') . ' ASC');

		return (array) $database->setQuery($query)->loadObjectList();
	}

	/**
	 * @brief Count publicly eligible clips for each directory tag.
	 *
	 * @param int[] $tagIds Tag identifiers to count.
	 * @param string $language Current site language.
	 * @param int[] $levels Authorised view levels.
	 * @param Registry $archiveParams Target Archive menu parameters.
	 *
	 * @return array<int, int> Counts keyed by tag identifier.
	 */
	private function loadCounts(
		array $tagIds,
		string $language,
		array $levels,
		Registry $archiveParams
	): array
	{
		if ($tagIds === [])
		{
			return [];
		}

		$database = $this->database;
		$published = 1;
		$available = 1;
		$original = 'original';
		$typeAlias = 'com_audioarchive.clip';
		$extension = 'com_audioarchive';
		$now = Factory::getDate()->toSql();
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('tm.tag_id'),
				'COUNT(DISTINCT ' . $database->quoteName('a.id') . ') AS ' . $database->quoteName('clip_count'),
			])
			->from($database->quoteName('#__contentitem_tag_map', 'tm'))
			->innerJoin(
				$database->quoteName('#__audioarchive_clips', 'a')
				. ' ON ' . $database->quoteName('a.id') . ' = ' . $database->quoteName('tm.content_item_id')
			)
			->innerJoin(
				$database->quoteName('#__categories', 'c')
				. ' ON ' . $database->quoteName('c.id') . ' = ' . $database->quoteName('a.catid')
			)
			->innerJoin(
				$database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $database->quoteName('f.clip_id') . ' = ' . $database->quoteName('a.id')
				. ' AND ' . $database->quoteName('f.file_role') . ' = :directoryFileRole'
				. ' AND ' . $database->quoteName('f.is_available') . ' = :directoryFileAvailable'
			)
			->where($database->quoteName('tm.type_alias') . ' = :directoryTypeAlias')
			->whereIn($database->quoteName('tm.tag_id'), $tagIds, ParameterType::INTEGER)
			->where($database->quoteName('a.state') . ' = :directoryClipPublished')
			->where($database->quoteName('c.published') . ' = :directoryCategoryPublished')
			->where($database->quoteName('c.extension') . ' = :directoryCategoryExtension')
			->whereIn($database->quoteName('a.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('c.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('a.language'), ['*', $language], ParameterType::STRING)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_up') . ' IS NULL',
					$database->quoteName('a.publish_up') . ' <= :directoryPublishNow',
				],
				'OR'
			)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_down') . ' IS NULL',
					$database->quoteName('a.publish_down') . ' >= :directoryUnpublishNow',
				],
				'OR'
			)
			->bind(':directoryFileRole', $original, ParameterType::STRING)
			->bind(':directoryFileAvailable', $available, ParameterType::INTEGER)
			->bind(':directoryTypeAlias', $typeAlias, ParameterType::STRING)
			->bind(':directoryClipPublished', $published, ParameterType::INTEGER)
			->bind(':directoryCategoryPublished', $published, ParameterType::INTEGER)
			->bind(':directoryCategoryExtension', $extension, ParameterType::STRING)
			->bind(':directoryPublishNow', $now, ParameterType::STRING)
			->bind(':directoryUnpublishNow', $now, ParameterType::STRING);

		$ancestor = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__categories', 'directoryAncestor'))
			->where($database->quoteName('directoryAncestor.extension') . ' = :directoryAncestorExtension')
			->where($database->quoteName('directoryAncestor.lft') . ' < ' . $database->quoteName('c.lft'))
			->where($database->quoteName('directoryAncestor.rgt') . ' > ' . $database->quoteName('c.rgt'))
			->where(
				'(' . $database->quoteName('directoryAncestor.published') . ' <> :directoryAncestorPublished'
				. ' OR ' . $database->quoteName('directoryAncestor.access')
				. ' NOT IN (' . implode(',', $levels) . '))'
			);
		$query
			->where('NOT EXISTS (' . $ancestor . ')')
			->bind(':directoryAncestorExtension', $extension, ParameterType::STRING)
			->bind(':directoryAncestorPublished', $published, ParameterType::INTEGER);

		$categoryRestriction = (int) $archiveParams->get('archive_category_restriction', 0);

		if ($categoryRestriction > 0)
		{
			$query
				->where($database->quoteName('a.catid') . ' = :directoryCategoryRestriction')
				->bind(':directoryCategoryRestriction', $categoryRestriction, ParameterType::INTEGER);
		}

		$requiredTags = $this->normaliseIds($archiveParams->get('archive_tag_restriction', []));
		$requiredTagBindings = [];
		$requiredTypeBindings = [];

		foreach ($requiredTags as $index => $requiredTagId)
		{
			$tagPlaceholder = ':directoryRequiredTag' . $index;
			$typePlaceholder = ':directoryRequiredType' . $index;
			$requiredTagBindings[$index] = (int) $requiredTagId;
			$requiredTypeBindings[$index] = $typeAlias;
			$subquery = $database->getQuery(true)
				->select('1')
				->from($database->quoteName('#__contentitem_tag_map', 'requiredTag' . $index))
				->where(
					$database->quoteName('requiredTag' . $index . '.content_item_id')
					. ' = ' . $database->quoteName('a.id')
				)
				->where($database->quoteName('requiredTag' . $index . '.type_alias') . ' = ' . $typePlaceholder)
				->where($database->quoteName('requiredTag' . $index . '.tag_id') . ' = ' . $tagPlaceholder);
			$query
				->where('EXISTS (' . $subquery . ')')
				->bind($tagPlaceholder, $requiredTagBindings[$index], ParameterType::INTEGER)
				->bind($typePlaceholder, $requiredTypeBindings[$index], ParameterType::STRING);
		}

		$query->group($database->quoteName('tm.tag_id'));
		$rows = (array) $database->setQuery($query)->loadObjectList();
		$counts = [];

		foreach ($rows as $row)
		{
			$counts[(int) $row->tag_id] = (int) $row->clip_count;
		}

		return $counts;
	}

	/**
	 * @brief Resolve an accessible public Archive menu item.
	 *
	 * @param int $configuredItemId Explicit directory target.
	 * @param int $preferredItemId Current page menu item.
	 * @param string $language Current site language.
	 * @param int[] $levels Authorised view levels.
	 *
	 * @return object Object containing id and params.
	 */
	private function resolveArchiveTarget(
		int $configuredItemId,
		int $preferredItemId,
		string $language,
		array $levels
	): object
	{
		$candidates = $this->loadArchiveMenuCandidates($language, $levels);

		foreach ([$configuredItemId, $preferredItemId] as $requestedId)
		{
			if ($requestedId <= 0)
			{
				continue;
			}

			foreach ($candidates as $candidate)
			{
				if ((int) $candidate->id === $requestedId)
				{
					return (object) [
						'id' => (int) $candidate->id,
						'params' => new Registry((string) $candidate->params),
					];
				}
			}
		}

		usort($candidates, static function(object $left, object $right) use ($language): int
		{
			$leftLanguage = (string) $left->language === $language ? 0 : 1;
			$rightLanguage = (string) $right->language === $language ? 0 : 1;

			return $leftLanguage <=> $rightLanguage
				?: (int) $left->id <=> (int) $right->id;
		});

		if ($candidates !== [])
		{
			return (object) [
				'id' => (int) $candidates[0]->id,
				'params' => new Registry((string) $candidates[0]->params),
			];
		}

		return (object) [
			'id' => 0,
			'params' => new Registry(),
		];
	}

	/**
	 * @brief Load published accessible Archive menu items.
	 *
	 * @param string $language Current site language.
	 * @param int[] $levels Authorised view levels.
	 *
	 * @return object[] Menu candidates.
	 */
	private function loadArchiveMenuCandidates(string $language, array $levels): array
	{
		$database = $this->database;
		$menuClientId = 0;
		$published = 1;
		$extensionType = 'component';
		$extensionElement = 'com_audioarchive';
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('m.id'),
				$database->quoteName('m.link'),
				$database->quoteName('m.language'),
				$database->quoteName('m.params'),
			])
			->from($database->quoteName('#__menu', 'm'))
			->innerJoin(
				$database->quoteName('#__extensions', 'e')
				. ' ON ' . $database->quoteName('e.extension_id') . ' = ' . $database->quoteName('m.component_id')
			)
			->where($database->quoteName('m.client_id') . ' = :directoryMenuClient')
			->where($database->quoteName('m.published') . ' = :directoryMenuPublished')
			->where($database->quoteName('e.type') . ' = :directoryExtensionType')
			->where($database->quoteName('e.element') . ' = :directoryExtensionElement')
			->whereIn($database->quoteName('m.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('m.language'), ['*', $language], ParameterType::STRING)
			->bind(':directoryMenuClient', $menuClientId, ParameterType::INTEGER)
			->bind(':directoryMenuPublished', $published, ParameterType::INTEGER)
			->bind(':directoryExtensionType', $extensionType, ParameterType::STRING)
			->bind(':directoryExtensionElement', $extensionElement, ParameterType::STRING)
			->order($database->quoteName('m.id') . ' ASC');
		$rows = (array) $database->setQuery($query)->loadObjectList();

		return array_values(array_filter($rows, static function(object $row): bool
		{
			$linkQuery = [];
			parse_str(
				(string) parse_url(str_replace('&amp;', '&', (string) $row->link), PHP_URL_QUERY),
				$linkQuery
			);

			return ($linkQuery['option'] ?? '') === 'com_audioarchive'
				&& ($linkQuery['view'] ?? '') === 'archive';
		}));
	}

	/**
	 * @brief Sort directory items according to configuration.
	 *
	 * @param object[] $items Directory items, modified in place.
	 * @param string $ordering Configured ordering key.
	 * @param int[] $selectedTagIds Configured selection order.
	 *
	 * @return void
	 */
	private function sortItems(array &$items, string $ordering, array $selectedTagIds): void
	{
		if ($ordering === 'selected' && $selectedTagIds !== [])
		{
			$positions = array_flip($selectedTagIds);
			usort($items, static function(object $left, object $right) use ($positions): int
			{
				$leftPosition = (int) ($positions[(int) $left->id] ?? PHP_INT_MAX);
				$rightPosition = (int) ($positions[(int) $right->id] ?? PHP_INT_MAX);

				return $leftPosition <=> $rightPosition
					?: strnatcasecmp((string) $left->title, (string) $right->title);
			});
			return;
		}

		if ($ordering === 'count')
		{
			usort($items, static function(object $left, object $right): int
			{
				return (int) $right->clip_count <=> (int) $left->clip_count
					?: strnatcasecmp((string) $left->title, (string) $right->title);
			});
			return;
		}

		usort($items, static function(object $left, object $right): int
		{
			return strnatcasecmp((string) $left->title, (string) $right->title)
				?: (int) $left->id <=> (int) $right->id;
		});
	}

	/**
	 * @brief Normalise array, JSON, or comma-separated identifiers.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int[] Positive unique identifiers in input order.
	 */
	private function normaliseIds(mixed $value): array
	{
		if (is_string($value))
		{
			$trimmed = trim($value);

			if ($trimmed === '')
			{
				return [];
			}

			$decoded = json_decode($trimmed, true);
			$value = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $trimmed);
		}

		if (!is_array($value))
		{
			$value = [$value];
		}

		$ids = [];

		foreach ($value as $entry)
		{
			$id = (int) $entry;

			if ($id > 0 && !in_array($id, $ids, true))
			{
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
