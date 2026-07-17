<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Create portable, versioned Audio Archive ZIP exports.
 */
final class ArchiveExportService
{
	/** @var string */
	public const FORMAT_NAME = 'joomla-audioarchive-export';

	/** @var int */
	public const FORMAT_VERSION = 1;

	/** @var string[] */
	private const SCOPES = ['metadata', 'analyses', 'complete'];

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var Registry */
	private Registry $params;

	/** @var User */
	private User $user;

	/**
	 * @brief Construct the export service.
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
	}

	/**
	 * @brief Determine whether ZIP archive support is available.
	 *
	 * @return bool True when PHP ZipArchive is available.
	 */
	public static function isSupported(): bool
	{
		return class_exists(\ZipArchive::class);
	}

	/**
	 * @brief Build an archive export and return its temporary file.
	 *
	 * @param string $scope Metadata, analyses, or complete.
	 * @param string $componentVersion Installed component version.
	 *
	 * @return array{path:string,filename:string,manifest:array<string,mixed>}
	 */
	public function create(string $scope, string $componentVersion): array
	{
		$scope = strtolower(trim($scope));

		if (!in_array($scope, self::SCOPES, true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_SCOPE'));
		}

		if (!self::isSupported())
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_ZIP_UNAVAILABLE'));
		}

		$temporaryDirectory = rtrim((string) Factory::getConfig()->get('tmp_path'), '/\\');

		if ($temporaryDirectory === '' || !is_dir($temporaryDirectory) || !is_writable($temporaryDirectory))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_TEMP'));
		}

		$stamp = gmdate('Y-m-d-His');
		$filename = 'audioarchive-export-' . $stamp . '-' . $scope . '.zip';
		$path = $temporaryDirectory . DIRECTORY_SEPARATOR . $filename . '.part-' . bin2hex(random_bytes(6));
		$zip = new \ZipArchive();
		$openResult = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		if ($openResult !== true)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_CREATE', (string) $openResult));
		}

		$checksums = [];
		$storage = new ManagedStorageService($this->params);
		$categoryData = $this->loadCategories();
		$tagData = $this->loadTags();
		$userGroupKeys = $this->loadUserGroupKeys();
		$clips = $this->loadClips($categoryData['id_to_key']);
		$clipUuidById = [];

		foreach ($clips as &$clip)
		{
			$clipUuidById[(int) ($clip['_source_id'] ?? 0)] = (string) ($clip['uuid'] ?? '');
			unset($clip['_source_id']);
		}

		unset($clip);

		try
		{
			$this->addJson($zip, 'data/categories.json', $categoryData['rows'], $checksums);
			$this->addJson($zip, 'data/tags.json', $tagData['rows'], $checksums);
			$this->addJson($zip, 'data/clips.json', $clips, $checksums);
			$this->addJson($zip, 'data/tag-relations.json', $this->loadTagRelations($clipUuidById, $tagData['id_to_key']), $checksums);
			$this->addJson($zip, 'data/configuration.json', $this->buildConfiguration($categoryData['id_to_key']), $checksums);
			$this->addJson(
				$zip,
				'data/acl.json',
				$this->loadAcl($clipUuidById, $categoryData['id_to_key'], $userGroupKeys),
				$checksums
			);

			$customFields = $this->loadCustomFields($clipUuidById, $userGroupKeys, $categoryData['id_to_key']);
			$this->addJson($zip, 'data/custom-field-groups.json', $customFields['groups'], $checksums);
			$this->addJson($zip, 'data/custom-fields.json', $customFields['fields'], $checksums);
			$this->addJson($zip, 'data/custom-field-values.json', $customFields['values'], $checksums);
			$this->addJson($zip, 'data/custom-field-categories.json', $customFields['categories'], $checksums);

			$fileRows = $this->loadFiles($clipUuidById, $scope, $zip, $storage, $checksums);
			$this->addJson($zip, 'data/files.json', $fileRows, $checksums);

			$analysisRows = $this->loadAnalyses($clipUuidById, $scope, $zip, $storage, $checksums);
			$this->addJson($zip, 'data/analyses.json', $analysisRows, $checksums);
			$this->addJson($zip, 'data/waveforms.json', $this->loadWaveforms($clipUuidById, $analysisRows), $checksums);

			$manifest = [
				'format' => self::FORMAT_NAME,
				'format_version' => self::FORMAT_VERSION,
				'component_version' => $componentVersion,
				'joomla_version' => defined('JVERSION') ? JVERSION : '',
				'created_at_utc' => gmdate('c'),
				'created_by' => (string) $this->user->username,
				'scope' => $scope,
				'includes' => [
					'metadata' => true,
					'analysis_files' => in_array($scope, ['analyses', 'complete'], true),
					'original_files' => $scope === 'complete',
					'preview_files' => $scope === 'complete',
					'configuration' => true,
					'custom_fields' => true,
				],
				'counts' => [
					'clips' => count($clips),
					'categories' => count($categoryData['rows']),
					'tags' => count($tagData['rows']),
					'files' => count($fileRows),
					'analyses' => count($analysisRows),
					'custom_fields' => count($customFields['fields']),
					'custom_field_values' => count($customFields['values']),
					'custom_field_categories' => count($customFields['categories']),
				],
				'checksum_algorithm' => 'sha256',
				'transient_processing_jobs_included' => false,
			];
			$this->addJson($zip, 'manifest.json', $manifest, $checksums);
			$checksumDocumentChecksums = [];
			$this->addJson($zip, 'checksums.json', $checksums, $checksumDocumentChecksums);
		}
		catch (\Throwable $exception)
		{
			$zip->close();
			@unlink($path);
			throw $exception;
		}

		if (!$zip->close() || !is_file($path))
		{
			@unlink($path);
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_FINALISE'));
		}

		return [
			'path' => $path,
			'filename' => $filename,
			'manifest' => $manifest,
		];
	}

	/**
	 * @brief Add one JSON document and record its checksum.
	 *
	 * @param \ZipArchive $zip Destination archive.
	 * @param string $entryName ZIP entry name.
	 * @param mixed $value JSON value.
	 * @param array<string, string> $checksums Checksum map.
	 *
	 * @return void
	 */
	private function addJson(\ZipArchive $zip, string $entryName, mixed $value, array &$checksums): void
	{
		$json = json_encode(
			$value,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		) . "\n";

		if (!$zip->addFromString($entryName, $json))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_ENTRY', $entryName));
		}

		$checksums[$entryName] = hash('sha256', $json);
	}

	/**
	 * @brief Add one managed file and record its checksum.
	 *
	 * @param \ZipArchive $zip Destination archive.
	 * @param string $sourcePath Absolute source path.
	 * @param string $entryName ZIP entry name.
	 * @param array<string, string> $checksums Checksum map.
	 *
	 * @return bool True when the source existed and was added.
	 */
	private function addManagedFile(\ZipArchive $zip, string $sourcePath, string $entryName, array &$checksums): bool
	{
		if (!is_file($sourcePath) || !is_readable($sourcePath) || is_link($sourcePath))
		{
			return false;
		}

		if (!$zip->addFile($sourcePath, $entryName))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_ENTRY', $entryName));
		}

		$checksum = hash_file('sha256', $sourcePath);

		if (!is_string($checksum) || $checksum === '')
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_CHECKSUM', $entryName));
		}

		$checksums[$entryName] = $checksum;

		return true;
	}

	/**
	 * @brief Load Audio Archive categories as portable path-based records.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,id_to_key:array<int,string>}
	 */
	private function loadCategories(): array
	{
		$query = $this->database->getQuery(true)
			->select('c.*')
			->select($this->database->quoteName('v.title', 'access_level_title'))
			->select($this->database->quoteName('u.username', 'created_by_username'))
			->select($this->database->quoteName('mu.username', 'modified_by_username'))
			->from($this->database->quoteName('#__categories', 'c'))
			->leftJoin($this->database->quoteName('#__viewlevels', 'v') . ' ON v.id = c.access')
			->leftJoin($this->database->quoteName('#__users', 'u') . ' ON u.id = c.created_user_id')
			->leftJoin($this->database->quoteName('#__users', 'mu') . ' ON mu.id = c.modified_user_id')
			->where($this->database->quoteName('c.extension') . ' = ' . $this->database->quote('com_audioarchive'))
			->order($this->database->quoteName('c.lft') . ' ASC');
		$sourceRows = $this->database->setQuery($query)->loadAssocList();
		$idToKey = [];

		foreach ($sourceRows as $row)
		{
			$key = trim((string) ($row['path'] ?? ''));
			$key = $key !== '' ? $key : trim((string) ($row['alias'] ?? ''));
			$idToKey[(int) $row['id']] = $key;
		}

		$rows = [];

		foreach ($sourceRows as $row)
		{
			$id = (int) $row['id'];
			$parentId = (int) $row['parent_id'];
			$row['key'] = $idToKey[$id] ?? '';
			$row['parent_key'] = $idToKey[$parentId] ?? '';
			unset(
				$row['id'],
				$row['asset_id'],
				$row['parent_id'],
				$row['lft'],
				$row['rgt'],
				$row['level'],
				$row['path'],
				$row['access'],
				$row['created_user_id'],
				$row['modified_user_id'],
				$row['checked_out'],
				$row['checked_out_time']
			);
			$rows[] = $row;
		}

		return ['rows' => $rows, 'id_to_key' => $idToKey];
	}

	/**
	 * @brief Load tags used by Audio Archive clips.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,id_to_key:array<int,string>}
	 */
	private function loadTags(): array
	{
		$typeAlias = 'com_audioarchive.clip';
		$query = $this->database->getQuery(true)
			->select('DISTINCT ' . $this->database->quoteName('t.id'))
			->from($this->database->quoteName('#__tags', 't'))
			->innerJoin(
				$this->database->quoteName('#__contentitem_tag_map', 'm')
				. ' ON m.tag_id = t.id AND m.type_alias = :typeAlias'
			)
			->where($this->database->quoteName('t.id') . ' > 1')
			->bind(':typeAlias', $typeAlias, ParameterType::STRING);
		$tagIds = array_map('intval', $this->database->setQuery($query)->loadColumn());

		if ($tagIds === [])
		{
			return ['rows' => [], 'id_to_key' => []];
		}

		$allIds = array_fill_keys($tagIds, true);
		$pending = $tagIds;

		while ($pending !== [])
		{
			$query = $this->database->getQuery(true)
				->select([$this->database->quoteName('id'), $this->database->quoteName('parent_id')])
				->from($this->database->quoteName('#__tags'))
				->whereIn($this->database->quoteName('id'), $pending, ParameterType::INTEGER);
			$parents = [];

			foreach ($this->database->setQuery($query)->loadAssocList() as $row)
			{
				$parentId = (int) $row['parent_id'];

				if ($parentId > 1 && !isset($allIds[$parentId]))
				{
					$allIds[$parentId] = true;
					$parents[] = $parentId;
				}
			}

			$pending = $parents;
		}

		$ids = array_keys($allIds);
		$query = $this->database->getQuery(true)
			->select('t.*')
			->select($this->database->quoteName('v.title', 'access_level_title'))
			->select($this->database->quoteName('u.username', 'created_by_username'))
			->select($this->database->quoteName('mu.username', 'modified_by_username'))
			->from($this->database->quoteName('#__tags', 't'))
			->leftJoin($this->database->quoteName('#__viewlevels', 'v') . ' ON v.id = t.access')
			->leftJoin($this->database->quoteName('#__users', 'u') . ' ON u.id = t.created_user_id')
			->leftJoin($this->database->quoteName('#__users', 'mu') . ' ON mu.id = t.modified_user_id')
			->whereIn($this->database->quoteName('t.id'), $ids, ParameterType::INTEGER)
			->order($this->database->quoteName('t.lft') . ' ASC');
		$sourceRows = $this->database->setQuery($query)->loadAssocList();
		$idToKey = [];

		foreach ($sourceRows as $row)
		{
			$key = trim((string) ($row['path'] ?? ''));
			$key = $key !== '' ? $key : trim((string) ($row['alias'] ?? ''));
			$idToKey[(int) $row['id']] = $key;
		}

		$rows = [];

		foreach ($sourceRows as $row)
		{
			$id = (int) $row['id'];
			$parentId = (int) $row['parent_id'];
			$row['key'] = $idToKey[$id] ?? '';
			$row['parent_key'] = $idToKey[$parentId] ?? '';
			unset(
				$row['id'],
				$row['asset_id'],
				$row['parent_id'],
				$row['lft'],
				$row['rgt'],
				$row['level'],
				$row['path'],
				$row['access'],
				$row['created_user_id'],
				$row['modified_user_id'],
				$row['checked_out'],
				$row['checked_out_time']
			);
			$rows[] = $row;
		}

		return ['rows' => $rows, 'id_to_key' => $idToKey];
	}

	/**
	 * @brief Load clips with portable category, access, and user references.
	 *
	 * @param array<int, string> $categoryKeys Category path by source ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function loadClips(array $categoryKeys): array
	{
		$query = $this->database->getQuery(true)
			->select('c.*')
			->select($this->database->quoteName('v.title', 'access_level_title'))
			->select($this->database->quoteName('u.username', 'created_by_username'))
			->select($this->database->quoteName('mu.username', 'modified_by_username'))
			->from($this->database->quoteName('#__audioarchive_clips', 'c'))
			->leftJoin($this->database->quoteName('#__viewlevels', 'v') . ' ON v.id = c.access')
			->leftJoin($this->database->quoteName('#__users', 'u') . ' ON u.id = c.created_by')
			->leftJoin($this->database->quoteName('#__users', 'mu') . ' ON mu.id = c.modified_by')
			->order($this->database->quoteName('c.id') . ' ASC');
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$row['_source_id'] = (int) $row['id'];
			$row['category_key'] = $categoryKeys[(int) $row['catid']] ?? '';
			unset(
				$row['id'],
				$row['asset_id'],
				$row['catid'],
				$row['access'],
				$row['created_by'],
				$row['modified_by'],
				$row['checked_out'],
				$row['checked_out_time']
			);
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @brief Load portable clip/tag relations.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param array<int, string> $tagKeyById Tag path by source ID.
	 *
	 * @return array<int, array{clip_uuid:string,tag_key:string}>
	 */
	private function loadTagRelations(array $clipUuidById, array $tagKeyById): array
	{
		$typeAlias = 'com_audioarchive.clip';
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('content_item_id'), $this->database->quoteName('tag_id')])
			->from($this->database->quoteName('#__contentitem_tag_map'))
			->where($this->database->quoteName('type_alias') . ' = :typeAlias')
			->bind(':typeAlias', $typeAlias, ParameterType::STRING);
		$relations = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$clipUuid = $clipUuidById[(int) $row['content_item_id']] ?? '';
			$tagKey = $tagKeyById[(int) $row['tag_id']] ?? '';

			if ($clipUuid !== '' && $tagKey !== '')
			{
				$relations[] = ['clip_uuid' => $clipUuid, 'tag_key' => $tagKey];
			}
		}

		return $relations;
	}

	/**
	 * @brief Build portable configuration data with semantic references.
	 *
	 * @param array<int, string> $categoryKeys Category path by source ID.
	 *
	 * @return array<string, mixed>
	 */
	private function buildConfiguration(array $categoryKeys): array
	{
		$params = $this->params->toArray();
		$accessTitles = $this->loadAccessTitles();

		return [
			'params' => $params,
			'references' => [
				'default_category_key' => $categoryKeys[(int) ($params['default_category'] ?? 0)] ?? '',
				'default_access_title' => $accessTitles[(int) ($params['default_access'] ?? 0)] ?? '',
				'frontend_access_title' => $accessTitles[(int) ($params['frontend_access_level'] ?? 0)] ?? '',
				'download_access_title' => $accessTitles[(int) ($params['download_access_level'] ?? 0)] ?? '',
			],
			'environment_specific_keys' => [
				'original_directory',
				'preview_directory',
				'waveform_directory',
				'import_directory',
				'ffmpeg_path',
				'ffprobe_path',
				'remove_media_on_uninstall',
			],
		];
	}

	/**
	 * @brief Load file rows and optionally add physical originals and previews.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param string $scope Export scope.
	 * @param \ZipArchive $zip Destination archive.
	 * @param ManagedStorageService $storage Managed storage service.
	 * @param array<string, string> $checksums Checksum map.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function loadFiles(
		array $clipUuidById,
		string $scope,
		\ZipArchive $zip,
		ManagedStorageService $storage,
		array &$checksums
	): array
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_files'))
			->order($this->database->quoteName('clip_id') . ' ASC')
			->order($this->database->quoteName('file_role') . ' ASC');
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$clipUuid = $clipUuidById[(int) $row['clip_id']] ?? '';

			if ($clipUuid === '')
			{
				continue;
			}

			$role = strtolower((string) $row['file_role']);
			$archivePath = '';
			$included = false;

			if ($scope === 'complete' && in_array($role, ['original', 'preview'], true) && trim((string) $row['storage_key']) !== '')
			{
				try
				{
					$sourcePath = $storage->resolveManagedPath($role, (string) $row['storage_key']);
					$extension = strtolower((string) ($row['file_extension'] ?: pathinfo($sourcePath, PATHINFO_EXTENSION)));
					$extension = preg_match('/^[a-z0-9]{1,16}$/', $extension) ? $extension : 'bin';
					$archivePath = 'media/' . $role . '/' . $clipUuid . '.' . $extension;
					$included = $this->addManagedFile($zip, $sourcePath, $archivePath, $checksums);
				}
				catch (\Throwable)
				{
					$included = false;
				}
			}

			$row['clip_uuid'] = $clipUuid;
			$row['archive_path'] = $included ? $archivePath : '';
			$row['file_included'] = $included;
			unset($row['id'], $row['clip_id'], $row['storage_key']);
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @brief Load analysis rows and optionally add physical analysis data.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param string $scope Export scope.
	 * @param \ZipArchive $zip Destination archive.
	 * @param ManagedStorageService $storage Managed storage service.
	 * @param array<string, string> $checksums Checksum map.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function loadAnalyses(
		array $clipUuidById,
		string $scope,
		\ZipArchive $zip,
		ManagedStorageService $storage,
		array &$checksums
	): array
	{
		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_analyses'))
			->order($this->database->quoteName('clip_id') . ' ASC')
			->order($this->database->quoteName('analysis_type') . ' ASC');
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$clipUuid = $clipUuidById[(int) $row['clip_id']] ?? '';

			if ($clipUuid === '')
			{
				continue;
			}

			$type = strtolower((string) $row['analysis_type']);
			$archivePath = '';
			$included = false;

			if (in_array($scope, ['analyses', 'complete'], true) && trim((string) $row['storage_key']) !== '')
			{
				try
				{
					$sourcePath = $storage->resolveManagedPath('analysis', (string) $row['storage_key']);
					$extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
					$extension = preg_match('/^[a-z0-9]{1,16}$/', $extension) ? $extension : 'bin';
					$archivePath = 'analyses/' . preg_replace('/[^a-z0-9_-]/', '_', $type) . '/' . $clipUuid . '.' . $extension;
					$included = $this->addManagedFile($zip, $sourcePath, $archivePath, $checksums);
				}
				catch (\Throwable)
				{
					$included = false;
				}
			}

			$row['clip_uuid'] = $clipUuid;
			$row['archive_path'] = $included ? $archivePath : '';
			$row['file_included'] = $included;
			unset($row['id'], $row['clip_id'], $row['storage_key']);
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @brief Load the legacy waveform projection.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param array<int, array<string,mixed>> $analyses Exported generic analyses.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function loadWaveforms(array $clipUuidById, array $analyses): array
	{
		$waveformPaths = [];

		foreach ($analyses as $analysis)
		{
			if ((string) ($analysis['analysis_type'] ?? '') === 'waveform')
			{
				$waveformPaths[(string) ($analysis['clip_uuid'] ?? '')] = (string) ($analysis['archive_path'] ?? '');
			}
		}

		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__audioarchive_waveforms'))
			->order($this->database->quoteName('clip_id') . ' ASC');
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$clipUuid = $clipUuidById[(int) $row['clip_id']] ?? '';

			if ($clipUuid === '')
			{
				continue;
			}

			$row['clip_uuid'] = $clipUuid;
			$row['archive_path'] = $waveformPaths[$clipUuid] ?? '';
			unset($row['id'], $row['clip_id'], $row['storage_key']);
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @brief Load ACL rules for the component, categories, and clips.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param array<int, string> $categoryKeys Category key by source ID.
	 * @param array<int, string> $userGroupKeys Portable user-group key by source ID.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function loadAcl(array $clipUuidById, array $categoryKeys, array $userGroupKeys): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('name'), $this->database->quoteName('rules')])
			->from($this->database->quoteName('#__assets'))
			->where(
				'(' . $this->database->quoteName('name') . ' = ' . $this->database->quote('com_audioarchive')
				. ' OR ' . $this->database->quoteName('name') . ' LIKE ' . $this->database->quote('com_audioarchive.category.%')
				. ' OR ' . $this->database->quoteName('name') . ' LIKE ' . $this->database->quote('com_audioarchive.clip.%') . ')'
			);
		$rows = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$name = (string) $row['name'];
			$record = [
				'type' => '',
				'key' => '',
				'rules' => $this->makePortableRules((string) $row['rules'], $userGroupKeys),
			];

			if ($name === 'com_audioarchive')
			{
				$record['type'] = 'component';
			}
			elseif (preg_match('/^com_audioarchive\.category\.(\d+)$/', $name, $matches))
			{
				$record['type'] = 'category';
				$record['key'] = $categoryKeys[(int) $matches[1]] ?? '';
			}
			elseif (preg_match('/^com_audioarchive\.clip\.(\d+)$/', $name, $matches))
			{
				$record['type'] = 'clip';
				$record['key'] = $clipUuidById[(int) $matches[1]] ?? '';
			}

			if ($record['type'] !== '' && ($record['type'] === 'component' || $record['key'] !== ''))
			{
				$rows[] = $record;
			}
		}

		return $rows;
	}



	/**
	 * @brief Convert site-specific ACL group IDs to portable hierarchy keys.
	 *
	 * @param string $rulesJson Joomla asset rules JSON.
	 * @param array<int,string> $userGroupKeys Portable key by source group ID.
	 *
	 * @return array<string,array<string,mixed>> Portable rules.
	 */
	private function makePortableRules(string $rulesJson, array $userGroupKeys): array
	{
		$sourceRules = json_decode($rulesJson, true);
		$portableRules = [];

		foreach (is_array($sourceRules) ? $sourceRules : [] as $action => $groupRules)
		{
			foreach (is_array($groupRules) ? $groupRules : [] as $groupId => $value)
			{
				$groupKey = $userGroupKeys[(int) $groupId] ?? '';

				if ($groupKey !== '')
				{
					$portableRules[(string) $action][$groupKey] = $value;
				}
			}
		}

		return $portableRules;
	}

	/**
	 * @brief Load portable hierarchical keys for Joomla user groups.
	 *
	 * @return array<int,string> Group key by source identifier.
	 */
	private function loadUserGroupKeys(): array
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

		foreach (array_keys($rows) as $groupId)
		{
			$this->resolveUserGroupKey((int) $groupId, $rows, $keys, []);
		}

		return $keys;
	}

	/**
	 * @brief Resolve one user-group hierarchy key recursively.
	 *
	 * @param int $groupId Group identifier.
	 * @param array<int,array<string,mixed>> $rows Source rows by identifier.
	 * @param array<int,string> $keys Resolved keys by identifier.
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
	 * @brief Load custom field definitions and values when Joomla fields tables exist.
	 *
	 * @param array<int, string> $clipUuidById Clip UUID by source ID.
	 * @param array<int, string> $userGroupKeys Portable user-group key by source ID.
	 * @param array<int, string> $categoryKeys Portable category key by source ID.
	 *
	 * @return array{groups:array<int,array<string,mixed>>,fields:array<int,array<string,mixed>>,values:array<int,array<string,mixed>>,categories:array<int,array<string,mixed>>}
	 */
	private function loadCustomFields(array $clipUuidById, array $userGroupKeys, array $categoryKeys): array
	{
		if (!$this->tableExists('#__fields') || !$this->tableExists('#__fields_groups') || !$this->tableExists('#__fields_values'))
		{
			return ['groups' => [], 'fields' => [], 'values' => [], 'categories' => []];
		}

		$context = 'com_audioarchive.clip';
		$query = $this->database->getQuery(true)
			->select('g.*')
			->select($this->database->quoteName('v.title', 'access_level_title'))
			->select($this->database->quoteName('u.username', 'created_by_username'))
			->select($this->database->quoteName('mu.username', 'modified_by_username'))
			->select($this->database->quoteName('a.rules', 'asset_rules'))
			->from($this->database->quoteName('#__fields_groups', 'g'))
			->leftJoin($this->database->quoteName('#__viewlevels', 'v') . ' ON v.id = g.access')
			->leftJoin($this->database->quoteName('#__users', 'u') . ' ON u.id = g.created_by')
			->leftJoin($this->database->quoteName('#__users', 'mu') . ' ON mu.id = g.modified_by')
			->leftJoin($this->database->quoteName('#__assets', 'a') . ' ON a.id = g.asset_id')
			->where($this->database->quoteName('g.context') . ' = :context')
			->order($this->database->quoteName('g.ordering') . ' ASC')
			->bind(':context', $context, ParameterType::STRING);
		$groups = [];
		$groupTitles = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$groupTitles[(int) $row['id']] = (string) $row['title'];
			$row['key'] = (string) $row['title'];
			$row['rules'] = $this->makePortableRules((string) ($row['asset_rules'] ?? '{}'), $userGroupKeys);
			unset(
				$row['id'],
				$row['asset_id'],
				$row['access'],
				$row['created_by'],
				$row['modified_by'],
				$row['checked_out'],
				$row['checked_out_time'],
				$row['asset_rules']
			);
			$groups[] = $row;
		}

		$query = $this->database->getQuery(true)
			->select('f.*')
			->select($this->database->quoteName('v.title', 'access_level_title'))
			->select($this->database->quoteName('u.username', 'created_by_username'))
			->select($this->database->quoteName('mu.username', 'modified_by_username'))
			->select($this->database->quoteName('a.rules', 'asset_rules'))
			->from($this->database->quoteName('#__fields', 'f'))
			->leftJoin($this->database->quoteName('#__viewlevels', 'v') . ' ON v.id = f.access')
			->leftJoin($this->database->quoteName('#__users', 'u') . ' ON u.id = f.created_user_id')
			->leftJoin($this->database->quoteName('#__users', 'mu') . ' ON mu.id = f.modified_by')
			->leftJoin($this->database->quoteName('#__assets', 'a') . ' ON a.id = f.asset_id')
			->where($this->database->quoteName('f.context') . ' = :context')
			->order($this->database->quoteName('f.ordering') . ' ASC')
			->bind(':context', $context, ParameterType::STRING);
		$fields = [];
		$fieldNames = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$fieldNames[(int) $row['id']] = (string) $row['name'];
			$row['key'] = (string) $row['name'];
			$row['group_key'] = $groupTitles[(int) ($row['group_id'] ?? 0)] ?? '';
			$row['rules'] = $this->makePortableRules((string) ($row['asset_rules'] ?? '{}'), $userGroupKeys);
			unset(
				$row['id'],
				$row['asset_id'],
				$row['group_id'],
				$row['access'],
				$row['created_user_id'],
				$row['modified_by'],
				$row['checked_out'],
				$row['checked_out_time'],
				$row['asset_rules']
			);
			$fields[] = $row;
		}

		$fieldCategories = [];

		if ($fieldNames !== [] && $this->tableExists('#__fields_categories'))
		{
			$query = $this->database->getQuery(true)
				->select([$this->database->quoteName('field_id'), $this->database->quoteName('category_id')])
				->from($this->database->quoteName('#__fields_categories'))
				->whereIn($this->database->quoteName('field_id'), array_keys($fieldNames), ParameterType::INTEGER);

			foreach ($this->database->setQuery($query)->loadAssocList() as $row)
			{
				$fieldKey = $fieldNames[(int) $row['field_id']] ?? '';
				$categoryId = (int) $row['category_id'];

				if ($fieldKey === '')
				{
					continue;
				}

				$fieldCategories[] = [
					'field_key' => $fieldKey,
					'category_key' => $categoryId > 0 ? ($categoryKeys[$categoryId] ?? '') : '',
					'special_category_id' => $categoryId < 0 ? $categoryId : 0,
				];
			}
		}

		if ($fieldNames === [] || $clipUuidById === [])
		{
			return ['groups' => $groups, 'fields' => $fields, 'values' => [], 'categories' => $fieldCategories];
		}

		$query = $this->database->getQuery(true)
			->select('*')
			->from($this->database->quoteName('#__fields_values'))
			->whereIn($this->database->quoteName('field_id'), array_keys($fieldNames), ParameterType::INTEGER)
			->whereIn($this->database->quoteName('item_id'), array_keys($clipUuidById), ParameterType::INTEGER);
		$values = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$fieldKey = $fieldNames[(int) $row['field_id']] ?? '';
			$clipUuid = $clipUuidById[(int) $row['item_id']] ?? '';

			if ($fieldKey !== '' && $clipUuid !== '')
			{
				$values[] = [
					'field_key' => $fieldKey,
					'clip_uuid' => $clipUuid,
					'value' => $row['value'] ?? '',
				];
			}
		}

		return ['groups' => $groups, 'fields' => $fields, 'values' => $values, 'categories' => $fieldCategories];
	}

	/**
	 * @brief Load access-level titles by identifier.
	 *
	 * @return array<int, string>
	 */
	private function loadAccessTitles(): array
	{
		$query = $this->database->getQuery(true)
			->select([$this->database->quoteName('id'), $this->database->quoteName('title')])
			->from($this->database->quoteName('#__viewlevels'));
		$result = [];

		foreach ($this->database->setQuery($query)->loadAssocList() as $row)
		{
			$result[(int) $row['id']] = (string) $row['title'];
		}

		return $result;
	}

	/**
	 * @brief Determine whether a Joomla table exists.
	 *
	 * @param string $tableName Prefix-aware table name.
	 *
	 * @return bool True when the table exists.
	 */
	private function tableExists(string $tableName): bool
	{
		$resolved = $this->database->replacePrefix($tableName);

		return in_array($resolved, $this->database->getTableList(), true);
	}
}
