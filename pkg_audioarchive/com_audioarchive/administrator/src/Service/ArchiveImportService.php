<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Tag;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Joomla\Component\Fields\Administrator\Table\FieldTable;
use Joomla\Component\Fields\Administrator\Table\GroupTable;
use Willeke\Component\Audioarchive\Administrator\Table\ClipTable;

\defined('_JEXEC') or die;

/**
 * @brief Inspect, stage, and restore portable Audio Archive exports.
 */
final class ArchiveImportService
{
	/** @var int */
	private const MAX_ARCHIVE_ENTRIES = 100000;

	/** @var int */
	private const MAX_JSON_ENTRY_SIZE = 268435456;

	/** @var string[] */
	private const RESTORE_MODES = ['empty', 'merge', 'replace'];

	/** @var string[] */
	private const CONFLICT_POLICIES = ['skip', 'metadata', 'all'];

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/** @var string[] */
	private array $createdManagedFiles = [];

	/** @var array<int,array{role:string,storage_key:string}> */
	private array $oldManagedFiles = [];

	/**
	 * @brief Construct the import service.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Audio Archive component parameters.
	 * @param User $user Current administrator.
	 */
	public function __construct(DatabaseInterface $database, Registry $params, User $user)
	{
		$this->database = $database;
		$this->params = $params;
		$this->user = $user;
		$this->storage = new ManagedStorageService($params);
	}

	/**
	 * @brief Determine whether ZIP archive support is available.
	 *
	 * @return bool True when PHP ZipArchive is available.
	 */
	public static function isSupported(): bool
	{
		return ArchiveExportService::isSupported();
	}

	/**
	 * @brief List ZIP files currently present in the configured import inbox.
	 *
	 * @return array<int,array{name:string,size:int,modified:int}>
	 */
	public function listInboxArchives(): array
	{
		try
		{
			$root = $this->storage->ensureDirectory('import');
		}
		catch (\Throwable)
		{
			return [];
		}

		$recursive = (int) $this->params->get('recursive_import', 0) === 1;
		$iterator = $recursive
			? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS))
			: new \IteratorIterator(new \DirectoryIterator($root));
		$archives = [];

		foreach ($iterator as $item)
		{
			if (!$item instanceof \SplFileInfo || !$item->isFile() || $item->isLink())
			{
				continue;
			}

			$path = Path::clean($item->getPathname());
			$relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

			if ($relative === '' || str_starts_with($relative, '.audioarchive-restore/'))
			{
				continue;
			}

			if (strtolower((string) pathinfo($relative, PATHINFO_EXTENSION)) !== 'zip')
			{
				continue;
			}

			$archives[] = [
				'name' => $relative,
				'size' => max(0, (int) $item->getSize()),
				'modified' => max(0, (int) $item->getMTime()),
			];
		}

		usort(
			$archives,
			static fn (array $left, array $right): int => $right['modified'] <=> $left['modified'] ?: strcasecmp($left['name'], $right['name'])
		);

		return $archives;
	}

	/**
	 * @brief Stage an uploaded or inbox ZIP for inspection and restoration.
	 *
	 * @param array<string,mixed>|null $upload PHP upload data.
	 * @param string $inboxRelativePath Selected import-inbox path.
	 *
	 * @return string Opaque staged archive token.
	 */
	public function stage(?array $upload, string $inboxRelativePath): string
	{
		if (!self::isSupported())
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_ZIP_UNAVAILABLE'));
		}

		$stagingRoot = $this->getStagingRoot();
		$token = bin2hex(random_bytes(16)) . '.zip';
		$destination = $stagingRoot . DIRECTORY_SEPARATOR . $token;
		$source = '';
		$moveUploadedFile = false;

		if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
		{
			if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_UPLOAD', (int) ($upload['error'] ?? -1)));
			}

			$originalName = (string) ($upload['name'] ?? '');

			if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip')
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_EXTENSION'));
			}

			$source = (string) ($upload['tmp_name'] ?? '');
			$moveUploadedFile = is_uploaded_file($source);
		}
		elseif (trim($inboxRelativePath) !== '')
		{
			$source = $this->resolveInboxArchive($inboxRelativePath);
		}
		else
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_NO_SOURCE'));
		}

		if (!is_file($source) || !is_readable($source) || is_link($source))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_SOURCE'));
		}

		$moved = $moveUploadedFile && @move_uploaded_file($source, $destination);

		if (!$moved && !@copy($source, $destination))
		{
			@unlink($destination);
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_STAGE'));
		}

		@chmod($destination, 0640);

		try
		{
			$this->inspect($destination);
		}
		catch (\Throwable $exception)
		{
			@unlink($destination);
			throw $exception;
		}

		return $token;
	}

	/**
	 * @brief Inspect a staged archive without changing archive data.
	 *
	 * @param string $path Absolute staged ZIP path.
	 *
	 * @return array<string,mixed> Inspection summary.
	 */
	public function inspect(string $path): array
	{
		if (!self::isSupported())
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_ZIP_UNAVAILABLE'));
		}

		if (!is_file($path) || !is_readable($path) || is_link($path))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_SOURCE'));
		}

		$zip = new \ZipArchive();
		$openResult = $zip->open($path, \ZipArchive::RDONLY);

		if ($openResult !== true)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_OPEN', (string) $openResult));
		}

		try
		{
			if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_ENTRY_COUNT'));
			}

			$compressedSize = 0;
			$uncompressedSize = 0;
			$entries = [];

			for ($index = 0; $index < $zip->numFiles; $index++)
			{
				$stat = $zip->statIndex($index);

				if (!is_array($stat))
				{
					throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_ENTRY'));
				}

				$name = (string) ($stat['name'] ?? '');
				$this->assertSafeEntryName($name);
				$this->assertNotSymlink($zip, $index, $name);
				$entries[$name] = true;
				$compressedSize += max(0, (int) ($stat['comp_size'] ?? 0));
				$uncompressedSize += max(0, (int) ($stat['size'] ?? 0));
			}

			foreach (['manifest.json', 'checksums.json', 'data/clips.json', 'data/categories.json', 'data/tags.json', 'data/files.json', 'data/analyses.json'] as $required)
			{
				if (!isset($entries[$required]))
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_REQUIRED_ENTRY', $required));
				}
			}

			$manifest = $this->readJson($zip, 'manifest.json');

			if ((string) ($manifest['format'] ?? '') !== ArchiveExportService::FORMAT_NAME)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_FORMAT'));
			}

			if ((int) ($manifest['format_version'] ?? 0) !== ArchiveExportService::FORMAT_VERSION)
			{
				throw new \RuntimeException(Text::sprintf(
					'COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_VERSION',
					(int) ($manifest['format_version'] ?? 0),
					ArchiveExportService::FORMAT_VERSION
				));
			}

			$checksums = $this->readJson($zip, 'checksums.json');

			if (!is_array($checksums))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CHECKSUMS'));
			}

			foreach ($checksums as $entryName => $expectedChecksum)
			{
				$entryName = (string) $entryName;
				$this->assertSafeEntryName($entryName);

				if (!isset($entries[$entryName]))
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CHECKSUM_ENTRY', $entryName));
				}

				$actualChecksum = $this->hashEntry($zip, $entryName);

				if (!hash_equals(strtolower((string) $expectedChecksum), strtolower($actualChecksum)))
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CHECKSUM_MISMATCH', $entryName));
				}
			}

			$warnings = [];
			$componentVersion = trim((string) ($manifest['component_version'] ?? ''));

			if ($componentVersion === '')
			{
				$warnings[] = Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_WARNING_NO_COMPONENT_VERSION');
			}

			$counts = is_array($manifest['counts'] ?? null) ? $manifest['counts'] : [];
			$scope = (string) ($manifest['scope'] ?? 'metadata');

			return [
				'manifest' => $manifest,
				'counts' => $counts,
				'scope' => $scope,
				'compressed_size' => $compressedSize,
				'uncompressed_size' => $uncompressedSize,
				'archive_size' => max(0, (int) filesize($path)),
				'entry_count' => $zip->numFiles,
				'checksums_verified' => count($checksums),
				'warnings' => $warnings,
				'fingerprint_sha256' => (string) hash_file('sha256', $path),
			];
		}
		finally
		{
			$zip->close();
		}
	}

	/**
	 * @brief Resolve one opaque staged token to an absolute path.
	 *
	 * @param string $token Staged token.
	 *
	 * @return string Absolute staged ZIP path.
	 */
	public function getStagedPath(string $token): string
	{
		if (!preg_match('/^[0-9a-f]{32}\.zip$/', $token))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_TOKEN'));
		}

		$root = $this->getStagingRoot();
		$path = Path::clean($root . DIRECTORY_SEPARATOR . $token);

		if (dirname($path) !== Path::clean($root) || !is_file($path) || is_link($path))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_TOKEN'));
		}

		return $path;
	}

	/**
	 * @brief Remove one staged archive.
	 *
	 * @param string $token Staged token.
	 *
	 * @return void
	 */
	public function deleteStaged(string $token): void
	{
		try
		{
			$path = $this->getStagedPath($token);
			@unlink($path);
		}
		catch (\Throwable)
		{
			// Clearing an already missing inspection is harmless.
		}
	}

	/**
	 * @brief Restore one inspected archive.
	 *
	 * @param string $path Absolute staged ZIP path.
	 * @param string $restoreMode Empty, merge, or replace.
	 * @param string $conflictPolicy Skip, metadata, or all.
	 * @param bool $restoreConfiguration Whether portable component settings are restored.
	 *
	 * @return array<string,mixed> Restore summary.
	 */
	public function restore(
		string $path,
		string $restoreMode,
		string $conflictPolicy,
		bool $restoreConfiguration
	): array
	{
		$restoreMode = strtolower(trim($restoreMode));
		$conflictPolicy = strtolower(trim($conflictPolicy));

		if (!in_array($restoreMode, self::RESTORE_MODES, true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_MODE'));
		}

		if (!in_array($conflictPolicy, self::CONFLICT_POLICIES, true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CONFLICT_POLICY'));
		}

		$inspection = $this->inspect($path);
		$zip = new \ZipArchive();
		$openResult = $zip->open($path, \ZipArchive::RDONLY);

		if ($openResult !== true)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_OPEN', (string) $openResult));
		}

		$data = [];

		try
		{
			foreach ([
				'categories',
				'tags',
				'clips',
				'tag-relations',
				'configuration',
				'acl',
				'custom-field-groups',
				'custom-fields',
				'custom-field-values',
				'custom-field-categories',
				'files',
				'analyses',
				'waveforms',
			] as $name)
			{
				$entry = 'data/' . $name . '.json';
				$data[$name] = $zip->locateName($entry) === false ? [] : $this->readJson($zip, $entry);
			}

			$currentClipCount = $this->getClipCount();

			if ($restoreMode === 'empty' && $currentClipCount > 0)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_NOT_EMPTY', $currentClipCount));
			}

			$this->createdManagedFiles = [];
			$this->oldManagedFiles = [];
			$result = [
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'categories_created' => 0,
				'tags_created' => 0,
				'files_restored' => 0,
				'analyses_restored' => 0,
				'custom_field_values_restored' => 0,
				'configuration_restored' => false,
				'warnings' => [],
				'smart_search_reindex_required' => true,
			];
			$this->database->transactionStart();

			try
			{
				if ($restoreMode === 'replace')
				{
					$this->prepareReplacement();
				}

				$accessMap = $this->loadAccessLevelMap();
				$userMap = $this->loadUserMap();
				$categoryMap = $this->restoreCategories((array) $data['categories'], $accessMap, $userMap, $result);
				$tagMap = $this->restoreTags((array) $data['tags'], $accessMap, $userMap, $result);
				$fieldMap = $this->restoreCustomFieldDefinitions(
					(array) $data['custom-field-groups'],
					(array) $data['custom-fields'],
					$accessMap,
					$userMap
				);
				$this->restoreCustomFieldCategories(
					(array) $data['custom-field-categories'],
					(array) $data['custom-fields'],
					$fieldMap,
					$categoryMap
				);
				$clipResult = $this->restoreClips(
					(array) $data['clips'],
					$categoryMap,
					$accessMap,
					$userMap,
					$restoreMode,
					$conflictPolicy,
					(array) $data['configuration'],
					$result
				);
				$this->restoreTagsForClips(
					(array) $data['tag-relations'],
					$clipResult['uuid_to_id'],
					$tagMap,
					$clipResult['mutable_uuids']
				);
				$this->restoreFiles(
					$zip,
					(array) $data['files'],
					$clipResult,
					$restoreMode,
					$conflictPolicy,
					$result
				);
				$analysisStorageKeys = $this->restoreAnalyses(
					$zip,
					(array) $data['analyses'],
					$clipResult,
					$conflictPolicy,
					$result
				);
				$this->restoreWaveforms(
					(array) $data['waveforms'],
					$clipResult,
					$analysisStorageKeys,
					$conflictPolicy
				);
				$result['custom_field_values_restored'] = $this->restoreCustomFieldValues(
					(array) $data['custom-field-values'],
					$fieldMap,
					$clipResult['uuid_to_id'],
					$clipResult['mutable_uuids']
				);
				$this->restoreAcl((array) $data['acl'], $categoryMap, $clipResult['uuid_to_id']);

				if ($restoreConfiguration)
				{
					$this->restoreConfiguration((array) $data['configuration'], $categoryMap, $accessMap);
					$result['configuration_restored'] = true;
				}

				$this->database->transactionCommit();
			}
			catch (\Throwable $exception)
			{
				$this->database->transactionRollback();
				$this->cleanupCreatedFiles();
				throw $exception;
			}

			$this->cleanupOldFiles();
			$result['inspection'] = $inspection;

			return $result;
		}
		finally
		{
			$zip->close();
		}
	}

	/**
	 * @brief Restore categories and return source-key mappings.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported categories.
	 * @param array<string,int> $accessMap Access level IDs by lowercase title.
	 * @param array<string,int> $userMap User IDs by lowercase username.
	 * @param array<string,mixed> $result Restore summary.
	 *
	 * @return array<string,int> Target category IDs by source key.
	 */
	private function restoreCategories(array $rows, array $accessMap, array $userMap, array &$result): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('path')])
			->from($this->database->quoteName('#__categories'))
			->where($this->database->quoteName('extension') . ' = ' . $this->database->quote('com_audioarchive'));
		$map = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$map[(string) $row['path']] = (int) $row['id'];
		}

		usort(
			$rows,
			static fn (array $left, array $right): int => substr_count((string) ($left['key'] ?? ''), '/') <=> substr_count((string) ($right['key'] ?? ''), '/')
		);

		foreach ($rows as $row)
		{
			$key = trim((string) ($row['key'] ?? ''));

			if ($key === '')
			{
				continue;
			}

			if (isset($map[$key]))
			{
				continue;
			}

			$parentKey = trim((string) ($row['parent_key'] ?? ''));
			$parentId = $parentKey !== '' ? (int) ($map[$parentKey] ?? 0) : 1;

			if ($parentId <= 0)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CATEGORY_PARENT', $key));
			}

			$category = new Category($this->database);
			$category->setCurrentUser($this->user);
			$category->setLocation($parentId, 'last-child');
			$data = $this->normalisePortableRow($row);
			$data['parent_id'] = $parentId;
			$data['extension'] = 'com_audioarchive';
			$data['access'] = $this->resolveAccess((string) ($row['access_level_title'] ?? ''), $accessMap);
			$data['created_user_id'] = $this->resolveUser((string) ($row['created_by_username'] ?? ''), $userMap);
			$data['modified_user_id'] = $this->resolveUser((string) ($row['modified_by_username'] ?? ''), $userMap, 0);
			$data['params'] = $this->decodeRegistryValue($row['params'] ?? '{}');
			$data['metadata'] = $this->decodeRegistryValue($row['metadata'] ?? '{}');
			unset(
				$data['key'],
				$data['parent_key'],
				$data['access_level_title'],
				$data['created_by_username'],
				$data['modified_by_username']
			);

			if (!$category->bind($data) || !$category->check() || !$category->store())
			{
				throw new \RuntimeException($category->getError() ?: Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CATEGORY', $key));
			}

			$map[$key] = (int) $category->id;
			$result['categories_created']++;
		}

		return $map;
	}

	/**
	 * @brief Restore tags and return source-key mappings.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported tags.
	 * @param array<string,int> $accessMap Access level IDs by lowercase title.
	 * @param array<string,int> $userMap User IDs by lowercase username.
	 * @param array<string,mixed> $result Restore summary.
	 *
	 * @return array<string,int> Target tag IDs by source key.
	 */
	private function restoreTags(array $rows, array $accessMap, array $userMap, array &$result): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('path')])
			->from($this->database->quoteName('#__tags'))
			->where($this->database->quoteName('id') . ' > 1');
		$map = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$map[(string) $row['path']] = (int) $row['id'];
		}

		usort(
			$rows,
			static fn (array $left, array $right): int => substr_count((string) ($left['key'] ?? ''), '/') <=> substr_count((string) ($right['key'] ?? ''), '/')
		);

		foreach ($rows as $row)
		{
			$key = trim((string) ($row['key'] ?? ''));

			if ($key === '' || isset($map[$key]))
			{
				continue;
			}

			$parentKey = trim((string) ($row['parent_key'] ?? ''));
			$parentId = $parentKey !== '' ? (int) ($map[$parentKey] ?? 0) : 1;

			if ($parentId <= 0)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_TAG_PARENT', $key));
			}

			$tag = new Tag($this->database);

			if (method_exists($tag, 'setCurrentUser'))
			{
				$tag->setCurrentUser($this->user);
			}

			$tag->setLocation($parentId, 'last-child');
			$data = $this->normalisePortableRow($row);
			$data['parent_id'] = $parentId;
			$data['access'] = $this->resolveAccess((string) ($row['access_level_title'] ?? ''), $accessMap);
			$data['created_user_id'] = $this->resolveUser((string) ($row['created_by_username'] ?? ''), $userMap);
			$data['modified_user_id'] = $this->resolveUser((string) ($row['modified_by_username'] ?? ''), $userMap, 0);
			$data['params'] = $this->decodeRegistryValue($row['params'] ?? '{}');
			$data['metadata'] = $this->decodeRegistryValue($row['metadata'] ?? '{}');
			unset(
				$data['key'],
				$data['parent_key'],
				$data['access_level_title'],
				$data['created_by_username'],
				$data['modified_by_username']
			);

			if (!$tag->bind($data) || !$tag->check() || !$tag->store())
			{
				throw new \RuntimeException($tag->getError() ?: Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_TAG', $key));
			}

			$map[$key] = (int) $tag->id;
			$result['tags_created']++;
		}

		return $map;
	}

	/**
	 * @brief Restore clips and establish UUID mappings and update permissions.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported clips.
	 * @param array<string,int> $categoryMap Category IDs by source key.
	 * @param array<string,int> $accessMap Access level IDs by lowercase title.
	 * @param array<string,int> $userMap User IDs by lowercase username.
	 * @param string $restoreMode Restore mode.
	 * @param string $conflictPolicy Merge conflict policy.
	 * @param array<string,mixed> $configuration Exported configuration.
	 * @param array<string,mixed> $result Restore summary.
	 *
	 * @return array{uuid_to_id:array<string,int>,mutable_uuids:array<string,bool>,allow_files:array<string,bool>,new_uuids:array<string,bool>}
	 */
	private function restoreClips(
		array $rows,
		array $categoryMap,
		array $accessMap,
		array $userMap,
		string $restoreMode,
		string $conflictPolicy,
		array $configuration,
		array &$result
	): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('uuid')])
			->from($this->database->quoteName('#__audioarchive_clips'));
		$uuidToId = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$uuidToId[strtolower((string) $row['uuid'])] = (int) $row['id'];
		}

		$mutable = [];
		$allowFiles = [];
		$newUuids = [];
		$fallbackCategory = $this->resolveFallbackCategory($categoryMap, $configuration);

		foreach ($rows as $row)
		{
			$uuid = strtolower(trim((string) ($row['uuid'] ?? '')));

			if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid))
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_UUID', $uuid));
			}

			$existingId = (int) ($uuidToId[$uuid] ?? 0);
			$isNew = $existingId <= 0;

			if (!$isNew && $restoreMode === 'merge' && $conflictPolicy === 'skip')
			{
				$result['skipped']++;
				$allowFiles[$uuid] = false;
				continue;
			}

			$table = new ClipTable($this->database, Factory::getApplication()->getDispatcher());
			$table->setCurrentUser($this->user);

			if (!$isNew && !$table->load($existingId))
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CLIP_LOAD', $uuid));
			}

			$data = $this->normalisePortableRow($row);
			$data['id'] = $isNew ? 0 : $existingId;
			$data['uuid'] = $uuid;
			$data['catid'] = (int) ($categoryMap[(string) ($row['category_key'] ?? '')] ?? $fallbackCategory);
			$data['access'] = $this->resolveAccess((string) ($row['access_level_title'] ?? ''), $accessMap);
			$data['created_by'] = $this->resolveUser((string) ($row['created_by_username'] ?? ''), $userMap);
			$data['modified_by'] = $this->resolveUser((string) ($row['modified_by_username'] ?? ''), $userMap, 0);
			$data['checked_out'] = null;
			$data['checked_out_time'] = null;
			unset(
				$data['category_key'],
				$data['access_level_title'],
				$data['created_by_username'],
				$data['modified_by_username']
			);

			if ((int) $data['catid'] <= 0)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CLIP_CATEGORY', $uuid));
			}

			if ($isNew && $this->aliasExists((string) ($data['alias'] ?? ''), (int) $data['catid']))
			{
				$data['alias'] = rtrim((string) ($data['alias'] ?? ''), '-') . '-' . substr(str_replace('-', '', $uuid), 0, 8);
			}

			if (!$isNew && $restoreMode === 'merge' && $conflictPolicy === 'metadata')
			{
				foreach ([
					'original_filename',
					'duration_ms',
					'uploaded_at',
					'play_count',
					'download_count',
					'metadata_status',
					'preview_status',
					'waveform_status',
					'spectrogram_status',
					'technical_metadata',
				] as $preservedField)
				{
					if (property_exists($table, $preservedField))
					{
						$data[$preservedField] = $table->{$preservedField};
					}
				}
			}

			if (!$table->bind($data) || !$table->check() || !$table->store(true))
			{
				throw new \RuntimeException($table->getError() ?: Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CLIP_STORE', $uuid));
			}

			$clipId = (int) $table->id;
			$uuidToId[$uuid] = $clipId;
			$mutable[$uuid] = true;
			$allowFiles[$uuid] = $isNew || $restoreMode !== 'merge' || $conflictPolicy === 'all';

			if ($isNew)
			{
				$newUuids[$uuid] = true;
				$result['created']++;
			}
			else
			{
				$result['updated']++;
			}
		}

		return [
			'uuid_to_id' => $uuidToId,
			'mutable_uuids' => $mutable,
			'allow_files' => $allowFiles,
			'new_uuids' => $newUuids,
		];
	}

	/**
	 * @brief Restore clip tag assignments through Joomla's tag helper.
	 *
	 * @param array<int,array<string,mixed>> $relations Exported relations.
	 * @param array<string,int> $clipMap Clip IDs by UUID.
	 * @param array<string,int> $tagMap Tag IDs by source key.
	 * @param array<string,bool> $mutable Mutable clip UUID set.
	 *
	 * @return void
	 */
	private function restoreTagsForClips(array $relations, array $clipMap, array $tagMap, array $mutable): void
	{
		$byClip = [];

		foreach ($relations as $relation)
		{
			$uuid = strtolower((string) ($relation['clip_uuid'] ?? ''));
			$tagKey = (string) ($relation['tag_key'] ?? '');
			$tagId = (int) ($tagMap[$tagKey] ?? 0);

			if ($uuid !== '' && $tagId > 0)
			{
				$byClip[$uuid][] = $tagId;
			}
		}

		foreach ($mutable as $uuid => $_)
		{
			$clipId = (int) ($clipMap[$uuid] ?? 0);

			if ($clipId <= 0)
			{
				continue;
			}

			$table = new ClipTable($this->database, Factory::getApplication()->getDispatcher());
			$table->setCurrentUser($this->user);

			if (!$table->load($clipId))
			{
				continue;
			}

			$tagIds = array_values(array_unique(array_map('intval', $byClip[$uuid] ?? [])));
			$helper = new TagsHelper();
			$helper->typeAlias = 'com_audioarchive.clip';
			$helper->preStoreProcess($table, $tagIds);

			if (!$helper->postStore($table, $tagIds, true))
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_TAG_RELATION', $uuid));
			}
		}
	}

	/**
	 * @brief Restore file records and included original or preview files.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param array<int,array<string,mixed>> $rows Exported file rows.
	 * @param array<string,mixed> $clipResult Clip restore mappings.
	 * @param string $restoreMode Restore mode.
	 * @param string $conflictPolicy Conflict policy.
	 * @param array<string,mixed> $result Restore summary.
	 *
	 * @return void
	 */
	private function restoreFiles(
		\ZipArchive $zip,
		array $rows,
		array $clipResult,
		string $restoreMode,
		string $conflictPolicy,
		array &$result
	): void
	{
		foreach ($rows as $row)
		{
			$uuid = strtolower((string) ($row['clip_uuid'] ?? ''));
			$clipId = (int) ($clipResult['uuid_to_id'][$uuid] ?? 0);
			$allowFiles = (bool) ($clipResult['allow_files'][$uuid] ?? false);
			$isNew = isset($clipResult['new_uuids'][$uuid]);

			if ($clipId <= 0 || (!$allowFiles && !$isNew))
			{
				continue;
			}

			$role = strtolower(trim((string) ($row['file_role'] ?? '')));

			if (!in_array($role, ['original', 'preview'], true))
			{
				continue;
			}

			$existing = $this->loadFileRow($clipId, $role);
			$archivePath = trim((string) ($row['archive_path'] ?? ''));
			$newStorageKey = '';
			$included = $archivePath !== '' && $zip->locateName($archivePath) !== false;

			if ($included)
			{
				$temporaryPath = $this->extractEntryToTemporaryFile($zip, $archivePath);
				$extension = strtolower(trim((string) ($row['file_extension'] ?? pathinfo($archivePath, PATHINFO_EXTENSION))));

				try
				{
					if ($role === 'original')
					{
						$freshKeyRequired = $existing !== null || $restoreMode === 'replace';
						$stored = $freshKeyRequired
							? $this->storage->storeReplacementOriginal($temporaryPath, $uuid, $extension, true)
							: $this->storage->storeOriginal($temporaryPath, $uuid, $extension, true);
					}
					else
					{
						$stored = $this->storePreviewFile($temporaryPath, $uuid, $extension);
					}
				}
				finally
				{
					@unlink($temporaryPath);
				}

				$newStorageKey = (string) $stored['storage_key'];
				$this->createdManagedFiles[] = (string) $stored['absolute_path'];
				$result['files_restored']++;
			}
			elseif ($existing !== null)
			{
				// A metadata-only export never removes a currently usable target file.
				continue;
			}

			if ($existing !== null && trim((string) $existing->storage_key) !== '' && $newStorageKey !== '')
			{
				$this->oldManagedFiles[] = ['role' => $role, 'storage_key' => (string) $existing->storage_key];
			}

			$record = $this->normalisePortableRow($row);
			$record['clip_id'] = $clipId;
			$record['file_role'] = $role;
			$record['storage_key'] = $newStorageKey;
			$record['is_available'] = $newStorageKey !== '' ? 1 : 0;
			$record['processing_error'] = $newStorageKey !== ''
				? (string) ($row['processing_error'] ?? '')
				: Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_FILE_NOT_INCLUDED');
			unset(
				$record['clip_uuid'],
				$record['archive_path'],
				$record['file_included'],
				$record['source_storage_key']
			);
			$this->upsertById('#__audioarchive_files', $record, $existing?->id ?? 0);
		}
	}

	/**
	 * @brief Restore generic analysis records and included data files.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param array<int,array<string,mixed>> $rows Exported analyses.
	 * @param array<string,mixed> $clipResult Clip restore mappings.
	 * @param string $conflictPolicy Conflict policy.
	 * @param array<string,mixed> $result Restore summary.
	 *
	 * @return array<string,string> New storage keys indexed by "UUID:type".
	 */
	private function restoreAnalyses(
		\ZipArchive $zip,
		array $rows,
		array $clipResult,
		string $conflictPolicy,
		array &$result
	): array
	{
		$storageKeys = [];

		foreach ($rows as $row)
		{
			$uuid = strtolower((string) ($row['clip_uuid'] ?? ''));
			$clipId = (int) ($clipResult['uuid_to_id'][$uuid] ?? 0);
			$allowFiles = (bool) ($clipResult['allow_files'][$uuid] ?? false);
			$isNew = isset($clipResult['new_uuids'][$uuid]);

			if ($clipId <= 0 || (!$allowFiles && !$isNew))
			{
				continue;
			}

			$type = strtolower(trim((string) ($row['analysis_type'] ?? '')));

			if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $type))
			{
				continue;
			}

			$existing = $this->loadAnalysisRow($clipId, $type);
			$archivePath = trim((string) ($row['archive_path'] ?? ''));
			$newStorageKey = '';
			$included = $archivePath !== '' && $zip->locateName($archivePath) !== false;

			if ($included)
			{
				$temporaryPath = $this->extractEntryToTemporaryFile($zip, $archivePath);
				$extension = strtolower((string) pathinfo($archivePath, PATHINFO_EXTENSION));

				try
				{
					$stored = $this->storage->storeAnalysisFile($temporaryPath, $uuid, $type, $extension);
				}
				finally
				{
					@unlink($temporaryPath);
				}

				$newStorageKey = (string) $stored['storage_key'];
				$this->createdManagedFiles[] = (string) $stored['absolute_path'];
				$result['analyses_restored']++;
			}
			elseif ($existing !== null)
			{
				// Metadata-only imports leave existing generated data untouched.
				continue;
			}

			if ($existing !== null && trim((string) $existing->storage_key) !== '' && $newStorageKey !== '')
			{
				$this->oldManagedFiles[] = ['role' => 'analysis', 'storage_key' => (string) $existing->storage_key];
			}

			$record = $this->normalisePortableRow($row);
			$record['clip_id'] = $clipId;
			$record['analysis_type'] = $type;
			$record['storage_key'] = $newStorageKey;
			$record['file_size'] = $newStorageKey !== ''
				? max(0, (int) filesize($this->storage->resolveManagedPath('analysis', $newStorageKey)))
				: 0;
			$record['is_available'] = $newStorageKey !== '' ? 1 : 0;

			if ($newStorageKey === '' && (string) ($record['status'] ?? '') === 'available')
			{
				$record['status'] = 'missing';
			}

			unset(
				$record['clip_uuid'],
				$record['archive_path'],
				$record['file_included'],
				$record['source_storage_key']
			);
			$this->upsertById('#__audioarchive_analyses', $record, $existing?->id ?? 0);
			$storageKeys[$uuid . ':' . $type] = $newStorageKey;
			$this->updateClipAnalysisStatus($clipId, $type, (string) ($record['status'] ?? 'missing'));
		}

		return $storageKeys;
	}

	/**
	 * @brief Restore the compatibility waveform projection.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported waveforms.
	 * @param array<string,mixed> $clipResult Clip restore mappings.
	 * @param array<string,string> $analysisStorageKeys New generic-analysis keys.
	 * @param string $conflictPolicy Conflict policy.
	 *
	 * @return void
	 */
	private function restoreWaveforms(
		array $rows,
		array $clipResult,
		array $analysisStorageKeys,
		string $conflictPolicy
	): void
	{
		foreach ($rows as $row)
		{
			$uuid = strtolower((string) ($row['clip_uuid'] ?? ''));
			$clipId = (int) ($clipResult['uuid_to_id'][$uuid] ?? 0);
			$allowFiles = (bool) ($clipResult['allow_files'][$uuid] ?? false);
			$isNew = isset($clipResult['new_uuids'][$uuid]);

			if ($clipId <= 0 || (!$allowFiles && !$isNew))
			{
				continue;
			}

			$storageKey = (string) ($analysisStorageKeys[$uuid . ':waveform'] ?? '');
			$existing = $this->loadWaveformRow($clipId);

			if ($storageKey === '' && $existing !== null)
			{
				continue;
			}

			$record = $this->normalisePortableRow($row);
			$record['clip_id'] = $clipId;
			$record['storage_key'] = $storageKey;
			$record['is_available'] = $storageKey !== '' ? 1 : 0;
			unset($record['clip_uuid'], $record['archive_path'], $record['source_storage_key']);
			$this->upsertById('#__audioarchive_waveforms', $record, $existing?->id ?? 0);
		}
	}

	/**
	 * @brief Restore Custom Field definitions using portable names and titles.
	 *
	 * @param array<int,array<string,mixed>> $groupRows Exported groups.
	 * @param array<int,array<string,mixed>> $fieldRows Exported fields.
	 * @param array<string,int> $accessMap Access map.
	 * @param array<string,int> $userMap User map.
	 *
	 * @return array<string,int> Field IDs by source key.
	 */
	private function restoreCustomFieldDefinitions(
		array $groupRows,
		array $fieldRows,
		array $accessMap,
		array $userMap
	): array
	{
		if (!$this->tableExists('#__fields') || !$this->tableExists('#__fields_groups'))
		{
			return [];
		}

		Factory::getApplication()->bootComponent('com_fields');
		$context = 'com_audioarchive.clip';
		$userGroupMap = $this->loadUserGroupMap();
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('title')])
			->from($this->database->quoteName('#__fields_groups'))
			->where($this->database->quoteName('context') . ' = :context')
			->bind(':context', $context, ParameterType::STRING);
		$groupMap = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$groupMap[(string) $row['title']] = (int) $row['id'];
		}

		foreach ($groupRows as $row)
		{
			$key = (string) ($row['key'] ?? $row['title'] ?? '');

			if ($key === '' || isset($groupMap[$key]))
			{
				continue;
			}

			$record = $this->normalisePortableRow($row);
			$record['context'] = $context;
			$record['access'] = $this->resolveAccess((string) ($row['access_level_title'] ?? ''), $accessMap);
			$record['created_by'] = $this->resolveUser((string) ($row['created_by_username'] ?? ''), $userMap);
			$record['modified_by'] = $this->resolveUser((string) ($row['modified_by_username'] ?? ''), $userMap, 0);
			$record['rules'] = $this->mapPortableRules($row['rules'] ?? [], $userGroupMap);
			unset($record['key'], $record['access_level_title'], $record['created_by_username'], $record['modified_by_username']);
			$table = new GroupTable($this->database, Factory::getApplication()->getDispatcher());
			$table->setCurrentUser($this->user);

			if (!$table->bind($record) || !$table->check() || !$table->store(true))
			{
				throw new \RuntimeException($table->getError());
			}

			$groupMap[$key] = (int) $table->id;
		}

		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('name')])
			->from($this->database->quoteName('#__fields'))
			->where($this->database->quoteName('context') . ' = :context')
			->bind(':context', $context, ParameterType::STRING);
		$fieldMap = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$fieldMap[(string) $row['name']] = (int) $row['id'];
		}

		foreach ($fieldRows as $row)
		{
			$key = (string) ($row['key'] ?? $row['name'] ?? '');

			if ($key === '' || isset($fieldMap[$key]))
			{
				continue;
			}

			$record = $this->normalisePortableRow($row);
			$record['context'] = $context;
			$record['group_id'] = (int) ($groupMap[(string) ($row['group_key'] ?? '')] ?? 0);
			$record['access'] = $this->resolveAccess((string) ($row['access_level_title'] ?? ''), $accessMap);
			$record['created_user_id'] = $this->resolveUser((string) ($row['created_by_username'] ?? ''), $userMap);
			$record['modified_by'] = $this->resolveUser((string) ($row['modified_by_username'] ?? ''), $userMap, 0);
			$record['rules'] = $this->mapPortableRules($row['rules'] ?? [], $userGroupMap);
			unset(
				$record['key'],
				$record['group_key'],
				$record['access_level_title'],
				$record['created_by_username'],
				$record['modified_by_username']
			);
			$table = new FieldTable($this->database, Factory::getApplication()->getDispatcher());
			$table->setCurrentUser($this->user);

			if (!$table->bind($record) || !$table->check() || !$table->store(true))
			{
				throw new \RuntimeException($table->getError());
			}

			$fieldMap[$key] = (int) $table->id;
		}

		return $fieldMap;
	}


	/**
	 * @brief Restore Custom Field category assignments.
	 *
	 * No assignment rows means that a field applies to all categories. Joomla's
	 * special category identifier -1 is preserved for fields assigned to none.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported assignments.
	 * @param array<int,array<string,mixed>> $fieldRows Exported field definitions.
	 * @param array<string,int> $fieldMap Target field IDs by key.
	 * @param array<string,int> $categoryMap Target category IDs by key.
	 *
	 * @return void
	 */
	private function restoreCustomFieldCategories(
		array $rows,
		array $fieldRows,
		array $fieldMap,
		array $categoryMap
	): void
	{
		if (!$this->tableExists('#__fields_categories') || $fieldMap === [])
		{
			return;
		}

		$exportedFieldIds = [];

		foreach ($fieldRows as $fieldRow)
		{
			$key = (string) ($fieldRow['key'] ?? $fieldRow['name'] ?? '');
			$fieldId = (int) ($fieldMap[$key] ?? 0);

			if ($fieldId > 0)
			{
				$exportedFieldIds[$fieldId] = true;
			}
		}

		if ($exportedFieldIds === [])
		{
			return;
		}

		$query = $this->database->getQuery(true)
			->delete($this->database->quoteName('#__fields_categories'))
			->whereIn($this->database->quoteName('field_id'), array_keys($exportedFieldIds), ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
		$seen = [];

		foreach ($rows as $row)
		{
			$fieldId = (int) ($fieldMap[(string) ($row['field_key'] ?? '')] ?? 0);
			$specialCategoryId = (int) ($row['special_category_id'] ?? 0);
			$categoryKey = (string) ($row['category_key'] ?? '');
			$categoryId = $specialCategoryId < 0
				? $specialCategoryId
				: (int) ($categoryMap[$categoryKey] ?? 0);

			if ($fieldId <= 0 || $categoryId === 0)
			{
				continue;
			}

			$key = $fieldId . ':' . $categoryId;

			if (isset($seen[$key]))
			{
				continue;
			}

			$this->database->insertObject('#__fields_categories', (object) [
				'field_id' => $fieldId,
				'category_id' => $categoryId,
			]);
			$seen[$key] = true;
		}
	}

	/**
	 * @brief Restore Custom Field values for mutable clips.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported values.
	 * @param array<string,int> $fieldMap Field IDs by key.
	 * @param array<string,int> $clipMap Clip IDs by UUID.
	 * @param array<string,bool> $mutable Mutable UUID set.
	 *
	 * @return int Number of restored value rows.
	 */
	private function restoreCustomFieldValues(array $rows, array $fieldMap, array $clipMap, array $mutable): int
	{
		if (!$this->tableExists('#__fields_values') || $fieldMap === [])
		{
			return 0;
		}

		$grouped = [];

		foreach ($rows as $row)
		{
			$uuid = strtolower((string) ($row['clip_uuid'] ?? ''));
			$fieldId = (int) ($fieldMap[(string) ($row['field_key'] ?? '')] ?? 0);
			$clipId = (int) ($clipMap[$uuid] ?? 0);

			if (!isset($mutable[$uuid]) || $fieldId <= 0 || $clipId <= 0)
			{
				continue;
			}

			$grouped[$fieldId . ':' . $clipId][] = [
				'field_id' => $fieldId,
				'item_id' => $clipId,
				'value' => (string) ($row['value'] ?? ''),
			];
		}

		$count = 0;

		foreach ($grouped as $values)
		{
			$fieldId = (int) $values[0]['field_id'];
			$clipId = (int) $values[0]['item_id'];
			$query = $this->database->getQuery(true)
				->delete($this->database->quoteName('#__fields_values'))
				->where($this->database->quoteName('field_id') . ' = :fieldId')
				->where($this->database->quoteName('item_id') . ' = :clipId')
				->bind(':fieldId', $fieldId, ParameterType::INTEGER)
				->bind(':clipId', $clipId, ParameterType::INTEGER);
			$this->database->setQuery($query)->execute();

			foreach ($values as $value)
			{
				$this->database->insertObject('#__fields_values', (object) $value);
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @brief Restore portable ACL rules onto generated Joomla assets.
	 *
	 * @param array<int,array<string,mixed>> $rows Exported ACL rows.
	 * @param array<string,int> $categoryMap Category IDs by source key.
	 * @param array<string,int> $clipMap Clip IDs by UUID.
	 *
	 * @return void
	 */
	private function restoreAcl(array $rows, array $categoryMap, array $clipMap): void
	{
		$userGroupMap = $this->loadUserGroupMap();

		foreach ($rows as $row)
		{
			$type = (string) ($row['type'] ?? '');
			$key = (string) ($row['key'] ?? '');
			$name = match ($type)
			{
				'component' => 'com_audioarchive',
				'category' => isset($categoryMap[$key]) ? 'com_audioarchive.category.' . (int) $categoryMap[$key] : '',
				'clip' => isset($clipMap[strtolower($key)]) ? 'com_audioarchive.clip.' . (int) $clipMap[strtolower($key)] : '',
				default => '',
			};

			if ($name === '')
			{
				continue;
			}

			$targetRules = $this->mapPortableRules($row['rules'] ?? [], $userGroupMap);
			$rules = json_encode($targetRules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			$query = $this->database->getQuery(true)
				->update($this->database->quoteName('#__assets'))
				->set($this->database->quoteName('rules') . ' = :rules')
				->where($this->database->quoteName('name') . ' = :name')
				->bind(':rules', $rules, ParameterType::STRING)
				->bind(':name', $name, ParameterType::STRING);
			$this->database->setQuery($query)->execute();
		}
	}

	/**
	 * @brief Restore portable component parameters while preserving target paths.
	 *
	 * @param array<string,mixed> $configuration Exported configuration.
	 * @param array<string,int> $categoryMap Category IDs by source key.
	 * @param array<string,int> $accessMap Access IDs by lowercase title.
	 *
	 * @return void
	 */
	private function restoreConfiguration(array $configuration, array $categoryMap, array $accessMap): void
	{
		$exportedParams = is_array($configuration['params'] ?? null) ? $configuration['params'] : [];
		$references = is_array($configuration['references'] ?? null) ? $configuration['references'] : [];
		$environmentKeys = array_map('strval', is_array($configuration['environment_specific_keys'] ?? null)
			? $configuration['environment_specific_keys']
			: []);
		$query = $this->database->getQuery(true)
			->select($this->database->quoteName('params'))
			->from($this->database->quoteName('#__extensions'))
			->where($this->database->quoteName('type') . ' = ' . $this->database->quote('component'))
			->where($this->database->quoteName('element') . ' = ' . $this->database->quote('com_audioarchive'));
		$current = new Registry((string) $this->database->setQuery($query, 0, 1)->loadResult());

		foreach ($exportedParams as $key => $value)
		{
			if (!in_array((string) $key, $environmentKeys, true))
			{
				$current->set((string) $key, $value);
			}
		}

		$defaultCategoryKey = (string) ($references['default_category_key'] ?? '');

		if ($defaultCategoryKey !== '' && isset($categoryMap[$defaultCategoryKey]))
		{
			$current->set('default_category', (int) $categoryMap[$defaultCategoryKey]);
		}

		foreach ([
			'default_access' => 'default_access_title',
			'frontend_access_level' => 'frontend_access_title',
			'download_access_level' => 'download_access_title',
		] as $parameter => $reference)
		{
			$title = strtolower(trim((string) ($references[$reference] ?? '')));

			if ($title !== '' && isset($accessMap[$title]))
			{
				$current->set($parameter, (int) $accessMap[$title]);
			}
		}

		$paramsJson = (string) $current;
		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__extensions'))
			->set($this->database->quoteName('params') . ' = :params')
			->where($this->database->quoteName('type') . ' = ' . $this->database->quote('component'))
			->where($this->database->quoteName('element') . ' = ' . $this->database->quote('com_audioarchive'))
			->bind(':params', $paramsJson, ParameterType::STRING);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Remove component-owned clip data inside the active transaction.
	 *
	 * Existing managed files are retained until the new restore commits.
	 *
	 * @return void
	 */
	private function prepareReplacement(): void
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('file_role', 'role'), $this->database->quoteName('storage_key')])
			->from($this->database->quoteName('#__audioarchive_files'))
			->where($this->database->quoteName('storage_key') . ' <> ' . $this->database->quote(''));

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$this->oldManagedFiles[] = ['role' => (string) $row['role'], 'storage_key' => (string) $row['storage_key']];
		}

		$query = $this->database->getQuery(true)
			->select($this->database->quoteName('storage_key'))
			->from($this->database->quoteName('#__audioarchive_analyses'))
			->where($this->database->quoteName('storage_key') . ' <> ' . $this->database->quote(''));

		foreach ($this->database->setQuery($query)->loadColumn() as $storageKey)
		{
			$this->oldManagedFiles[] = ['role' => 'analysis', 'storage_key' => (string) $storageKey];
		}

		$clipIds = array_map('intval', $this->database->setQuery(
			$this->database->getQuery(true)
				->select($this->database->quoteName('id'))
				->from($this->database->quoteName('#__audioarchive_clips'))
		)->loadColumn());

		if ($clipIds !== [])
		{
			foreach ($clipIds as $clipId)
			{
				$table = new ClipTable($this->database, Factory::getApplication()->getDispatcher());
				$table->setCurrentUser($this->user);

				if ($table->load($clipId) && !$table->delete($clipId))
				{
					throw new \RuntimeException($table->getError());
				}
			}

			$query = $this->database->getQuery(true)
				->delete($this->database->quoteName('#__contentitem_tag_map'))
				->where($this->database->quoteName('type_alias') . ' = ' . $this->database->quote('com_audioarchive.clip'));
			$this->database->setQuery($query)->execute();

			if ($this->tableExists('#__fields_values'))
			{
				$query = $this->database->getQuery(true)
					->delete($this->database->quoteName('#__fields_values'))
					->whereIn($this->database->quoteName('item_id'), $clipIds, ParameterType::INTEGER);
				$this->database->setQuery($query)->execute();
			}

			if ($this->tableExists('#__ucm_content'))
			{
				$query = $this->database->getQuery(true)
					->delete($this->database->quoteName('#__ucm_content'))
					->where($this->database->quoteName('core_type_alias') . ' = ' . $this->database->quote('com_audioarchive.clip'));
				$this->database->setQuery($query)->execute();
			}
		}

		foreach (['#__audioarchive_jobs', '#__audioarchive_waveforms', '#__audioarchive_analyses', '#__audioarchive_files', '#__audioarchive_clips'] as $tableName)
		{
			$this->database->setQuery(
				$this->database->getQuery(true)->delete($this->database->quoteName($tableName))
			)->execute();
		}
	}



	/**
	 * @brief Map portable ACL group keys to target Joomla group IDs.
	 *
	 * @param mixed $sourceRules Portable source rules.
	 * @param array<string,int> $userGroupMap Target group ID by lowercase key.
	 *
	 * @return array<string,array<string,mixed>> Joomla rules by numeric group ID.
	 */
	private function mapPortableRules(mixed $sourceRules, array $userGroupMap): array
	{
		if (is_string($sourceRules))
		{
			$sourceRules = json_decode($sourceRules, true);
		}

		$targetRules = [];

		foreach (is_array($sourceRules) ? $sourceRules : [] as $action => $groupRules)
		{
			foreach (is_array($groupRules) ? $groupRules : [] as $groupKey => $value)
			{
				$groupId = (int) ($userGroupMap[strtolower(trim((string) $groupKey))] ?? 0);

				if ($groupId > 0)
				{
					$targetRules[(string) $action][(string) $groupId] = $value;
				}
			}
		}

		return $targetRules;
	}

	/**
	 * @brief Load target Joomla user groups by portable hierarchy key.
	 *
	 * @return array<string,int> Target group identifier by lowercase key.
	 */
	private function loadUserGroupMap(): array
	{
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('id'),
				$this->database->quoteName('parent_id'),
				$this->database->quoteName('title'),
			])
			->from($this->database->quoteName('#__usergroups'))
			->order($this->database->quoteName('lft') . ' ASC');
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$rows[(int) $row['id']] = $row;
		}

		$keys = [];
		$map = [];

		foreach (array_keys($rows) as $groupId)
		{
			$key = $this->resolveUserGroupKey((int) $groupId, $rows, $keys, []);

			if ($key !== '')
			{
				$map[strtolower($key)] = (int) $groupId;
			}
		}

		return $map;
	}

	/**
	 * @brief Resolve one target user-group hierarchy key recursively.
	 *
	 * @param int $groupId Group identifier.
	 * @param array<int,array<string,mixed>> $rows Group rows by identifier.
	 * @param array<int,string> $keys Resolved keys.
	 * @param array<int,bool> $stack Recursion guard.
	 *
	 * @return string Portable key.
	 */
	private function resolveUserGroupKey(int $groupId, array $rows, array &$keys, array $stack): string
	{
		if (isset($keys[$groupId]))
		{
			return $keys[$groupId];
		}

		if ($groupId <= 0 || !isset($rows[$groupId]) || isset($stack[$groupId]))
		{
			return '';
		}

		$stack[$groupId] = true;
		$title = trim((string) ($rows[$groupId]['title'] ?? ''));
		$parentId = (int) ($rows[$groupId]['parent_id'] ?? 0);
		$parentKey = $parentId > 0 ? $this->resolveUserGroupKey($parentId, $rows, $keys, $stack) : '';
		$key = $parentKey !== '' ? $parentKey . '/' . $title : $title;
		$keys[$groupId] = $key;

		return $key;
	}

	/**
	 * @brief Load access level IDs keyed by lowercase title.
	 *
	 * @return array<string,int>
	 */
	private function loadAccessLevelMap(): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('title')])
			->from($this->database->quoteName('#__viewlevels'));
		$map = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$map[strtolower(trim((string) $row['title']))] = (int) $row['id'];
		}

		return $map;
	}

	/**
	 * @brief Load user IDs keyed by lowercase username.
	 *
	 * @return array<string,int>
	 */
	private function loadUserMap(): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('username')])
			->from($this->database->quoteName('#__users'));
		$map = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$map[strtolower(trim((string) $row['username']))] = (int) $row['id'];
		}

		return $map;
	}

	/**
	 * @brief Resolve one access level title with Public as fallback.
	 *
	 * @param string $title Access title.
	 * @param array<string,int> $map Access map.
	 *
	 * @return int Access level ID.
	 */
	private function resolveAccess(string $title, array $map): int
	{
		$key = strtolower(trim($title));

		return (int) ($map[$key] ?? $map['public'] ?? 1);
	}

	/**
	 * @brief Resolve one username with current-user fallback.
	 *
	 * @param string $username Username.
	 * @param array<string,int> $map User map.
	 * @param int|null $fallback Optional fallback.
	 *
	 * @return int User ID.
	 */
	private function resolveUser(string $username, array $map, ?int $fallback = null): int
	{
		$key = strtolower(trim($username));

		return (int) ($map[$key] ?? ($fallback ?? (int) $this->user->id));
	}

	/**
	 * @brief Resolve a valid fallback category for clips without a mapped source.
	 *
	 * @param array<string,int> $categoryMap Imported category map.
	 * @param array<string,mixed> $configuration Exported configuration.
	 *
	 * @return int Category ID.
	 */
	private function resolveFallbackCategory(array $categoryMap, array $configuration): int
	{
		$references = is_array($configuration['references'] ?? null) ? $configuration['references'] : [];
		$key = (string) ($references['default_category_key'] ?? '');

		if ($key !== '' && isset($categoryMap[$key]))
		{
			return (int) $categoryMap[$key];
		}

		$configured = (int) $this->params->get('default_category', 0);

		if ($configured > 0)
		{
			return $configured;
		}

		return (int) (reset($categoryMap) ?: 0);
	}

	/**
	 * @brief Test an alias/category pair for an existing clip.
	 *
	 * @param string $alias Alias.
	 * @param int $categoryId Category ID.
	 *
	 * @return bool True when the pair exists.
	 */
	private function aliasExists(string $alias, int $categoryId): bool
	{
		$alias = trim($alias);

		if ($alias === '')
		{
			return false;
		}

		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_clips'))
			->where($this->database->quoteName('alias') . ' = :alias')
			->where($this->database->quoteName('catid') . ' = :catid')
			->bind(':alias', $alias, ParameterType::STRING)
			->bind(':catid', $categoryId, ParameterType::INTEGER);

		return (int) $this->database->setQuery($query)->loadResult() > 0;
	}

	/**
	 * @brief Load one existing file row.
	 *
	 * @param int $clipId Clip ID.
	 * @param string $role File role.
	 *
	 * @return object|null Existing row.
	 */
	private function loadFileRow(int $clipId, string $role): ?object
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_files'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('file_role') . ' = :role')
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':role', $role, ParameterType::STRING);
		$row = $this->database->setQuery($query, 0, 1)->loadObject();

		return is_object($row) ? $row : null;
	}

	/**
	 * @brief Load one existing generic analysis row.
	 *
	 * @param int $clipId Clip ID.
	 * @param string $type Analysis type.
	 *
	 * @return object|null Existing row.
	 */
	private function loadAnalysisRow(int $clipId, string $type): ?object
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_analyses'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('analysis_type') . ' = :type')
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':type', $type, ParameterType::STRING);
		$row = $this->database->setQuery($query, 0, 1)->loadObject();

		return is_object($row) ? $row : null;
	}

	/**
	 * @brief Load one legacy waveform row.
	 *
	 * @param int $clipId Clip ID.
	 *
	 * @return object|null Existing row.
	 */
	private function loadWaveformRow(int $clipId): ?object
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_waveforms'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$row = $this->database->setQuery($query, 0, 1)->loadObject();

		return is_object($row) ? $row : null;
	}

	/**
	 * @brief Insert or update a component table row.
	 *
	 * @param string $table Table name.
	 * @param array<string,mixed> $record Row values.
	 * @param int $existingId Existing row ID or zero.
	 *
	 * @return int Stored row ID.
	 */
	private function upsertById(string $table, array $record, int $existingId): int
	{
		$record = $this->filterForTable($table, $record);

		if ($existingId > 0)
		{
			$record['id'] = $existingId;
			$this->database->updateObject($table, (object) $record, 'id', true);

			return $existingId;
		}

		$this->database->insertObject($table, (object) $record, 'id');

		return (int) $this->database->insertid();
	}

	/**
	 * @brief Update one clip's denormalised analysis status.
	 *
	 * @param int $clipId Clip ID.
	 * @param string $type Analysis type.
	 * @param string $status Analysis status.
	 *
	 * @return void
	 */
	private function updateClipAnalysisStatus(int $clipId, string $type, string $status): void
	{
		$field = match ($type)
		{
			'waveform' => 'waveform_status',
			'spectrogram' => 'spectrogram_status',
			default => '',
		};

		if ($field === '')
		{
			return;
		}

		$query = $this->database->getQuery(true)
			->update($this->database->quoteName('#__audioarchive_clips'))
			->set($this->database->quoteName($field) . ' = :status')
			->where($this->database->quoteName('id') . ' = :clipId')
			->bind(':status', $status, ParameterType::STRING)
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$this->database->setQuery($query)->execute();
	}

	/**
	 * @brief Store one compatibility preview under a generated managed key.
	 *
	 * @param string $temporaryPath Temporary file.
	 * @param string $uuid Clip UUID.
	 * @param string $extension File extension.
	 *
	 * @return array{storage_key:string,absolute_path:string}
	 */
	private function storePreviewFile(string $temporaryPath, string $uuid, string $extension): array
	{
		if (!preg_match('/^[a-z0-9]{1,16}$/', $extension))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_EXTENSION'));
		}

		$root = $this->storage->ensureDirectory('preview');
		$compact = str_replace('-', '', strtolower($uuid));
		$relativeDirectory = substr($compact, 0, 2) . '/' . substr($compact, 2, 2);
		$directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

		if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_CREATE_SHARD'));
		}

		$basename = strtolower($uuid) . '-p' . bin2hex(random_bytes(6)) . '.' . $extension;
		$destination = $directory . DIRECTORY_SEPARATOR . $basename;

		if (!@rename($temporaryPath, $destination) && !@copy($temporaryPath, $destination))
		{
			@unlink($destination);
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_MOVE'));
		}

		@chmod($destination, 0640);

		return [
			'storage_key' => $relativeDirectory . '/' . $basename,
			'absolute_path' => $destination,
		];
	}

	/**
	 * @brief Extract one already validated archive entry to a temporary file.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param string $entryName Entry name.
	 *
	 * @return string Temporary path.
	 */
	private function extractEntryToTemporaryFile(\ZipArchive $zip, string $entryName): string
	{
		$this->assertSafeEntryName($entryName);
		$stream = $zip->getStream($entryName);

		if (!is_resource($stream))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_EXTRACT', $entryName));
		}

		$temporaryDirectory = rtrim((string) Factory::getConfig()->get('tmp_path'), '/\\');
		$temporaryPath = tempnam($temporaryDirectory, 'aa-restore-');

		if (!is_string($temporaryPath) || $temporaryPath === '')
		{
			fclose($stream);
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_TEMP'));
		}

		$output = fopen($temporaryPath, 'wb');

		if (!is_resource($output))
		{
			fclose($stream);
			@unlink($temporaryPath);
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_EXTRACT', $entryName));
		}

		try
		{
			if (stream_copy_to_stream($stream, $output) === false)
			{
				throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_EXTRACT', $entryName));
			}
		}
		finally
		{
			fclose($stream);
			fclose($output);
		}

		return $temporaryPath;
	}

	/**
	 * @brief Read and decode one bounded JSON archive entry.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param string $entryName Entry name.
	 *
	 * @return mixed Decoded JSON.
	 */
	private function readJson(\ZipArchive $zip, string $entryName): mixed
	{
		$stat = $zip->statName($entryName);

		if (!is_array($stat) || (int) ($stat['size'] ?? 0) > self::MAX_JSON_ENTRY_SIZE)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_JSON_SIZE', $entryName));
		}

		$json = $zip->getFromName($entryName);

		if (!is_string($json))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_JSON', $entryName));
		}

		try
		{
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $exception)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_JSON', $entryName), 0, $exception);
		}
	}

	/**
	 * @brief Hash one ZIP entry without loading it entirely into memory.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param string $entryName Entry name.
	 *
	 * @return string SHA-256 checksum.
	 */
	private function hashEntry(\ZipArchive $zip, string $entryName): string
	{
		$stream = $zip->getStream($entryName);

		if (!is_resource($stream))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CHECKSUM_ENTRY', $entryName));
		}

		$context = hash_init('sha256');

		try
		{
			while (!feof($stream))
			{
				$chunk = fread($stream, 1048576);

				if ($chunk === false)
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_CHECKSUM_ENTRY', $entryName));
				}

				hash_update($context, $chunk);
			}
		}
		finally
		{
			fclose($stream);
		}

		return hash_final($context);
	}

	/**
	 * @brief Reject unsafe path names in a ZIP archive.
	 *
	 * @param string $name Entry name.
	 *
	 * @return void
	 */
	private function assertSafeEntryName(string $name): void
	{
		$normalised = str_replace('\\', '/', $name);

		if (
			$normalised === ''
			|| str_contains($normalised, "\0")
			|| str_starts_with($normalised, '/')
			|| preg_match('/^[A-Za-z]:\//', $normalised)
			|| preg_match('#(^|/)\.\.(/|$)#', $normalised)
		)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_UNSAFE_ENTRY', $name));
		}
	}

	/**
	 * @brief Reject symbolic-link entries.
	 *
	 * @param \ZipArchive $zip Source archive.
	 * @param int $index Entry index.
	 * @param string $name Entry name.
	 *
	 * @return void
	 */
	private function assertNotSymlink(\ZipArchive $zip, int $index, string $name): void
	{
		$operatingSystem = 0;
		$attributes = 0;

		if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes))
		{
			return;
		}

		$mode = ($attributes >> 16) & 0xffff;

		if (($mode & 0170000) === 0120000)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_SYMLINK', $name));
		}
	}

	/**
	 * @brief Return the protected restore staging directory.
	 *
	 * @return string Absolute path.
	 */
	private function getStagingRoot(): string
	{
		$importRoot = $this->storage->ensureDirectory('import');
		$root = Path::clean($importRoot . DIRECTORY_SEPARATOR . '.audioarchive-restore');

		if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_STAGE'));
		}

		if (!is_writable($root))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_STAGE'));
		}

		return $root;
	}

	/**
	 * @brief Resolve a selected import-inbox ZIP safely.
	 *
	 * @param string $relativePath Relative inbox path.
	 *
	 * @return string Absolute source path.
	 */
	private function resolveInboxArchive(string $relativePath): string
	{
		$normalised = str_replace('\\', '/', trim($relativePath));

		if (
			$normalised === ''
			|| str_starts_with($normalised, '/')
			|| preg_match('#(^|/)\.\.(/|$)#', $normalised)
			|| str_starts_with($normalised, '.audioarchive-restore/')
			|| strtolower((string) pathinfo($normalised, PATHINFO_EXTENSION)) !== 'zip'
		)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_INBOX_PATH'));
		}

		$root = $this->storage->ensureDirectory('import');
		$path = Path::clean($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalised));
		$realRoot = realpath($root);
		$realPath = realpath($path);

		if (
			$realRoot === false
			|| $realPath === false
			|| !str_starts_with(Path::clean($realPath), rtrim(Path::clean($realRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
			|| !is_file($realPath)
			|| is_link($path)
		)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_INBOX_PATH'));
		}

		return $realPath;
	}

	/**
	 * @brief Return the current clip count.
	 *
	 * @return int Clip count.
	 */
	private function getClipCount(): int
	{
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_clips'));

		return (int) $this->database->setQuery($query)->loadResult();
	}

	/**
	 * @brief Remove newly created files after a database rollback.
	 *
	 * @return void
	 */
	private function cleanupCreatedFiles(): void
	{
		foreach (array_reverse(array_unique($this->createdManagedFiles)) as $path)
		{
			if (is_file($path) && !is_link($path))
			{
				@unlink($path);
			}
		}
	}

	/**
	 * @brief Remove superseded files only after the database commit succeeds.
	 *
	 * @return void
	 */
	private function cleanupOldFiles(): void
	{
		foreach ($this->oldManagedFiles as $entry)
		{
			try
			{
				$this->storage->deleteManagedFile((string) $entry['role'], (string) $entry['storage_key']);
			}
			catch (\Throwable)
			{
				// A failed cleanup remains discoverable through the stale-file check.
			}
		}
	}

	/**
	 * @brief Decode a registry-like JSON value.
	 *
	 * @param mixed $value Source value.
	 *
	 * @return array<string,mixed>
	 */
	private function decodeRegistryValue(mixed $value): array
	{
		if (is_array($value))
		{
			return $value;
		}

		try
		{
			$decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

			return is_array($decoded) ? $decoded : [];
		}
		catch (\Throwable)
		{
			return [];
		}
	}

	/**
	 * @brief Remove export-only helper fields and normalise nested values.
	 *
	 * @param array<string,mixed> $row Source row.
	 *
	 * @return array<string,mixed> Normalised row.
	 */
	private function normalisePortableRow(array $row): array
	{
		foreach ($row as $key => $value)
		{
			if (is_array($value) || is_object($value))
			{
				$row[$key] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			}
		}

		return $row;
	}

	/**
	 * @brief Filter a portable row to columns present in the target table.
	 *
	 * @param string $table Table name.
	 * @param array<string,mixed> $record Candidate row.
	 *
	 * @return array<string,mixed> Filtered row.
	 */
	private function filterForTable(string $table, array $record): array
	{
		$columns = $this->database->getTableColumns($table, false);

		return array_intersect_key($record, $columns);
	}

	/**
	 * @brief Determine whether one Joomla table exists.
	 *
	 * @param string $tableName Prefix-aware table name.
	 *
	 * @return bool True when present.
	 */
	private function tableExists(string $tableName): bool
	{
		return in_array($this->database->replacePrefix($tableName), $this->database->getTableList(), true);
	}
}
