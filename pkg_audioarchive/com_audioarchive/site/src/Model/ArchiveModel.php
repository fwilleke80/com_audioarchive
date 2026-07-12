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

	/** @var Registry|null */
	private ?Registry $resolvedParams = null;

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
				$db->quoteName('c.title', 'category_title'),
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

		$levels = array_values(array_unique(array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels())));
		$query->whereIn($db->quoteName('a.access'), $levels, ParameterType::INTEGER);
		$query->whereIn($db->quoteName('c.access'), $levels, ParameterType::INTEGER);

		$search = trim((string) $this->getState('filter.search'));
		if ($search !== '')
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
		elseif ($categoryId > 0)
		{
			$query->where($db->quoteName('a.catid') . ' = :filterCategory')
				->bind(':filterCategory', $categoryId, ParameterType::INTEGER);
		}

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
			$query->where($db->quoteName('a.duration_ms') . ' <= :durationMaximum')
				->bind(':durationMaximum', $durationMaximum, ParameterType::INTEGER);
		}

		foreach ([
			['filter.recorded_from_sql', 'a.recorded_at', '>=', ':recordedFrom'],
			['filter.recorded_to_sql', 'a.recorded_at', '<=', ':recordedTo'],
			['filter.uploaded_from_sql', 'a.uploaded_at', '>=', ':uploadedFrom'],
			['filter.uploaded_to_sql', 'a.uploaded_at', '<=', ':uploadedTo'],
		] as [$stateKey, $column, $operator, $placeholder])
		{
			$value = $this->getState($stateKey);
			if ($value !== null)
			{
				$value = (string) $value;
				$query->where($db->quoteName($column) . ' ' . $operator . ' ' . $placeholder)
					->bind($placeholder, $value, ParameterType::STRING);
			}
		}

		$tagBindings = [];
		$typeBindings = [];
		foreach ($this->selectedTags as $index => $tagId)
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
		$levels = array_values(array_unique(array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels())));
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				$db->quoteName('level'),
			])
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('extension') . ' = :extension')
			->where($db->quoteName('published') . ' = :published')
			->whereIn($db->quoteName('access'), $levels, ParameterType::INTEGER)
			->order($db->quoteName('lft') . ' ASC')
			->bind(':extension', $extension, ParameterType::STRING)
			->bind(':published', $published, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
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
		$levels = array_values(array_unique(array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels())));
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

		$defaultLimit = max(1, (int) $params->get('archive_default_limit', $params->get('default_limit', 20)));
		$limit = $input->getInt('limit', $defaultLimit);
		$this->setState('list.limit', max(1, min(200, $limit)));
		$this->setState('list.start', max(0, $input->getInt('limitstart', 0)));
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
