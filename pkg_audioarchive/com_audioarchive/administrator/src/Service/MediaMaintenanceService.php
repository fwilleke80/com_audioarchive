<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Provide media inventory and safe stale-file cleanup operations.
 */
class MediaMaintenanceService
{
	/** @var string[] */
	private const PROTECTION_FILES = [
		'.htaccess',
		'index.html',
		'web.config',
	];

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/**
	 * @brief Construct the media maintenance service.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 */
	public function __construct(DatabaseInterface $database, Registry $params)
	{
		$this->database = $database;
		$this->storage = new ManagedStorageService($params);
	}

	/**
	 * @brief Return grouped original-file codec, container, and extension counts.
	 *
	 * @return array<int, array<string, mixed>> Inventory rows.
	 */
	public function getCodecInventory(): array
	{
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('f.audio_codec'),
				$this->database->quoteName('f.container_format'),
				$this->database->quoteName('f.file_extension'),
				'COUNT(*) AS ' . $this->database->quoteName('clip_count'),
				'SUM(' . $this->database->quoteName('f.file_size') . ') AS ' . $this->database->quoteName('total_size'),
			])
			->from($this->database->quoteName('#__audioarchive_files', 'f'))
			->where($this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original'))
			->group([
				$this->database->quoteName('f.audio_codec'),
				$this->database->quoteName('f.container_format'),
				$this->database->quoteName('f.file_extension'),
			])
			->order([
				$this->database->quoteName('f.audio_codec') . ' ASC',
				$this->database->quoteName('f.container_format') . ' ASC',
				$this->database->quoteName('f.file_extension') . ' ASC',
			]);
		$rows = $this->database->setQuery($query)->loadObjectList() ?: [];
		$result = [];

		foreach ($rows as $row)
		{
			$codec = trim((string) $row->audio_codec);
			$result[] = [
				'codec' => $codec,
				'codec_filter' => $codec !== '' ? $codec : '__unknown__',
				'container' => trim((string) $row->container_format),
				'extension' => trim((string) $row->file_extension),
				'clip_count' => (int) $row->clip_count,
				'total_size' => (int) $row->total_size,
			];
		}

		return $result;
	}

	/**
	 * @brief Return all clips using one selected original-file codec.
	 *
	 * @param string $codecFilter Codec value or __unknown__.
	 *
	 * @return object[] Matching clip rows.
	 */
	public function getClipsByCodec(string $codecFilter): array
	{
		$codecFilter = trim($codecFilter);

		if ($codecFilter === '')
		{
			return [];
		}

		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('a.id'),
				$this->database->quoteName('a.title'),
				$this->database->quoteName('a.original_filename'),
				$this->database->quoteName('a.state'),
				$this->database->quoteName('a.preview_status'),
				$this->database->quoteName('a.waveform_status'),
				$this->database->quoteName('f.file_extension'),
				$this->database->quoteName('f.container_format'),
				$this->database->quoteName('f.audio_codec'),
				$this->database->quoteName('f.file_size'),
				$this->database->quoteName('f.duration_ms'),
			])
			->from($this->database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
				. ' AND ' . $this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original')
			)
			->order([
				$this->database->quoteName('a.title') . ' ASC',
				$this->database->quoteName('a.id') . ' ASC',
			]);

		if ($codecFilter === '__unknown__')
		{
			$query->where(
				'(' . $this->database->quoteName('f.audio_codec') . ' IS NULL OR '
				. $this->database->quoteName('f.audio_codec') . ' = ' . $this->database->quote('') . ')'
			);
		}
		else
		{
			$query->where($this->database->quoteName('f.audio_codec') . ' = :codec')
				->bind(':codec', $codecFilter, ParameterType::STRING);
		}

		return $this->database->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * @brief Find stale derivatives and unreferenced managed files.
	 *
	 * @return array<int, array<string, mixed>> Safe cleanup candidates.
	 */
	public function getStaleItems(): array
	{
		$items = [];
		$referenced = $this->loadReferencedKeys();
		$referencedPaths = $this->loadReferencedPaths();
		$this->appendStalePreviewItems($items);
		$this->appendStaleWaveformItems($items);

		foreach (['original', 'preview', 'waveform'] as $role)
		{
			$this->appendUnreferencedItems(
				$items,
				$role,
				$referenced[$role] ?? [],
				$referencedPaths
			);
		}

		usort(
			$items,
			static fn (array $left, array $right): int => strcasecmp(
				(string) ($left['kind'] . ' ' . $left['storage_key']),
				(string) ($right['kind'] . ' ' . $right['storage_key'])
			)
		);

		return $items;
	}

	/**
	 * @brief Delete selected stale candidates after regenerating the candidate list.
	 *
	 * @param string[] $tokens Candidate tokens submitted by the administrator.
	 *
	 * @return array{succeeded:int,failed:int,messages:string[]} Cleanup result.
	 */
	public function deleteStaleItems(array $tokens): array
	{
		$tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
		$current = [];

		foreach ($this->getStaleItems() as $item)
		{
			$current[(string) $item['token']] = $item;
		}

		$succeeded = 0;
		$failed = 0;
		$messages = [];

		foreach ($tokens as $token)
		{
			if (!isset($current[$token]))
			{
				$failed++;
				$messages[] = Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_CHANGED');
				continue;
			}

			$item = $current[$token];

			try
			{
				$this->deleteStaleItem($item);
				$succeeded++;
			}
			catch (\Throwable $exception)
			{
				$failed++;
				$messages[] = $exception->getMessage();
			}
		}

		return [
			'succeeded' => $succeeded,
			'failed' => $failed,
			'messages' => array_values(array_unique(array_filter($messages))),
		];
	}

	/**
	 * @brief Load every database-referenced managed storage key.
	 *
	 * @return array<string, array<string, bool>> Keys by role.
	 */
	private function loadReferencedKeys(): array
	{
		$result = ['original' => [], 'preview' => [], 'waveform' => []];
		$query = $this->database->getQuery(true)
			->select(['file_role', 'storage_key'])
			->from($this->database->quoteName('#__audioarchive_files'));

		foreach ($this->database->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$role = (string) $row->file_role;
			$key = $this->normaliseKey((string) $row->storage_key);

			if ($key === '')
			{
				continue;
			}

			if (isset($result[$role]))
			{
				$result[$role][$key] = true;
			}
			else
			{
				foreach (array_keys($result) as $knownRole)
				{
					$result[$knownRole][$key] = true;
				}
			}
		}

		$query = $this->database->getQuery(true)
			->select('storage_key')
			->from($this->database->quoteName('#__audioarchive_waveforms'));

		foreach ($this->database->setQuery($query)->loadColumn() ?: [] as $value)
		{
			$key = $this->normaliseKey((string) $value);

			if ($key !== '')
			{
				$result['waveform'][$key] = true;
			}
		}

		return $result;
	}


	/**
	 * @brief Resolve all database-referenced files to absolute paths.
	 *
	 * This additional set protects installations whose configured storage roots
	 * overlap or are nested inside each other.
	 *
	 * @return array<string, bool> Normalised absolute paths.
	 */
	private function loadReferencedPaths(): array
	{
		$paths = [];
		$query = $this->database->getQuery(true)
			->select(['file_role', 'storage_key'])
			->from($this->database->quoteName('#__audioarchive_files'));

		foreach ($this->database->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$role = (string) $row->file_role;

			if (!in_array($role, ['original', 'preview'], true))
			{
				continue;
			}

			$this->appendReferencedPath($paths, $role, (string) $row->storage_key);
		}

		$query = $this->database->getQuery(true)
			->select('storage_key')
			->from($this->database->quoteName('#__audioarchive_waveforms'));

		foreach ($this->database->setQuery($query)->loadColumn() ?: [] as $storageKey)
		{
			$this->appendReferencedPath($paths, 'waveform', (string) $storageKey);
		}

		return $paths;
	}

	/**
	 * @brief Add one safely resolved database reference to an absolute path set.
	 *
	 * @param array<string, bool> $paths Absolute path set.
	 * @param string $role Managed storage role.
	 * @param string $storageKey Managed storage key.
	 *
	 * @return void
	 */
	private function appendReferencedPath(array &$paths, string $role, string $storageKey): void
	{
		try
		{
			$path = $this->storage->resolveManagedPath($role, $storageKey);
		}
		catch (\Throwable $exception)
		{
			return;
		}

		$realPath = realpath($path);
		$paths[$this->normaliseAbsolutePath($realPath !== false ? $realPath : $path)] = true;
	}

	/**
	 * @brief Append preview records marked stale by their owning clips.
	 *
	 * @param array<int, array<string, mixed>> $items Candidate collection.
	 *
	 * @return void
	 */
	private function appendStalePreviewItems(array &$items): void
	{
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('f.id', 'record_id'),
				$this->database->quoteName('f.clip_id'),
				$this->database->quoteName('f.storage_key'),
				$this->database->quoteName('f.file_size'),
				$this->database->quoteName('a.title', 'clip_title'),
				$this->database->quoteName('a.original_filename'),
			])
			->from($this->database->quoteName('#__audioarchive_files', 'f'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_clips', 'a')
				. ' ON ' . $this->database->quoteName('a.id') . ' = ' . $this->database->quoteName('f.clip_id')
			)
			->where($this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('preview'))
			->where($this->database->quoteName('a.preview_status') . ' = ' . $this->database->quote('stale'));

		foreach ($this->database->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$items[] = $this->makeItem(
				'stale_preview',
				'preview',
				(string) $row->storage_key,
				(int) $row->file_size,
				(int) $row->clip_id,
				(string) $row->clip_title,
				(string) $row->original_filename,
				(int) $row->record_id,
				'COM_AUDIOARCHIVE_MAINTENANCE_STALE_REASON_PREVIEW'
			);
		}
	}

	/**
	 * @brief Append waveform records marked stale by their owning clips.
	 *
	 * @param array<int, array<string, mixed>> $items Candidate collection.
	 *
	 * @return void
	 */
	private function appendStaleWaveformItems(array &$items): void
	{
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('w.id', 'record_id'),
				$this->database->quoteName('w.clip_id'),
				$this->database->quoteName('w.storage_key'),
				$this->database->quoteName('a.title', 'clip_title'),
				$this->database->quoteName('a.original_filename'),
			])
			->from($this->database->quoteName('#__audioarchive_waveforms', 'w'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_clips', 'a')
				. ' ON ' . $this->database->quoteName('a.id') . ' = ' . $this->database->quoteName('w.clip_id')
			)
			->where($this->database->quoteName('a.waveform_status') . ' = ' . $this->database->quote('stale'));

		foreach ($this->database->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$size = $this->managedFileSize('waveform', (string) $row->storage_key);
			$items[] = $this->makeItem(
				'stale_waveform',
				'waveform',
				(string) $row->storage_key,
				$size,
				(int) $row->clip_id,
				(string) $row->clip_title,
				(string) $row->original_filename,
				(int) $row->record_id,
				'COM_AUDIOARCHIVE_MAINTENANCE_STALE_REASON_WAVEFORM'
			);
		}
	}

	/**
	 * @brief Append filesystem objects that have no database reference.
	 *
	 * @param array<int, array<string, mixed>> $items Candidate collection.
	 * @param string $role Managed role.
	 * @param array<string, bool> $referenced Referenced keys for this role.
	 * @param array<string, bool> $referencedPaths All referenced absolute paths.
	 *
	 * @return void
	 */
	private function appendUnreferencedItems(
		array &$items,
		string $role,
		array $referenced,
		array $referencedPaths
	): void
	{
		try
		{
			$root = $this->storage->getRoot($role);
		}
		catch (\Throwable $exception)
		{
			return;
		}

		if (!is_dir($root) || !is_readable($root))
		{
			return;
		}

		$rootLength = strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($iterator as $entry)
		{
			if (!$entry instanceof \SplFileInfo || $entry->isLink() || !$entry->isFile())
			{
				continue;
			}

			if (in_array($entry->getFilename(), self::PROTECTION_FILES, true))
			{
				continue;
			}

			$key = $this->normaliseKey(substr($entry->getPathname(), $rootLength));
			$absolutePath = $this->normaliseAbsolutePath(
				$entry->getRealPath() !== false ? $entry->getRealPath() : $entry->getPathname()
			);

			if ($key === '' || isset($referenced[$key]) || isset($referencedPaths[$absolutePath]))
			{
				continue;
			}

			$temporary = str_contains($entry->getFilename(), '.part-');
			$items[] = $this->makeItem(
				'unreferenced',
				$role,
				$key,
				max(0, (int) $entry->getSize()),
				0,
				'',
				'',
				0,
				$temporary
					? 'COM_AUDIOARCHIVE_MAINTENANCE_STALE_REASON_TEMPORARY'
					: 'COM_AUDIOARCHIVE_MAINTENANCE_STALE_REASON_UNREFERENCED'
			);
		}
	}

	/**
	 * @brief Construct one cleanup candidate and its revalidation token.
	 *
	 * @return array<string, mixed> Candidate data.
	 */
	private function makeItem(
		string $kind,
		string $role,
		string $storageKey,
		int $size,
		int $clipId,
		string $clipTitle,
		string $originalFilename,
		int $recordId,
		string $reason
	): array
	{
		$storageKey = $this->normaliseKey($storageKey);
		$token = hash('sha256', implode('|', [$kind, $role, $storageKey, $clipId, $recordId]));

		return [
			'token' => $token,
			'kind' => $kind,
			'role' => $role,
			'storage_key' => $storageKey,
			'size' => max(0, $size),
			'clip_id' => $clipId,
			'clip_title' => $clipTitle,
			'original_filename' => $originalFilename,
			'record_id' => $recordId,
			'reason' => $reason,
		];
	}

	/**
	 * @brief Delete one revalidated cleanup candidate.
	 *
	 * @param array<string, mixed> $item Candidate data.
	 *
	 * @return void
	 */
	private function deleteStaleItem(array $item): void
	{
		$kind = (string) $item['kind'];
		$role = (string) $item['role'];
		$storageKey = (string) $item['storage_key'];

		if ($kind === 'unreferenced')
		{
			if (!$this->storage->deleteManagedFile($role, $storageKey))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_DELETE_FILE_FAILED'));
			}

			return;
		}

		if ($kind === 'stale_preview')
		{
			$this->deletePreviewRecord((int) $item['record_id'], (int) $item['clip_id']);
		}
		elseif ($kind === 'stale_waveform')
		{
			$this->deleteWaveformRecord((int) $item['record_id'], (int) $item['clip_id']);
		}
		else
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_CHANGED'));
		}

		if ($storageKey !== '' && !$this->storage->deleteManagedFile($role, $storageKey))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_DELETE_FILE_FAILED'));
		}
	}

	/**
	 * @brief Remove one stale preview record and refresh clip preview status.
	 */
	private function deletePreviewRecord(int $recordId, int $clipId): void
	{
		$this->database->transactionStart();

		try
		{
			$query = $this->database->getQuery(true)
				->delete($this->database->quoteName('#__audioarchive_files'))
				->where($this->database->quoteName('id') . ' = :recordId')
				->where($this->database->quoteName('clip_id') . ' = :clipId')
				->where($this->database->quoteName('file_role') . ' = ' . $this->database->quote('preview'))
				->bind(':recordId', $recordId, ParameterType::INTEGER)
				->bind(':clipId', $clipId, ParameterType::INTEGER);
			$this->database->setQuery($query)->execute();
			$status = $this->hasPreview($clipId)
				? 'stale'
				: ($this->originalRequiresPreview($clipId) ? 'unavailable' : 'not_required');
			$this->updateClipStatus($clipId, 'preview_status', $status);
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}
	}

	/**
	 * @brief Remove one stale waveform record and refresh clip waveform status.
	 */
	private function deleteWaveformRecord(int $recordId, int $clipId): void
	{
		$this->database->transactionStart();

		try
		{
			$query = $this->database->getQuery(true)
				->delete($this->database->quoteName('#__audioarchive_waveforms'))
				->where($this->database->quoteName('id') . ' = :recordId')
				->where($this->database->quoteName('clip_id') . ' = :clipId')
				->bind(':recordId', $recordId, ParameterType::INTEGER)
				->bind(':clipId', $clipId, ParameterType::INTEGER);
			$this->database->setQuery($query)->execute();
			$this->updateClipStatus($clipId, 'waveform_status', $this->hasWaveform($clipId) ? 'stale' : 'missing');
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}
	}

	/**
	 * @brief Check whether a clip still has a preview record.
	 */
	private function hasPreview(int $clipId): bool
	{
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_files'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('file_role') . ' = ' . $this->database->quote('preview'))
			->bind(':clipId', $clipId, ParameterType::INTEGER);

		return (int) $this->database->setQuery($query)->loadResult() > 0;
	}

	/**
	 * @brief Check whether a clip still has a waveform record.
	 */
	private function hasWaveform(int $clipId): bool
	{
		$query = $this->database->getQuery(true)
			->select('COUNT(*)')
			->from($this->database->quoteName('#__audioarchive_waveforms'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->bind(':clipId', $clipId, ParameterType::INTEGER);

		return (int) $this->database->setQuery($query)->loadResult() > 0;
	}

	/**
	 * @brief Determine whether the current original needs a browser preview.
	 */
	private function originalRequiresPreview(int $clipId): bool
	{
		$query = $this->database->getQuery(true)
			->select(['audio_codec', 'container_format'])
			->from($this->database->quoteName('#__audioarchive_files'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->where($this->database->quoteName('file_role') . ' = ' . $this->database->quote('original'))
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$row = $this->database->setQuery($query)->loadObject();

		if (!$row)
		{
			return true;
		}

		$codec = strtoupper((string) $row->audio_codec);
		$container = strtoupper((string) $row->container_format);

		return str_contains($codec, 'ALAC')
			|| str_contains($codec, 'AC-3')
			|| $container === 'ADTS'
			|| $codec === 'FLAC';
	}

	/**
	 * @brief Update one allowlisted derivative status field.
	 */
	private function updateClipStatus(int $clipId, string $field, string $status): void
	{
		if (!in_array($field, ['preview_status', 'waveform_status'], true))
		{
			throw new \InvalidArgumentException('Invalid derivative status field.');
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
	 * @brief Return a managed file size when safely readable.
	 */
	private function managedFileSize(string $role, string $storageKey): int
	{
		try
		{
			$path = $this->storage->resolveManagedPath($role, $storageKey);
		}
		catch (\Throwable $exception)
		{
			return 0;
		}

		return is_file($path) && !is_link($path) ? max(0, (int) filesize($path)) : 0;
	}

	/**
	 * @brief Normalise an absolute path for safe equality comparison.
	 */
	private function normaliseAbsolutePath(string $path): string
	{
		return rtrim(str_replace('\\', '/', $path), '/');
	}


	/**
	 * @brief Normalise a managed storage key.
	 */
	private function normaliseKey(string $key): string
	{
		return ltrim(str_replace('\\', '/', trim($key)), '/');
	}
}
