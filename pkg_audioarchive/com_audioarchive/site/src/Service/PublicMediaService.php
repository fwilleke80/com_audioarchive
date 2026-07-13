<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Resolve public clips and their protected managed originals.
 */
class PublicMediaService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/**
	 * @brief Construct the public media service.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @param User $user Current visitor.
	 */
	public function __construct(DatabaseInterface $database, Registry $params, User $user)
	{
		$this->database = $database;
		$this->params = $params;
		$this->user = $user;
	}

	/**
	 * @brief Load one published clip visible to the current visitor.
	 *
	 * @param int $id Clip identifier.
	 * @param bool $withTags Whether to load Joomla tags.
	 *
	 * @return object|null Public clip or null.
	 */
	public function getPublicClip(int $id, bool $withTags = true): ?object
	{
		if ($id <= 0)
		{
			return null;
		}

		$database = $this->database;
		$published = 1;
		$available = 1;
		$extension = 'com_audioarchive';
		$fileRole = 'original';
		$now = Factory::getDate()->toSql();
		$levels = $this->getAuthorisedViewLevels();
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('a.id'),
				$database->quoteName('a.uuid'),
				$database->quoteName('a.title'),
				$database->quoteName('a.alias'),
				$database->quoteName('a.description'),
				$database->quoteName('a.original_filename'),
				$database->quoteName('a.duration_ms'),
				$database->quoteName('a.recorded_at'),
				$database->quoteName('a.recorded_date_source'),
				$database->quoteName('a.uploaded_at'),
				$database->quoteName('a.publish_up'),
				$database->quoteName('a.publish_down'),
				$database->quoteName('a.catid'),
				$database->quoteName('a.access'),
				$database->quoteName('a.language'),
				$database->quoteName('a.created_by'),
				$database->quoteName('u.name', 'author_name'),
				$database->quoteName('a.play_count'),
				$database->quoteName('a.download_count'),
				$database->quoteName('a.technical_metadata'),
				$database->quoteName('c.title', 'category_title'),
				$database->quoteName('c.alias', 'category_alias'),
				$database->quoteName('f.id', 'file_id'),
				$database->quoteName('f.storage_key'),
				$database->quoteName('f.file_extension'),
				$database->quoteName('f.mime_type'),
				$database->quoteName('f.container_format'),
				$database->quoteName('f.audio_codec'),
				$database->quoteName('f.file_size'),
				$database->quoteName('f.checksum_sha256'),
			])
			->from($database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$database->quoteName('#__categories', 'c')
				. ' ON ' . $database->quoteName('c.id') . ' = ' . $database->quoteName('a.catid')
			)
			->innerJoin(
				$database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $database->quoteName('f.clip_id') . ' = ' . $database->quoteName('a.id')
				. ' AND ' . $database->quoteName('f.file_role') . ' = :fileRole'
				. ' AND ' . $database->quoteName('f.is_available') . ' = :available'
			)
			->leftJoin(
				$database->quoteName('#__users', 'u')
					. ' ON ' . $database->quoteName('u.id') . ' = ' . $database->quoteName('a.created_by')
			)
			->where($database->quoteName('a.id') . ' = :id')
			->where($database->quoteName('a.state') . ' = :published')
			->where($database->quoteName('c.published') . ' = :categoryPublished')
			->where($database->quoteName('c.extension') . ' = :extension')
			->whereIn($database->quoteName('a.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('c.access'), $levels, ParameterType::INTEGER)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_up') . ' IS NULL',
					$database->quoteName('a.publish_up') . ' <= :publishNow',
				],
				'OR'
			)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_down') . ' IS NULL',
					$database->quoteName('a.publish_down') . ' >= :unpublishNow',
				],
				'OR'
			)
			->bind(':id', $id, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':categoryPublished', $published, ParameterType::INTEGER)
			->bind(':extension', $extension, ParameterType::STRING)
			->bind(':fileRole', $fileRole, ParameterType::STRING)
			->bind(':available', $available, ParameterType::INTEGER)
			->bind(':publishNow', $now, ParameterType::STRING)
			->bind(':unpublishNow', $now, ParameterType::STRING);

		$this->addAncestorCategoryRestrictions($query, 'c', $levels);
		$database->setQuery($query, 0, 1);
		$item = $database->loadObject();

		if (!$item)
		{
			return null;
		}

		$item->tags = $withTags
			? (new TagsHelper())->getItemTags('com_audioarchive.clip', (int) $item->id, true)
			: [];

		return $item;
	}

	/**
	 * @brief Resolve the absolute original-file path without allowing an escape.
	 *
	 * @param object $clip Public clip with a storage_key field.
	 *
	 * @return string Absolute regular-file path.
	 */
	public function resolveOriginalPath(object $clip): string
	{
		$storageKey = str_replace('\\', '/', trim((string) ($clip->storage_key ?? '')));

		if ($storageKey === '' || str_contains($storageKey, "\0") || str_starts_with($storageKey, '/') || preg_match('#(^|/)\.\.(/|$)#', $storageKey))
		{
			throw new \RuntimeException('Invalid managed storage key.');
		}

		$configuredRoot = trim((string) $this->params->get('original_directory', 'audioarchive/originals'));

		if ($configuredRoot === '' || str_contains($configuredRoot, "\0"))
		{
			throw new \RuntimeException('Invalid original storage root.');
		}

		$root = $this->isAbsolutePath($configuredRoot)
			? Path::clean($configuredRoot)
			: Path::clean(JPATH_ROOT . DIRECTORY_SEPARATOR . $configuredRoot);
		$realRoot = realpath($root);

		if ($realRoot === false || !is_dir($realRoot))
		{
			throw new \RuntimeException('Original storage root is unavailable.');
		}

		$realRoot = rtrim(Path::clean($realRoot), DIRECTORY_SEPARATOR);
		$candidate = Path::clean($realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey));
		$realPath = realpath($candidate);

		if ($realPath === false || !is_file($realPath) || is_link($candidate) || !is_readable($realPath))
		{
			throw new \RuntimeException('Original media file is unavailable.');
		}

		$realPath = Path::clean($realPath);
		$prefix = $realRoot . DIRECTORY_SEPARATOR;

		if (!str_starts_with($realPath . DIRECTORY_SEPARATOR, $prefix))
		{
			throw new \RuntimeException('Managed media path escaped its storage root.');
		}

		return $realPath;
	}

	/**
	 * @brief Increment the aggregate playback counter for one authorised clip.
	 *
	 * @param int $id Clip identifier.
	 *
	 * @return void
	 */
	public function incrementPlayCount(int $id): void
	{
		$this->incrementCounter($id, 'play_count');
	}

	/**
	 * @brief Increment the aggregate download counter for one authorised clip.
	 *
	 * @param int $id Clip identifier.
	 *
	 * @return void
	 */
	public function incrementDownloadCount(int $id): void
	{
		$this->incrementCounter($id, 'download_count');
	}

	/**
	 * @brief Increment one allow-listed aggregate counter.
	 *
	 * @param int $id Clip identifier.
	 * @param string $column Counter column.
	 *
	 * @return void
	 */
	private function incrementCounter(int $id, string $column): void
	{
		if ($id <= 0 || !in_array($column, ['play_count', 'download_count'], true))
		{
			return;
		}

		$database = $this->database;
		$query = $database->getQuery(true)
			->update($database->quoteName('#__audioarchive_clips'))
			->set(
				$database->quoteName($column)
				. ' = ' . $database->quoteName($column) . ' + 1'
			)
			->where($database->quoteName('id') . ' = :counterId')
			->bind(':counterId', $id, ParameterType::INTEGER);
		$database->setQuery($query)->execute();
	}

	/**
	 * @brief Return the component parameters.
	 *
	 * @return Registry
	 */
	public function getParams(): Registry
	{
		return $this->params;
	}

	/**
	 * @brief Return authorised access-level identifiers with a safe fallback.
	 *
	 * @return int[]
	 */
	private function getAuthorisedViewLevels(): array
	{
		$levels = array_values(array_unique(array_filter(array_map('intval', $this->user->getAuthorisedViewLevels()), static fn(int $id): bool => $id > 0)));

		return $levels !== [] ? $levels : [1];
	}

	/**
	 * @brief Exclude clips whose category has a hidden ancestor.
	 *
	 * @param object $query Joomla query object.
	 * @param string $categoryAlias Direct category table alias.
	 * @param int[] $levels Authorised access levels.
	 *
	 * @return void
	 */
	private function addAncestorCategoryRestrictions(object $query, string $categoryAlias, array $levels): void
	{
		$database = $this->database;
		$ancestorPublished = 1;
		$ancestorExtension = 'com_audioarchive';
		$ancestor = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__categories', 'ancestor'))
			->where($database->quoteName('ancestor.extension') . ' = :ancestorExtension')
			->where($database->quoteName('ancestor.lft') . ' < ' . $database->quoteName($categoryAlias . '.lft'))
			->where($database->quoteName('ancestor.rgt') . ' > ' . $database->quoteName($categoryAlias . '.rgt'))
			->where(
				'(' . $database->quoteName('ancestor.published') . ' <> :ancestorPublished'
				. ' OR ' . $database->quoteName('ancestor.access') . ' NOT IN (' . implode(',', array_map('intval', $levels)) . '))'
			);
		$query->where('NOT EXISTS (' . $ancestor . ')')
			->bind(':ancestorExtension', $ancestorExtension, ParameterType::STRING)
			->bind(':ancestorPublished', $ancestorPublished, ParameterType::INTEGER);
	}

	/**
	 * @brief Determine whether a configured path is absolute.
	 *
	 * @param string $path Candidate path.
	 *
	 * @return bool
	 */
	private function isAbsolutePath(string $path): bool
	{
		return str_starts_with($path, '/')
			|| str_starts_with($path, '\\')
			|| (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
	}
}
