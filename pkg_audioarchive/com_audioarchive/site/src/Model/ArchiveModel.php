<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

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

	/**
	 * @brief Construct the public list model.
	 *
	 * @param array $config Model configuration.
	 */
	public function __construct($config = [])
	{
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
		$activeTags = $this->ignoreVisitorFiltersForMaximum ? $this->menuTags : $this->selectedTags;
		foreach ($activeTags as $index => $tagId)
		{
			$tagPlaceholder = ':archiveTag' . $index;
			$typePlaceholder = ':archiveType' . $index;
			$tagBindings[$index] = (int) $tagId;
			$typeBindings[$index] = 'com_audioarchive.clip';
			$subquery = $db->getQuery(true)
				->select('1')
				->from($db->quoteName('#__contentitem_tag_map', 'tm' . $index))
				->where($db->quoteName('tm' . $index . '.content_item_id') . ' = ' . $db->quoteName('a.id'))
				->where($db->quoteName('tm' . $index . '.type_alias') . ' = ' . $typePlaceholder)
				->where($db->quoteName('tm' . $index . '.tag_id') . ' = ' . $tagPlaceholder);
			$query->where('EXISTS (' . $subquery . ')')
				->bind($tagPlaceholder, $tagBindings[$index], ParameterType::INTEGER)
				->bind($typePlaceholder, $typeBindings[$index], ParameterType::STRING);
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
	 * @brief Add visible tag data to each paginated clip.
	 *
	 * @return array
	 */
	public function getItems()
	{
		$items = parent::getItems();
		$ids = array_map(static fn(object $item): int => (int) $item->id, $items);
		$tagData = $ids ? (new TagsHelper())->getMultipleItemTags('com_audioarchive.clip', $ids, true) : [];

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

		return $db->setQuery($query)->loadObjectList();
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

		return max(0, (int) $db->setQuery($query)->loadResult());
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
				$db->quoteName('t.path'),
			])
			->order($db->quoteName('t.path') . ' ASC')
			->bind(':typeAlias', $typeAlias, ParameterType::STRING)
			->bind(':published', $published, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
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
		$item = Factory::getApplication()->getMenu()->getActive();
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

		$search = trim($input->getString('q', ''));
		$category = $input->getInt('category', 0);
		$durationMinimumInput = trim($input->getString('duration_min', ''));
		$durationMaximumInput = trim($input->getString('duration_max', ''));
		$recordedFromInput = trim($input->getString('recorded_from', ''));
		$recordedToInput = trim($input->getString('recorded_to', ''));
		$uploadedFromInput = trim($input->getString('uploaded_from', ''));
		$uploadedToInput = trim($input->getString('uploaded_to', ''));

		$this->setState('filter.search', $search);
		$this->setState('filter.category', $category);
		$this->setState('filter.duration_min', $durationMinimumInput);
		$this->setState('filter.duration_max', $durationMaximumInput);
		$this->setState('filter.recorded_from', $recordedFromInput);
		$this->setState('filter.recorded_to', $recordedToInput);
		$this->setState('filter.uploaded_from', $uploadedFromInput);
		$this->setState('filter.uploaded_to', $uploadedToInput);

		$requestedTags = $this->normaliseIntegerList($input->get('tags', [], 'array'));
		$menuTags = $this->normaliseIntegerList($params->get('archive_tag_restriction', []));
		$this->menuTags = $menuTags;
		$this->selectedTags = array_values(array_unique(array_merge($requestedTags, $menuTags)));
		$this->setState('filter.tags', $requestedTags);

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
		$defaultOrdering = (string) $params->get('archive_default_ordering', $params->get('default_ordering', 'uploaded_at'));
		$defaultOrdering = match ($defaultOrdering)
		{
			'uploaded_at' => 'uploaded',
			'recorded_at' => 'recorded',
			default => $defaultOrdering,
		};
		$requestedOrdering = $input->getCmd('sort', $defaultOrdering);
		$this->setState('list.ordering', in_array($requestedOrdering, $allowedOrdering, true) ? $requestedOrdering : 'uploaded');

		$defaultDirection = strtoupper((string) $params->get('archive_default_direction', $params->get('default_direction', 'desc')));
		$requestedDirection = strtoupper($input->getCmd('direction', $defaultDirection));
		$this->setState('list.direction', $requestedDirection === 'ASC' ? 'ASC' : 'DESC');

		$maximumLimit = max(1, min(1000, (int) $params->get('archive_maximum_page_size', 200)));
		$allowedLimits = $this->getPageSizeOptions();
		$defaultLimit = max(1, min($maximumLimit, (int) $params->get('archive_default_limit', $params->get('default_limit', 20))));
		$requestedLimit = max(1, min($maximumLimit, $input->getInt('limit', $defaultLimit)));
		$limit = in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;
		$this->setState('list.limit', $limit);
		$this->setState('list.start', max(0, $input->getInt('limitstart', 0)));
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
