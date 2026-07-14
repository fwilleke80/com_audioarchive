<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Category;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\String\StringHelper;

\defined('_JEXEC') or die;

/**
 * @brief Resolve inbox folder paths to Joomla Audio Archive categories.
 */
class ImportCategoryService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var User */
	private User $user;

	/** @var int|null */
	private ?int $rootId = null;

	/**
	 * @brief Construct the category resolver.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param User $user Current administrator.
	 */
	public function __construct(DatabaseInterface $database, User $user)
	{
		$this->database = $database;
		$this->user = $user;
	}

	/**
	 * @brief Preview the category assignment for an inbox file without changing data.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 * @param string $mode Assignment mode: selected or folders.
	 * @param int $baseCategoryId Optional base category.
	 * @param int $fallbackCategoryId Category used for root-level files when no base is selected.
	 * @param bool $createMissing Whether missing folder categories may be created during import.
	 *
	 * @return array{eligible:bool,category_id:int,path:string,missing:string[],will_create:bool,message:string}
	 */
	public function plan(
		string $relativePath,
		string $mode,
		int $baseCategoryId,
		int $fallbackCategoryId,
		bool $createMissing
	): array
	{
		return $this->resolvePath(
			$relativePath,
			$mode,
			$baseCategoryId,
			$fallbackCategoryId,
			$createMissing,
			false
		);
	}

	/**
	 * @brief Resolve and, where allowed, create the category path for an inbox file.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 * @param string $mode Assignment mode: selected or folders.
	 * @param int $baseCategoryId Optional base category.
	 * @param int $fallbackCategoryId Category used for root-level files when no base is selected.
	 * @param bool $createMissing Whether missing folder categories may be created.
	 *
	 * @return array{eligible:bool,category_id:int,path:string,missing:string[],will_create:bool,message:string}
	 */
	public function resolve(
		string $relativePath,
		string $mode,
		int $baseCategoryId,
		int $fallbackCategoryId,
		bool $createMissing
	): array
	{
		return $this->resolvePath(
			$relativePath,
			$mode,
			$baseCategoryId,
			$fallbackCategoryId,
			$createMissing,
			true
		);
	}

	/**
	 * @brief Resolve a category path and optionally create missing nodes.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 * @param string $mode Assignment mode.
	 * @param int $baseCategoryId Optional base category.
	 * @param int $fallbackCategoryId Root-file fallback category.
	 * @param bool $createMissing Whether creation is enabled.
	 * @param bool $performCreation Whether this call may modify categories.
	 *
	 * @return array{eligible:bool,category_id:int,path:string,missing:string[],will_create:bool,message:string}
	 */
	private function resolvePath(
		string $relativePath,
		string $mode,
		int $baseCategoryId,
		int $fallbackCategoryId,
		bool $createMissing,
		bool $performCreation
	): array
	{
		$mode = $mode === 'folders' ? 'folders' : 'selected';
		$baseCategory = $baseCategoryId > 0 ? $this->getCategory($baseCategoryId) : null;
		$fallbackCategory = $fallbackCategoryId > 0 ? $this->getCategory($fallbackCategoryId) : null;

		if ($baseCategoryId > 0 && $baseCategory === null)
		{
			return $this->failure(Text::_('COM_AUDIOARCHIVE_IMPORT_CATEGORY_BASE_INVALID'));
		}

		if ($mode === 'selected')
		{
			$target = $baseCategory ?? $fallbackCategory;

			if ($target === null)
			{
				return $this->failure(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));
			}

			return $this->success((int) $target->id, (string) $target->title, [], false);
		}

		$segments = $this->getFolderSegments($relativePath);

		if ($segments === [])
		{
			$target = $baseCategory ?? $fallbackCategory;

			if ($target === null)
			{
				return $this->failure(Text::_('COM_AUDIOARCHIVE_IMPORT_CATEGORY_ROOT_REQUIRED'));
			}

			return $this->success((int) $target->id, (string) $target->title, [], false);
		}

		$parentId = $baseCategory !== null ? (int) $baseCategory->id : $this->getRootId();
		$pathTitles = $baseCategory !== null ? [(string) $baseCategory->title] : [];
		$missing = [];

		foreach ($segments as $index => $segment)
		{
			$title = $this->normaliseTitle($segment);

			if ($title === '')
			{
				return $this->failure(Text::sprintf('COM_AUDIOARCHIVE_IMPORT_CATEGORY_FOLDER_INVALID', $segment));
			}

			$alias = $this->makeAlias($title, $segment);
			$existing = $this->findCategory($parentId, $title, $alias);

			if ($existing !== null)
			{
				if (!in_array((int) $existing->published, [0, 1], true))
				{
					return $this->failure(Text::sprintf(
						'COM_AUDIOARCHIVE_IMPORT_CATEGORY_UNAVAILABLE',
						(string) $existing->title
					));
				}

				$parentId = (int) $existing->id;
				$pathTitles[] = (string) $existing->title;
				continue;
			}

			$remaining = array_slice($segments, $index);
			$missing = array_map(fn (string $value): string => $this->normaliseTitle($value), $remaining);

			if (!$createMissing)
			{
				$pathTitles = array_merge($pathTitles, $missing);

				return [
					'eligible' => false,
					'category_id' => 0,
					'path' => implode(' / ', $pathTitles),
					'missing' => $missing,
					'will_create' => false,
					'message' => Text::sprintf(
						'COM_AUDIOARCHIVE_IMPORT_CATEGORY_MISSING_DISABLED',
						implode(' / ', $missing)
					),
				];
			}

			if (!$performCreation)
			{
				$pathTitles = array_merge($pathTitles, $missing);

				return [
					'eligible' => true,
					'category_id' => 0,
					'path' => implode(' / ', $pathTitles),
					'missing' => $missing,
					'will_create' => true,
					'message' => '',
				];
			}

			foreach ($remaining as $remainingSegment)
			{
				$remainingTitle = $this->normaliseTitle($remainingSegment);
				$remainingAlias = $this->makeAlias($remainingTitle, $remainingSegment);
				$parentId = $this->createCategory($parentId, $remainingTitle, $remainingAlias);
				$pathTitles[] = $remainingTitle;
			}

			return $this->success($parentId, implode(' / ', $pathTitles), $missing, true);
		}

		return $this->success($parentId, implode(' / ', $pathTitles), [], false);
	}

	/**
	 * @brief Return folder components from a relative source path.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 *
	 * @return string[] Folder names.
	 */
	private function getFolderSegments(string $relativePath): array
	{
		$relativePath = str_replace('\\', '/', trim($relativePath));
		$directory = trim((string) dirname($relativePath), './');

		if ($directory === '')
		{
			return [];
		}

		return array_values(array_filter(
			explode('/', $directory),
			static fn (string $segment): bool => $segment !== '' && $segment !== '.'
		));
	}

	/**
	 * @brief Load one usable Audio Archive category.
	 *
	 * @param int $categoryId Category identifier.
	 *
	 * @return object|null Category row.
	 */
	private function getCategory(int $categoryId): ?object
	{
		$extension = 'com_audioarchive';
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('id'),
				$this->database->quoteName('title'),
				$this->database->quoteName('alias'),
				$this->database->quoteName('published'),
			])
			->from($this->database->quoteName('#__categories'))
			->where($this->database->quoteName('id') . ' = :categoryId')
			->where($this->database->quoteName('extension') . ' = :extension')
			->whereIn($this->database->quoteName('published'), [0, 1])
			->bind(':categoryId', $categoryId, ParameterType::INTEGER)
			->bind(':extension', $extension, ParameterType::STRING);

		$result = $this->database->setQuery($query)->loadObject();

		return is_object($result) ? $result : null;
	}

	/**
	 * @brief Find a category with the same title or alias beneath one parent.
	 *
	 * @param int $parentId Parent category or nested-set root identifier.
	 * @param string $title Proposed title.
	 * @param string $alias Proposed alias.
	 *
	 * @return object|null Matching row.
	 */
	private function findCategory(int $parentId, string $title, string $alias): ?object
	{
		$extension = 'com_audioarchive';
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('id'),
				$this->database->quoteName('title'),
				$this->database->quoteName('alias'),
				$this->database->quoteName('published'),
			])
			->from($this->database->quoteName('#__categories'))
			->where($this->database->quoteName('parent_id') . ' = :parentId')
			->where($this->database->quoteName('extension') . ' = :extension')
			->bind(':parentId', $parentId, ParameterType::INTEGER)
			->bind(':extension', $extension, ParameterType::STRING);
		$rows = (array) $this->database->setQuery($query)->loadObjectList();
		$titleKey = StringHelper::strtolower($title);

		foreach ($rows as $row)
		{
			if ((string) $row->alias === $alias || StringHelper::strtolower((string) $row->title) === $titleKey)
			{
				return $row;
			}
		}

		return null;
	}

	/**
	 * @brief Create one child category.
	 *
	 * @param int $parentId Parent category or nested-set root identifier.
	 * @param string $title Category title.
	 * @param string $alias Category alias.
	 *
	 * @return int New or concurrently created category identifier.
	 */
	private function createCategory(int $parentId, string $title, string $alias): int
	{
		$asset = $parentId === $this->getRootId()
			? 'com_audioarchive'
			: 'com_audioarchive.category.' . $parentId;

		if (!$this->user->authorise('core.create', $asset))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_IMPORT_CATEGORY_CREATE_DENIED', $title), 403);
		}

		$table = new Category($this->database);
		$table->setCurrentUser($this->user);
		$table->setLocation($parentId, 'last-child');
		$data = [
			'parent_id' => $parentId,
			'extension' => 'com_audioarchive',
			'title' => $title,
			'alias' => $alias,
			'description' => '',
			'published' => 1,
			'access' => 1,
			'params' => [],
			'metadata' => [],
			'language' => '*',
		];

		if (!$table->bind($data) || !$table->check() || !$table->store())
		{
			$existing = $this->findCategory($parentId, $title, $alias);

			if ($existing !== null && in_array((int) $existing->published, [0, 1], true))
			{
				return (int) $existing->id;
			}

			throw new \RuntimeException(
				Text::sprintf('COM_AUDIOARCHIVE_IMPORT_CATEGORY_CREATE_FAILED', $title, (string) $table->getError()),
				500
			);
		}

		if (!$table->rebuildPath((int) $table->id))
		{
			throw new \RuntimeException(
				Text::sprintf('COM_AUDIOARCHIVE_IMPORT_CATEGORY_CREATE_FAILED', $title, (string) $table->getError()),
				500
			);
		}

		return (int) $table->id;
	}

	/**
	 * @brief Return Joomla's nested-set root category identifier.
	 *
	 * @return int Root identifier.
	 */
	private function getRootId(): int
	{
		if ($this->rootId !== null)
		{
			return $this->rootId;
		}

		$table = new Category($this->database);
		$table->setCurrentUser($this->user);
		$this->rootId = (int) $table->getRootId();

		if ($this->rootId <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_CATEGORY_ROOT_NOT_FOUND'), 500);
		}

		return $this->rootId;
	}

	/**
	 * @brief Normalise one folder name into a category title.
	 *
	 * @param string $value Folder name.
	 *
	 * @return string Category title.
	 */
	private function normaliseTitle(string $value): string
	{
		$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;

		return trim($value);
	}

	/**
	 * @brief Create a stable Joomla alias from a folder title.
	 *
	 * @param string $title Normalised title.
	 * @param string $original Original folder segment.
	 *
	 * @return string Alias.
	 */
	private function makeAlias(string $title, string $original): string
	{
		$alias = ApplicationHelper::stringURLSafe($title, '*');

		return $alias !== '' ? $alias : 'folder-' . substr(hash('sha256', $original), 0, 12);
	}

	/**
	 * @brief Construct a successful category plan.
	 *
	 * @param int $categoryId Target category identifier.
	 * @param string $path Display path.
	 * @param string[] $missing Missing folder names.
	 * @param bool $willCreate Whether missing categories will be created.
	 *
	 * @return array{eligible:bool,category_id:int,path:string,missing:string[],will_create:bool,message:string}
	 */
	private function success(int $categoryId, string $path, array $missing, bool $willCreate): array
	{
		return [
			'eligible' => true,
			'category_id' => $categoryId,
			'path' => $path,
			'missing' => $missing,
			'will_create' => $willCreate,
			'message' => '',
		];
	}

	/**
	 * @brief Construct a failed category plan.
	 *
	 * @param string $message Failure message.
	 *
	 * @return array{eligible:bool,category_id:int,path:string,missing:string[],will_create:bool,message:string}
	 */
	private function failure(string $message): array
	{
		return [
			'eligible' => false,
			'category_id' => 0,
			'path' => '',
			'missing' => [],
			'will_create' => false,
			'message' => $message,
		];
	}
}
