<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\TagDescriptionHelper;

\defined('_JEXEC') or die;

/**
 * @brief Public archive list model.
 */
class ArchiveModel extends ListModel
{
	/** @var string[] */
	private array $filterErrors = [];

	/** @var array<int, int> */
	private array $selectedTags = [];

	/** @var array<int, int> */
	private array $menuTags = [];

	/** @var Registry|null */
	private ?Registry $resolvedParams = null;

	/** @var bool */
	private bool $ignoreVisitorCategoryFilter = false;

	/** @var bool */
	private bool $ignoreVisitorFiltersForMaximum = false;

	/** @var int|null */
	private ?int $maximumDurationCache = null;

	/** @var array<string, mixed>|null */
	private ?array $canonicalQueryCache = null;

	/** @var int */
	private int $sessionItemId = 0;

	/** @var int */
	private int $contextItemId = 0;

	/**
	 * @brief Construct the public list model.
	 *
	 * @param array $config Model configuration.
	 */
	public function __construct($config = [])
	{
		$this->contextItemId = max(0, (int) ($config['item_id'] ?? 0));

		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'title', 'a.title',
				'duration', 'a.duration_ms',
				'recorded', 'a.recorded_at',
				'uploaded', 'a.uploaded_at',
			];
		}

		parent::__construct($config);
	}

	/**
	 * @brief Return published clips visible to the current visitor.
	 *
	 * @return QueryInterface
	 */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a.id'),
				$db->quoteName('a.title'),
				$db->quoteName('a.alias'),
				$db->quoteName('a.description'),
				$db->quoteName('a.original_filename'),
				$db->quoteName('a.duration_ms'),
				$db->quoteName('a.recorded_at'),
				$db->quoteName('a.uploaded_at'),
				$db->quoteName('a.catid'),
				$db->quoteName('a.access'),
				$db->quoteName('a.play_count'),
				$db->quoteName('a.download_count'),
				$db->quoteName('f.file_extension'),
				$db->quoteName('f.mime_type'),
				$db->quoteName('f.audio_codec'),
				$db->quoteName('f.container_format'),
				$db->quoteName('f.file_size'),
				$db->quoteName('c.title', 'category_title'),
				$db->quoteName('c.lft', 'category_lft'),
				$db->quoteName('c.rgt', 'category_rgt'),
			])
			->from($db->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$db->quoteName('#__categories', 'c')
				. ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
			)
			->innerJoin(
				$db->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $db->quoteName('f.clip_id') . ' = ' . $db->quoteName('a.id')
				. ' AND ' . $db->quoteName('f.file_role') . ' = :originalRole'
				. ' AND ' . $db->quoteName('f.is_available') . ' = :fileAvailable'
			);

		$published = 1;
		$originalRole = 'original';
		$fileAvailable = 1;
		$categoryExtension = 'com_audioarchive';
		$now = Factory::getDate()->toSql();
		$query
			->where($db->quoteName('a.state') . ' = :published')
			->where($db->quoteName('c.published') . ' = :categoryPublished')
			->where($db->quoteName('c.extension') . ' = :categoryExtension')
			->extendWhere(
				'AND',
				[
					$db->quoteName('a.publish_up') . ' IS NULL',
					$db->quoteName('a.publish_up') . ' <= :publishNow',
				],
				'OR'
			)
			->extendWhere(
				'AND',
				[
					$db->quoteName('a.publish_down') . ' IS NULL',
					$db->quoteName('a.publish_down') . ' >= :unpublishNow',
				],
				'OR'
			)
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':originalRole', $originalRole, ParameterType::STRING)
			->bind(':fileAvailable', $fileAvailable, ParameterType::INTEGER)
			->bind(':categoryPublished', $published, ParameterType::INTEGER)
			->bind(':categoryExtension', $categoryExtension, ParameterType::STRING)
			->bind(':publishNow', $now, ParameterType::STRING)
			->bind(':unpublishNow', $now, ParameterType::STRING);

		$levels = $this->getAuthorisedViewLevels();
		$query->whereIn($db->quoteName('a.access'), $levels, ParameterType::INTEGER);
		$query->whereIn($db->quoteName('c.access'), $levels, ParameterType::INTEGER);
		$this->addAncestorCategoryRestrictions($query, 'c', $levels);

		$search = trim((string) $this->getState('filter.search'));
		if (!$this->ignoreVisitorFiltersForMaximum && $search !== '')
		{
			$search = '%' . str_replace(' ', '%', $search) . '%';
			$query
				->where(
					'(' . $db->quoteName('a.title') . ' LIKE :searchTitle'
					. ' OR ' . $db->quoteName('a.description') . ' LIKE :searchDescription'
					. ' OR ' . $db->quoteName('a.original_filename') . ' LIKE :searchFilename)'
				)
				->bind(':searchTitle', $search, ParameterType::STRING)
				->bind(':searchDescription', $search, ParameterType::STRING)
				->bind(':searchFilename', $search, ParameterType::STRING);
		}

		$categoryId = (int) $this->getState('filter.category');
		$menuCategoryId = (int) $this->getResolvedParams()->get('archive_category_restriction', 0);
		if ($menuCategoryId > 0)
		{
			$query->where($db->quoteName('a.catid') . ' = :menuCategory')
				->bind(':menuCategory', $menuCategoryId, ParameterType::INTEGER);
		}
		elseif (!$this->ignoreVisitorFiltersForMaximum && !$this->ignoreVisitorCategoryFilter && $categoryId > 0)
		{
			$query->where($db->quoteName('a.catid') . ' = :filterCategory')
				->bind(':filterCategory', $categoryId, ParameterType::INTEGER);
		}

		if (!$this->ignoreVisitorFiltersForMaximum)
		{
			$durationMinimum = $this->getState('filter.duration_min_ms');
			if ($durationMinimum !== null)
			{
				$durationMinimum = (int) $durationMinimum;
				$query->where($db->quoteName('a.duration_ms') . ' >= :durationMinimum')
					->bind(':durationMinimum', $durationMinimum, ParameterType::INTEGER);
			}

			$durationMaximum = $this->getState('filter.duration_max_ms');
			if ($durationMaximum !== null)
			{
				$durationMaximum = (int) $durationMaximum;
				$durationMaximumExclusive = $durationMaximum >= PHP_INT_MAX - 1000
					? PHP_INT_MAX
					: $durationMaximum + 1000;

				// Durations are displayed as whole seconds using floor(). Therefore an
				// upper value of 2 must include 2.000 through 2.999 seconds.
				$query->where($db->quoteName('a.duration_ms') . ' < :durationMaximumExclusive')
					->bind(':durationMaximumExclusive', $durationMaximumExclusive, ParameterType::INTEGER);
			}

			// Joomla database bindings are by reference. Keep each date value in a
			// dedicated variable for the lifetime of the query instead of reusing one
			// loop variable, which would make all placeholders reference the final value.
			$recordedFrom = $this->getState('filter.recorded_from_sql');
			if ($recordedFrom !== null)
			{
				$recordedFrom = (string) $recordedFrom;
				$query->where($db->quoteName('a.recorded_at') . ' >= :recordedFrom')
					->bind(':recordedFrom', $recordedFrom, ParameterType::STRING);
			}

			$recordedTo = $this->getState('filter.recorded_to_sql');
			if ($recordedTo !== null)
			{
				$recordedTo = (string) $recordedTo;
				$query->where($db->quoteName('a.recorded_at') . ' <= :recordedTo')
					->bind(':recordedTo', $recordedTo, ParameterType::STRING);
			}

			$uploadedFrom = $this->getState('filter.uploaded_from_sql');
			if ($uploadedFrom !== null)
			{
				$uploadedFrom = (string) $uploadedFrom;
				$query->where($db->quoteName('a.uploaded_at') . ' >= :uploadedFrom')
					->bind(':uploadedFrom', $uploadedFrom, ParameterType::STRING);
			}

			$uploadedTo = $this->getState('filter.uploaded_to_sql');
			if ($uploadedTo !== null)
			{
				$uploadedTo = (string) $uploadedTo;
				$query->where($db->quoteName('a.uploaded_at') . ' <= :uploadedTo')
					->bind(':uploadedTo', $uploadedTo, ParameterType::STRING);
			}
		}

		$tagBindings = [];
		$typeBindings = [];
		$mandatoryTags = $this->menuTags;
		$visitorTags = $this->ignoreVisitorFiltersForMaximum ? [] : $this->selectedTags;

		foreach ($mandatoryTags as $index => $tagId)
		{
			$tagPlaceholder = ':archiveMenuTag' . $index;
			$typePlaceholder = ':archiveMenuType' . $index;
			$tagBindings['menu' . $index] = (int) $tagId;
			$typeBindings['menu' . $index] = 'com_audioarchive.clip';
			$subquery = $db->getQuery(true)
				->select('1')
				->from($db->quoteName('#__contentitem_tag_map', 'mtm' . $index))
				->where($db->quoteName('mtm' . $index . '.content_item_id') . ' = ' . $db->quoteName('a.id'))
				->where($db->quoteName('mtm' . $index . '.type_alias') . ' = ' . $typePlaceholder)
				->where($db->quoteName('mtm' . $index . '.tag_id') . ' = ' . $tagPlaceholder);
			$query->where('EXISTS (' . $subquery . ')')
				->bind($tagPlaceholder, $tagBindings['menu' . $index], ParameterType::INTEGER)
				->bind($typePlaceholder, $typeBindings['menu' . $index], ParameterType::STRING);
		}

		if ($visitorTags !== [])
		{
			$tagMode = (string) $this->getState('filter.tag_mode', 'and');

			if ($tagMode === 'or')
			{
				$typePlaceholder = ':archiveVisitorOrType';
				$typeBindings['visitorOr'] = 'com_audioarchive.clip';
				$tagConditions = [];
				$tagPlaceholders = [];

				foreach ($visitorTags as $index => $tagId)
				{
					$tagPlaceholder = ':archiveVisitorOrTag' . $index;
					$bindingKey = 'visitorOr' . $index;
					$tagBindings[$bindingKey] = (int) $tagId;
					$tagConditions[] = $db->quoteName('vtm.tag_id') . ' = ' . $tagPlaceholder;
					$tagPlaceholders[$bindingKey] = $tagPlaceholder;
				}

				$subquery = $db->getQuery(true)
					->select('1')
					->from($db->quoteName('#__contentitem_tag_map', 'vtm'))
					->where($db->quoteName('vtm.content_item_id') . ' = ' . $db->quoteName('a.id'))
					->where($db->quoteName('vtm.type_alias') . ' = ' . $typePlaceholder)
					->where('(' . implode(' OR ', $tagConditions) . ')');
				$query->where('EXISTS (' . $subquery . ')')
					->bind($typePlaceholder, $typeBindings['visitorOr'], ParameterType::STRING);

				foreach ($tagPlaceholders as $bindingKey => $tagPlaceholder)
				{
					$query->bind($tagPlaceholder, $tagBindings[$bindingKey], ParameterType::INTEGER);
				}
			}
			else
			{
				foreach ($visitorTags as $index => $tagId)
				{
					$tagPlaceholder = ':archiveVisitorTag' . $index;
					$typePlaceholder = ':archiveVisitorType' . $index;
					$tagBindings['visitor' . $index] = (int) $tagId;
					$typeBindings['visitor' . $index] = 'com_audioarchive.clip';
					$subquery = $db->getQuery(true)
						->select('1')
						->from($db->quoteName('#__contentitem_tag_map', 'vtm' . $index))
						->where($db->quoteName('vtm' . $index . '.content_item_id') . ' = ' . $db->quoteName('a.id'))
						->where($db->quoteName('vtm' . $index . '.type_alias') . ' = ' . $typePlaceholder)
						->where($db->quoteName('vtm' . $index . '.tag_id') . ' = ' . $tagPlaceholder);
					$query->where('EXISTS (' . $subquery . ')')
						->bind($tagPlaceholder, $tagBindings['visitor' . $index], ParameterType::INTEGER)
						->bind($typePlaceholder, $typeBindings['visitor' . $index], ParameterType::STRING);
				}
			}
		}

		$orderMap = [
			'title' => $db->quoteName('a.title'),
			'duration' => $db->quoteName('a.duration_ms'),
			'recorded' => $db->quoteName('a.recorded_at'),
			'uploaded' => $db->quoteName('a.uploaded_at'),
		];
		$sort = (string) $this->getState('list.ordering', 'uploaded');
		$direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$query->order(($orderMap[$sort] ?? $orderMap['uploaded']) . ' ' . $direction);
		$query->order($db->quoteName('a.id') . ' ' . $direction);

		return $query;
	}

	/**
	 * @brief Return the clips immediately before and after one clip in the current archive result set.
	 *
	 * The same menu restrictions, visitor filters, ordering, publication rules,
	 * category rules, and access-level checks as the public archive are applied.
	 *
	 * @param int $currentId Current clip identifier.
	 *
	 * @return array{previous: object|null, next: object|null}
	 */
	public function getAdjacentItems(int $currentId): array
	{
		$result = [
			'previous' => null,
			'next' => null,
		];

		if ($currentId <= 0)
		{
			return $result;
		}

		$items = $this->loadOrderedNavigationItems(false);
		$currentIndex = $this->findNavigationItemIndex($items, $currentId);

		if ($currentIndex === null)
		{
			$items = $this->loadOrderedNavigationItems(true);
			$currentIndex = $this->findNavigationItemIndex($items, $currentId);
		}

		if ($currentIndex === null)
		{
			return $result;
		}

		$result['previous'] = $currentIndex > 0 ? $items[$currentIndex - 1] : null;
		$result['next'] = isset($items[$currentIndex + 1]) ? $items[$currentIndex + 1] : null;

		return $result;
	}

	/**
	 * @brief Load ordered clip identifiers and titles for detail navigation.
	 *
	 * @param bool $ignoreVisitorFilters Whether to ignore visitor-entered filters while retaining menu restrictions.
	 *
	 * @return object[] Ordered navigation items.
	 */
	private function loadOrderedNavigationItems(bool $ignoreVisitorFilters): array
	{
		$db = $this->getDatabase();
		$this->ignoreVisitorFiltersForMaximum = $ignoreVisitorFilters;

		try
		{
			$query = $this->getListQuery();
		}
		finally
		{
			$this->ignoreVisitorFiltersForMaximum = false;
		}

		$query
			->clear('select')
			->select([
				$db->quoteName('a.id'),
				$db->quoteName('a.title'),
			]);

		return (array) $db->setQuery($query)->loadObjectList();
	}

	/**
	 * @brief Find one clip within an ordered navigation item list.
	 *
	 * @param object[] $items Ordered navigation items.
	 * @param int $currentId Current clip identifier.
	 *
	 * @return int|null Zero-based item index or null when absent.
	 */
	private function findNavigationItemIndex(array $items, int $currentId): ?int
	{
		foreach ($items as $index => $item)
		{
			if ((int) $item->id === $currentId)
			{
				return $index;
			}
		}

		return null;
	}

	/**
	 * @brief Add visible tag data to each paginated clip.
	 *
	 * @return array
	 */
	public function getItems()
	{
		$items = parent::getItems();
		$ids = array_map(static fn(object $item): int => (int) $item->id, $items);
		$tagData = $ids ? (new TagsHelper())->getMultipleItemTags('com_audioarchive.clip', $ids, true) : [];
		$tagData = TagDescriptionHelper::enrichGroups($this->getDatabase(), $tagData);

		foreach ($items as $item)
		{
			$item->tags = $tagData[(int) $item->id] ?? [];
		}

		return $items;
	}

	/**
	 * @brief Return categories available to the public filter.
	 *
	 * @return object[]
	 */
	public function getCategoryOptions(): array
	{
		$db = $this->getDatabase();
		$extension = 'com_audioarchive';
		$published = 1;
		$levels = $this->getAuthorisedViewLevels();
		$showEmpty = (int) $this->getResolvedParams()->get('archive_show_empty_categories', 0) === 1;
		$visibleCategoryIds = [];

		if (!$showEmpty)
		{
			$this->ignoreVisitorCategoryFilter = true;

			try
			{
				$visibleQuery = $this->getListQuery();
			}
			finally
			{
				$this->ignoreVisitorCategoryFilter = false;
			}

			$visibleQuery->clear('select')->clear('order')->select('DISTINCT ' . $db->quoteName('a.catid'));
			$visibleCategoryIds = array_values(array_unique(array_map('intval', $db->setQuery($visibleQuery)->loadColumn())));

			if ($visibleCategoryIds === [])
			{
				return [];
			}
		}

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('c.id'),
				$db->quoteName('c.title'),
				$db->quoteName('c.level'),
			])
			->from($db->quoteName('#__categories', 'c'))
			->where($db->quoteName('c.extension') . ' = :optionExtension')
			->where($db->quoteName('c.published') . ' = :optionPublished')
			->whereIn($db->quoteName('c.access'), $levels, ParameterType::INTEGER)
			->order($db->quoteName('c.lft') . ' ASC')
			->bind(':optionExtension', $extension, ParameterType::STRING)
			->bind(':optionPublished', $published, ParameterType::INTEGER);
		$this->addAncestorCategoryRestrictions($query, 'c', $levels, 'option');

		if (!$showEmpty)
		{
			$query->whereIn($db->quoteName('c.id'), $visibleCategoryIds, ParameterType::INTEGER);
		}

		return TagDescriptionHelper::enrich($db, (array) $db->setQuery($query)->loadObjectList());
	}


	/**
	 * @brief Return the longest publicly eligible clip duration for this archive menu item.
	 *
	 * Visitor-entered filters are intentionally ignored so the slider range remains stable.
	 * Menu category/tag restrictions, publication rules, and access levels still apply.
	 *
	 * @return int Maximum duration in milliseconds.
	 */
	public function getMaximumDurationMs(): int
	{
		if ($this->maximumDurationCache !== null)
		{
			return $this->maximumDurationCache;
		}

		$db = $this->getDatabase();
		$this->ignoreVisitorFiltersForMaximum = true;

		try
		{
			$query = $this->getListQuery();
		}
		finally
		{
			$this->ignoreVisitorFiltersForMaximum = false;
		}

		$query
			->clear('select')
			->clear('order')
			->select('MAX(' . $db->quoteName('a.duration_ms') . ')');

		$this->maximumDurationCache = max(0, (int) $db->setQuery($query)->loadResult());

		return $this->maximumDurationCache;
	}

	/**
	 * @brief Return configured visitor-selectable page sizes.
	 *
	 * @return int[]
	 */
	public function getPageSizeOptions(): array
	{
		$params = $this->getResolvedParams();
		$maximum = max(1, min(1000, (int) $params->get('archive_maximum_page_size', 200)));
		$values = preg_split('/[,;\s]+/', (string) $params->get('archive_allowed_page_sizes', '10,20,50,100')) ?: [];
		$options = array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $value): bool => $value > 0 && $value <= $maximum)));
		$default = max(1, min($maximum, (int) $params->get('archive_default_limit', $params->get('default_limit', 20))));
		$options[] = $default;
		$options = array_values(array_unique($options));
		sort($options, SORT_NUMERIC);

		return $options !== [] ? $options : [$default];
	}

	/**
	 * @brief Return global Joomla tags currently used by Audio Archive clips.
	 *
	 * @return object[]
	 */
	public function getTagOptions(): array
	{
		$db = $this->getDatabase();
		$typeAlias = 'com_audioarchive.clip';
		$published = 1;
		$levels = $this->getAuthorisedViewLevels();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('t.id'),
				$db->quoteName('t.title'),
				$db->quoteName('t.alias'),
				$db->quoteName('t.path'),
			])
			->from($db->quoteName('#__tags', 't'))
			->innerJoin(
				$db->quoteName('#__contentitem_tag_map', 'm')
				. ' ON ' . $db->quoteName('m.tag_id') . ' = ' . $db->quoteName('t.id')
			)
			->where($db->quoteName('m.type_alias') . ' = :typeAlias')
			->where($db->quoteName('t.published') . ' = :published')
			->whereIn($db->quoteName('t.access'), $levels, ParameterType::INTEGER)
			->group([
				$db->quoteName('t.id'),
				$db->quoteName('t.title'),
				$db->quoteName('t.alias'),
				$db->quoteName('t.path'),
			])
			->order($db->quoteName('t.path') . ' ASC')
			->bind(':typeAlias', $typeAlias, ParameterType::STRING)
			->bind(':published', $published, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
	}


	/**
	 * @brief Return the canonical public query values for the current archive state.
	 *
	 * Empty fields and values equal to the menu/component defaults are omitted.
	 * Tag identifiers are converted to stable Joomla tag aliases.
	 *
	 * @return array<string, mixed>
	 */
	public function getCanonicalQueryValues(): array
	{
		if ($this->canonicalQueryCache !== null)
		{
			return $this->canonicalQueryCache;
		}

		$params = $this->getResolvedParams();
		$values = [];
		$search = trim((string) $this->getState('filter.search', ''));
		$category = (int) $this->getState('filter.category', 0);
		$tagAliases = $this->loadTagAliasesById((array) $this->getState('filter.tags', []));
		$durationMinimum = trim((string) $this->getState('filter.duration_min', ''));
		$durationMaximum = trim((string) $this->getState('filter.duration_max', ''));
		$durationMinimumMs = $this->getState('filter.duration_min_ms');
		$durationMaximumMs = $this->getState('filter.duration_max_ms');
		$maximumDurationMs = $this->getMaximumDurationMs();
		$durationMaximumExclusive = $durationMaximumMs !== null
			? ((int) $durationMaximumMs >= PHP_INT_MAX - 1000 ? PHP_INT_MAX : (int) $durationMaximumMs + 1000)
			: null;

		if ($search !== '')
		{
			$values['q'] = $search;
		}

		if ($category > 0)
		{
			$values['category'] = $category;
		}

		if ($tagAliases !== [])
		{
			sort($tagAliases, SORT_NATURAL | SORT_FLAG_CASE);
			$values['tags'] = implode(',', $tagAliases);

			if ((string) $this->getState('filter.tag_mode', 'and') === 'or')
			{
				$values['tag_mode'] = 'or';
			}
		}

		if ($durationMinimum !== '' && $durationMinimumMs !== null && (int) $durationMinimumMs > 0)
		{
			$values['duration_min'] = $durationMinimum;
		}

		if (
			$durationMaximum !== ''
			&& $durationMaximumMs !== null
			&& (int) $durationMaximumMs > 0
			&& $durationMaximumExclusive !== null
			&& $maximumDurationMs > 0
			&& $durationMaximumExclusive <= $maximumDurationMs
		)
		{
			$values['duration_max'] = $durationMaximum;
		}

		foreach (['recorded_from', 'recorded_to', 'uploaded_from', 'uploaded_to'] as $name)
		{
			$value = trim((string) $this->getState('filter.' . $name, ''));

			if ($value !== '')
			{
				$values[$name] = $value;
			}
		}

		$ordering = (string) $this->getState('list.ordering', 'uploaded');
		$direction = strtolower((string) $this->getState('list.direction', 'DESC'));
		$limit = (int) $this->getState('list.limit', 20);
		$defaultOrdering = $this->getDefaultOrdering($params);
		$defaultDirection = strtolower($this->getDefaultDirection($params));
		$defaultLimit = $this->getDefaultLimit($params);

		if ($ordering !== $defaultOrdering)
		{
			$values['sort'] = $ordering;
		}

		if ($direction !== $defaultDirection)
		{
			$values['direction'] = $direction;
		}

		if ($limit !== $defaultLimit)
		{
			$values['limit'] = $limit;
		}

		$this->canonicalQueryCache = $values;
		Factory::getApplication()->setUserState(
			self::getQuerySessionKey($this->sessionItemId),
			$this->canonicalQueryCache
		);

		return $this->canonicalQueryCache;
	}

	/**
	 * @brief Resolve visitor tag input supplied as aliases, IDs, or comma-separated values.
	 *
	 * @param mixed $value Raw request value.
	 *
	 * @return int[] Accessible published tag identifiers.
	 */
	private function resolveVisitorTagIdentifiers(mixed $value): array
	{
		$entries = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);
		$ids = [];
		$aliases = [];

		foreach ((array) $entries as $entry)
		{
			foreach (preg_split('/\s*,\s*/', trim((string) $entry)) ?: [] as $part)
			{
				$part = trim($part);

				if ($part === '')
				{
					continue;
				}

				if (ctype_digit($part) && (int) $part > 0)
				{
					$ids[] = (int) $part;
				}
				else
				{
					$aliases[] = $part;
				}
			}
		}

		$ids = array_values(array_unique($ids));
		$aliases = array_values(array_unique($aliases));

		if ($ids === [] && $aliases === [])
		{
			return [];
		}

		$db = $this->getDatabase();
		$published = 1;
		$levels = $this->getAuthorisedViewLevels();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__tags'))
			->where($db->quoteName('published') . ' = :visitorTagPublished')
			->whereIn($db->quoteName('access'), $levels, ParameterType::INTEGER)
			->bind(':visitorTagPublished', $published, ParameterType::INTEGER);
		$conditions = [];

		if ($ids !== [])
		{
			$conditions[] = $db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
		}

		if ($aliases !== [])
		{
			$conditions[] = $db->quoteName('alias') . ' IN (' . implode(',', array_map([$db, 'quote'], $aliases)) . ')';
		}

		$query->where('(' . implode(' OR ', $conditions) . ')');
		$result = array_values(array_unique(array_map('intval', (array) $db->setQuery($query)->loadColumn())));
		sort($result, SORT_NUMERIC);

		return $result;
	}

	/**
	 * @brief Load aliases for the supplied tag identifiers.
	 *
	 * @param int[] $tagIds Tag identifiers.
	 *
	 * @return string[] Tag aliases.
	 */
	private function loadTagAliasesById(array $tagIds): array
	{
		$tagIds = $this->normaliseIntegerList($tagIds);

		if ($tagIds === [])
		{
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('alias'))
			->from($db->quoteName('#__tags'))
			->whereIn($db->quoteName('id'), $tagIds, ParameterType::INTEGER);

		return array_values(array_unique(array_filter(array_map(
			static fn(mixed $alias): string => trim((string) $alias),
			(array) $db->setQuery($query)->loadColumn()
		))));
	}

	/**
	 * @brief Return the resolved default public ordering key.
	 *
	 * @param Registry $params Resolved component/menu parameters.
	 *
	 * @return string
	 */
	private function getDefaultOrdering(Registry $params): string
	{
		$ordering = (string) $params->get('archive_default_ordering', $params->get('default_ordering', 'uploaded_at'));

		return match ($ordering)
		{
			'uploaded_at' => 'uploaded',
			'recorded_at' => 'recorded',
			default => in_array($ordering, ['title', 'duration', 'recorded', 'uploaded'], true) ? $ordering : 'uploaded',
		};
	}

	/**
	 * @brief Return the resolved default public sort direction.
	 *
	 * @param Registry $params Resolved component/menu parameters.
	 *
	 * @return string ASC or DESC.
	 */
	private function getDefaultDirection(Registry $params): string
	{
		return strtoupper((string) $params->get('archive_default_direction', $params->get('default_direction', 'desc'))) === 'ASC'
			? 'ASC'
			: 'DESC';
	}

	/**
	 * @brief Return the resolved default public page size.
	 *
	 * @param Registry $params Resolved component/menu parameters.
	 *
	 * @return int
	 */
	private function getDefaultLimit(Registry $params): int
	{
		$maximum = max(1, min(1000, (int) $params->get('archive_maximum_page_size', 200)));

		return max(1, min($maximum, (int) $params->get('archive_default_limit', $params->get('default_limit', 20))));
	}

	/**
	 * @brief Return validation messages generated while parsing filters.
	 *
	 * @return string[]
	 */
	public function getFilterErrors(): array
	{
		return $this->filterErrors;
	}

	/**
	 * @brief Return component settings with menu-item overrides resolved.
	 *
	 * @return Registry
	 */
	public function getResolvedParams(): Registry
	{
		if ($this->resolvedParams !== null)
		{
			return $this->resolvedParams;
		}

		$params = clone ComponentHelper::getParams('com_audioarchive');
		$application = Factory::getApplication();
		$item = $this->contextItemId > 0
			? $application->getMenu()->getItem($this->contextItemId)
			: $application->getMenu()->getActive();
		if ($item)
		{
			$menuParams = $item->getParams();
			foreach ($menuParams->toArray() as $key => $value)
			{
				if ($value !== '' && $value !== null)
				{
					$params->set($key, $value);
				}
			}
		}

		$this->resolvedParams = $params;
		return $this->resolvedParams;
	}

	/**
	 * @brief Return the per-menu session key for resolved archive state.
	 *
	 * @param int $itemId Archive menu item identifier.
	 *
	 * @return string
	 */
	public static function getStateSessionKey(int $itemId): string
	{
		return 'com_audioarchive.archive.state.' . max(0, $itemId);
	}

	/**
	 * @brief Return the per-menu session key for the canonical archive query.
	 *
	 * @param int $itemId Archive menu item identifier.
	 *
	 * @return string
	 */
	public static function getQuerySessionKey(int $itemId): string
	{
		return 'com_audioarchive.archive.query.' . max(0, $itemId);
	}

	/**
	 * @brief Populate state from bookmarkable GET parameters.
	 *
	 * @param string|null $ordering Default ordering.
	 * @param string|null $direction Default direction.
	 *
	 * @return void
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = Factory::getApplication();
		$input = $app->getInput();
		$params = $this->getResolvedParams();
		$request = $input->getArray();
		$itemId = $this->contextItemId > 0
			? $this->contextItemId
			: $input->getInt('Itemid', 0);

		if ($itemId <= 0)
		{
			$itemId = (int) ($app->getMenu()->getActive()?->id ?? 0);
		}

		$this->sessionItemId = max(0, $itemId);
		$sessionKey = self::getStateSessionKey($this->sessionItemId);
		$querySessionKey = self::getQuerySessionKey($this->sessionItemId);
		$reset = (int) ($request['audioarchive_reset'] ?? 0) === 1;
		$stateKeys = [
			'q', 'category', 'tags', 'tag_mode', 'duration_min', 'duration_max',
			'recorded_from', 'recorded_to', 'uploaded_from', 'uploaded_to',
			'sort', 'direction', 'limit', 'limitstart', 'audioarchive_state',
		];
		$hasExplicitState = $input->getCmd('task') === 'archive.applyFilters';

		foreach ($stateKeys as $key)
		{
			if (array_key_exists($key, $request))
			{
				$hasExplicitState = true;
				break;
			}
		}

		if ($reset)
		{
			$app->setUserState($sessionKey, null);
			$app->setUserState($querySessionKey, null);
			$source = [];
		}
		elseif ($hasExplicitState)
		{
			$source = $request;
		}
		else
		{
			$stored = $app->getUserState($sessionKey, []);
			$source = is_array($stored) ? $stored : [];
		}

		$search = trim((string) ($source['q'] ?? ''));
		$category = max(0, (int) ($source['category'] ?? 0));
		$durationMinimumInput = trim((string) ($source['duration_min'] ?? ''));
		$durationMaximumInput = trim((string) ($source['duration_max'] ?? ''));
		$recordedFromInput = trim((string) ($source['recorded_from'] ?? ''));
		$recordedToInput = trim((string) ($source['recorded_to'] ?? ''));
		$uploadedFromInput = trim((string) ($source['uploaded_from'] ?? ''));
		$uploadedToInput = trim((string) ($source['uploaded_to'] ?? ''));

		$this->setState('filter.search', $search);
		$this->setState('filter.category', $category);
		$this->setState('filter.duration_min', $durationMinimumInput);
		$this->setState('filter.duration_max', $durationMaximumInput);
		$this->setState('filter.recorded_from', $recordedFromInput);
		$this->setState('filter.recorded_to', $recordedToInput);
		$this->setState('filter.uploaded_from', $uploadedFromInput);
		$this->setState('filter.uploaded_to', $uploadedToInput);

		$requestedTags = $this->resolveVisitorTagIdentifiers($source['tags'] ?? []);
		$menuTags = $this->normaliseIntegerList($params->get('archive_tag_restriction', []));
		$this->menuTags = $menuTags;
		$this->selectedTags = $requestedTags;
		$this->setState('filter.tags', $requestedTags);
		$tagMode = strtolower(trim((string) ($source['tag_mode'] ?? 'and')));
		$this->setState('filter.tag_mode', $tagMode === 'or' ? 'or' : 'and');

		$minimum = $this->parseDuration($durationMinimumInput, 'COM_AUDIOARCHIVE_FILTER_DURATION_MIN_INVALID');
		$maximum = $this->parseDuration($durationMaximumInput, 'COM_AUDIOARCHIVE_FILTER_DURATION_MAX_INVALID');

		if ($minimum !== null && $maximum !== null && $minimum > $maximum)
		{
			[$minimum, $maximum] = [$maximum, $minimum];
		}

		$this->setState('filter.duration_min_ms', $minimum);
		$this->setState('filter.duration_max_ms', $maximum);
		$this->setState('filter.recorded_from_sql', $this->parseDate($recordedFromInput, false, 'COM_AUDIOARCHIVE_FILTER_RECORDED_FROM_INVALID'));
		$this->setState('filter.recorded_to_sql', $this->parseDate($recordedToInput, true, 'COM_AUDIOARCHIVE_FILTER_RECORDED_TO_INVALID'));
		$this->setState('filter.uploaded_from_sql', $this->parseDate($uploadedFromInput, false, 'COM_AUDIOARCHIVE_FILTER_UPLOADED_FROM_INVALID'));
		$this->setState('filter.uploaded_to_sql', $this->parseDate($uploadedToInput, true, 'COM_AUDIOARCHIVE_FILTER_UPLOADED_TO_INVALID'));

		$allowedOrdering = ['title', 'duration', 'recorded', 'uploaded'];
		$defaultOrdering = $this->getDefaultOrdering($params);
		$requestedOrdering = trim((string) ($source['sort'] ?? $defaultOrdering));
		$resolvedOrdering = in_array($requestedOrdering, $allowedOrdering, true) ? $requestedOrdering : $defaultOrdering;
		$this->setState('list.ordering', $resolvedOrdering);

		$defaultDirection = $this->getDefaultDirection($params);
		$requestedDirection = strtoupper(trim((string) ($source['direction'] ?? $defaultDirection)));
		$resolvedDirection = $requestedDirection === 'ASC' ? 'ASC' : 'DESC';
		$this->setState('list.direction', $resolvedDirection);

		$maximumLimit = max(1, min(1000, (int) $params->get('archive_maximum_page_size', 200)));
		$allowedLimits = $this->getPageSizeOptions();
		$defaultLimit = $this->getDefaultLimit($params);
		$requestedLimit = max(1, min($maximumLimit, (int) ($source['limit'] ?? $defaultLimit)));
		$limit = in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;
		$this->setState('list.limit', $limit);
		$this->setState('list.start', max(0, (int) ($source['limitstart'] ?? 0)));

		if (!$reset)
		{
			$app->setUserState($sessionKey, [
				'q' => $search,
				'category' => $category,
				'tags' => $requestedTags,
				'tag_mode' => $tagMode === 'or' ? 'or' : 'and',
				'duration_min' => $durationMinimumInput,
				'duration_max' => $durationMaximumInput,
				'recorded_from' => $recordedFromInput,
				'recorded_to' => $recordedToInput,
				'uploaded_from' => $uploadedFromInput,
				'uploaded_to' => $uploadedToInput,
				'sort' => $resolvedOrdering,
				'direction' => strtolower($resolvedDirection),
				'limit' => $limit,
			]);
		}
	}

	/**
	 * @brief Return authorised access-level identifiers with a safe fallback.
	 *
	 * @return int[]
	 */
	private function getAuthorisedViewLevels(): array
	{
		$levels = array_values(array_unique(array_filter(array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()), static fn(int $id): bool => $id > 0)));

		return $levels !== [] ? $levels : [1];
	}

	/**
	 * @brief Exclude clips or options beneath hidden ancestor categories.
	 *
	 * @param QueryInterface $query Query receiving the restriction.
	 * @param string $categoryAlias Direct category alias.
	 * @param int[] $levels Authorised access levels.
	 * @param string $bindingPrefix Unique placeholder prefix.
	 *
	 * @return void
	 */
	private function addAncestorCategoryRestrictions(QueryInterface $query, string $categoryAlias, array $levels, string $bindingPrefix = 'archive'): void
	{
		$db = $this->getDatabase();
		$extension = 'com_audioarchive';
		$published = 1;
		$extensionPlaceholder = ':' . $bindingPrefix . 'AncestorExtension';
		$publishedPlaceholder = ':' . $bindingPrefix . 'AncestorPublished';
		$ancestor = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__categories', 'ancestor_' . $bindingPrefix))
			->where($db->quoteName('ancestor_' . $bindingPrefix . '.extension') . ' = ' . $extensionPlaceholder)
			->where($db->quoteName('ancestor_' . $bindingPrefix . '.lft') . ' < ' . $db->quoteName($categoryAlias . '.lft'))
			->where($db->quoteName('ancestor_' . $bindingPrefix . '.rgt') . ' > ' . $db->quoteName($categoryAlias . '.rgt'))
			->where(
				'(' . $db->quoteName('ancestor_' . $bindingPrefix . '.published') . ' <> ' . $publishedPlaceholder
				. ' OR ' . $db->quoteName('ancestor_' . $bindingPrefix . '.access') . ' NOT IN (' . implode(',', array_map('intval', $levels)) . '))'
			);
		$query->where('NOT EXISTS (' . $ancestor . ')')
			->bind($extensionPlaceholder, $extension, ParameterType::STRING)
			->bind($publishedPlaceholder, $published, ParameterType::INTEGER);
	}

	/**
	 * @brief Convert arrays or comma-separated values to unique positive integers.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int[]
	 */
	private function normaliseIntegerList(mixed $value): array
	{
		$values = is_array($value) ? $value : explode(',', (string) $value);
		return array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0)));
	}

	/**
	 * @brief Parse seconds, MM:SS, or HH:MM:SS into milliseconds.
	 *
	 * @param string $value User input.
	 * @param string $errorKey Language key for invalid input.
	 *
	 * @return int|null
	 */
	private function parseDuration(string $value, string $errorKey): ?int
	{
		if ($value === '')
		{
			return null;
		}

		$parts = explode(':', $value);
		if (count($parts) > 3 || array_filter($parts, static fn(string $part): bool => $part === '' || !ctype_digit($part)))
		{
			$this->filterErrors[] = $errorKey;
			return null;
		}

		$seconds = 0;
		foreach ($parts as $part)
		{
			$seconds = ($seconds * 60) + (int) $part;
		}

		return $seconds * 1000;
	}

	/**
	 * @brief Parse an inclusive ISO date boundary.
	 *
	 * @param string $value User input.
	 * @param bool $endOfDay Whether to use 23:59:59.
	 * @param string $errorKey Language key for invalid input.
	 *
	 * @return string|null
	 */
	private function parseDate(string $value, bool $endOfDay, string $errorKey): ?string
	{
		if ($value === '')
		{
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value)
		{
			$this->filterErrors[] = $errorKey;
			return null;
		}

		return $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0)->format('Y-m-d H:i:s');
	}
}
