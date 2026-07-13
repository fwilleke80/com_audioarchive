<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Perform non-destructive integrity checks for Audio Archive data and storage.
 */
class IntegrityService
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
     * @brief Construct the integrity service.
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
     * @brief Scan database relationships and managed storage without modifying either.
     *
     * Full SHA-256 verification is intentionally excluded because hashing every audio
     * file during an ordinary page request would be expensive. The maintenance
     * controller exposes explicit selected-item verification for that purpose.
     *
     * @return array<string, mixed> Structured integrity report.
     */
    public function run(): array
    {
        $issues = [];
        $referencedKeys = [
            'original' => [],
            'preview' => [],
            'waveform' => [],
        ];
        $clips = $this->loadClipsWithOriginals();
        $fileRows = $this->loadFileRows();
        $waveformRows = $this->loadWaveformRows();
        $clipIds = [];
        $originalRecordCount = 0;

        foreach ($clips as $clip)
        {
            $clipId = (int) $clip->id;
            $clipIds[$clipId] = true;

            if ((int) $clip->catid > 0 && (int) $clip->category_exists === 0)
            {
                $issues[] = $this->issue(
                    'error',
                    'missing_category',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_CATEGORY',
                    $clip,
                    Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_MISSING_CATEGORY', (int) $clip->catid)
                );
            }

            $technicalMetadata = trim((string) $clip->technical_metadata);

            if ((string) $clip->metadata_status !== 'available')
            {
                $issues[] = $this->issue(
                    'warning',
                    'metadata_status',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_METADATA_STATUS',
                    $clip,
                    Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_METADATA_STATUS',
                        (string) $clip->metadata_status
                    ),
                    true
                );
            }

            if ($technicalMetadata === '' || !is_array(json_decode($technicalMetadata, true)))
            {
                $issues[] = $this->issue(
                    'warning',
                    'invalid_metadata_json',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_INVALID_METADATA_JSON',
                    $clip,
                    Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_INVALID_METADATA_JSON'),
                    true
                );
            }

            if ($clip->file_id === null)
            {
                $issues[] = $this->issue(
                    'error',
                    'missing_original_record',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_ORIGINAL_RECORD',
                    $clip,
                    Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_MISSING_ORIGINAL_RECORD')
                );
                continue;
            }

            $originalRecordCount++;
            $storageKey = (string) $clip->storage_key;

            if ($storageKey !== '')
            {
                $referencedKeys['original'][$this->normaliseKey($storageKey)] = true;
            }

            $pathResult = $this->inspectManagedPath('original', $storageKey);

            if (!$pathResult['valid'])
            {
                $issues[] = $this->issue(
                    'error',
                    (string) $pathResult['code'],
                    (string) $pathResult['label'],
                    $clip,
                    (string) $pathResult['detail'],
                    true,
                    $storageKey
                );
                continue;
            }

            $actualSize = (int) $pathResult['size'];

            if ($actualSize !== (int) $clip->file_size)
            {
                $issues[] = $this->issue(
                    'warning',
                    'file_size_mismatch',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_FILE_SIZE_MISMATCH',
                    $clip,
                    Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_FILE_SIZE_MISMATCH',
                        (int) $clip->file_size,
                        $actualSize
                    ),
                    true,
                    $storageKey
                );
            }

            $checksum = strtolower(trim((string) $clip->checksum_sha256));

            if (!preg_match('/^[0-9a-f]{64}$/', $checksum))
            {
                $issues[] = $this->issue(
                    'warning',
                    'invalid_checksum',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_INVALID_CHECKSUM',
                    $clip,
                    Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_INVALID_CHECKSUM'),
                    true,
                    $storageKey
                );
            }

            if ((int) $clip->duration_ms !== (int) $clip->file_duration_ms)
            {
                $issues[] = $this->issue(
                    'warning',
                    'duration_mismatch',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_DURATION_MISMATCH',
                    $clip,
                    Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_DURATION_MISMATCH',
                        (int) $clip->duration_ms,
                        (int) $clip->file_duration_ms
                    ),
                    true,
                    $storageKey
                );
            }

            if ((int) $clip->is_available !== 1)
            {
                $issues[] = $this->issue(
                    'warning',
                    'availability_mismatch',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_AVAILABILITY_MISMATCH',
                    $clip,
                    Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_AVAILABILITY_MISMATCH'),
                    true,
                    $storageKey
                );
            }
        }

        foreach ($fileRows as $file)
        {
            $role = (string) $file->file_role;
            $storageKey = (string) $file->storage_key;

            if (isset($referencedKeys[$role]) && $storageKey !== '')
            {
                $referencedKeys[$role][$this->normaliseKey($storageKey)] = true;
            }

            if (!isset($clipIds[(int) $file->clip_id]))
            {
                $issues[] = $this->standaloneIssue(
                    'error',
                    'orphan_file_record',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_ORPHAN_FILE_RECORD',
                    Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_ORPHAN_FILE_RECORD',
                        (int) $file->id,
                        (int) $file->clip_id,
                        $role
                    ),
                    $storageKey
                );
                continue;
            }

            if ($role === 'original')
            {
                continue;
            }

            if (!in_array($role, ['preview'], true))
            {
                $issues[] = $this->standaloneIssue(
                    'warning',
                    'unknown_file_role',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_UNKNOWN_FILE_ROLE',
                    Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_UNKNOWN_FILE_ROLE', $role),
                    $storageKey,
                    (int) $file->clip_id
                );
                continue;
            }

            $pathResult = $this->inspectManagedPath($role, $storageKey);

            if (!$pathResult['valid'])
            {
                $issues[] = $this->standaloneIssue(
                    'warning',
                    'missing_derivative',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_DERIVATIVE',
                    (string) $pathResult['detail'],
                    $storageKey,
                    (int) $file->clip_id
                );
            }
        }

        foreach ($waveformRows as $waveform)
        {
            $storageKey = (string) $waveform->storage_key;

            if ($storageKey !== '')
            {
                $referencedKeys['waveform'][$this->normaliseKey($storageKey)] = true;
            }

            if (!isset($clipIds[(int) $waveform->clip_id]))
            {
                $issues[] = $this->standaloneIssue(
                    'error',
                    'orphan_waveform_record',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_ORPHAN_WAVEFORM_RECORD',
                    Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_ORPHAN_WAVEFORM_RECORD',
                        (int) $waveform->id,
                        (int) $waveform->clip_id
                    ),
                    $storageKey
                );
                continue;
            }

            $pathResult = $this->inspectManagedPath('waveform', $storageKey);

            if (!$pathResult['valid'])
            {
                $issues[] = $this->standaloneIssue(
                    'warning',
                    'missing_waveform_file',
                    'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_WAVEFORM_FILE',
                    (string) $pathResult['detail'],
                    $storageKey,
                    (int) $waveform->clip_id
                );
            }
        }

        $this->appendDuplicateIssues($issues);
        $this->appendBrokenTagMappingIssues($issues, $clipIds);
        $this->appendStuckJobIssues($issues);

        $filesystemCounts = [];

        foreach (['original', 'preview', 'waveform'] as $role)
        {
            $filesystemCounts[$role] = $this->appendUnreferencedFilesystemIssues(
                $issues,
                $role,
                $referencedKeys[$role]
            );
        }

        usort(
            $issues,
            static function (array $left, array $right): int
            {
                $severityOrder = ['error' => 0, 'warning' => 1, 'info' => 2];
                $severityComparison = ($severityOrder[$left['severity']] ?? 9)
                    <=> ($severityOrder[$right['severity']] ?? 9);

                if ($severityComparison !== 0)
                {
                    return $severityComparison;
                }

                return strcasecmp(
                    (string) ($left['clip_title'] ?: $left['storage_key'] ?: $left['code']),
                    (string) ($right['clip_title'] ?: $right['storage_key'] ?: $right['code'])
                );
            }
        );

        $actionableClips = $this->buildActionableClips($issues);
        $severityCounts = ['error' => 0, 'warning' => 0, 'info' => 0];

        foreach ($issues as $issue)
        {
            $severity = (string) $issue['severity'];

            if (isset($severityCounts[$severity]))
            {
                $severityCounts[$severity]++;
            }
        }

        return [
            'checked_at' => Factory::getDate()->toSql(),
            'summary' => [
                'clips' => count($clips),
                'original_records' => $originalRecordCount,
                'managed_original_files' => (int) ($filesystemCounts['original']['files'] ?? 0),
                'issues' => count($issues),
                'errors' => $severityCounts['error'],
                'warnings' => $severityCounts['warning'],
                'actionable_clips' => count($actionableClips),
            ],
            'issues' => $issues,
            'actionable_clips' => $actionableClips,
        ];
    }

    /**
     * @brief Load clips and their mandatory original-file relation.
     *
     * @return object[] Clip rows.
     */
    private function loadClipsWithOriginals(): array
    {
        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('a.id'),
                $this->database->quoteName('a.title'),
                $this->database->quoteName('a.original_filename'),
                $this->database->quoteName('a.catid'),
                $this->database->quoteName('a.duration_ms'),
                $this->database->quoteName('a.metadata_status'),
                $this->database->quoteName('a.technical_metadata'),
                $this->database->quoteName('f.id', 'file_id'),
                $this->database->quoteName('f.storage_key'),
                $this->database->quoteName('f.file_size'),
                $this->database->quoteName('f.duration_ms', 'file_duration_ms'),
                $this->database->quoteName('f.checksum_sha256'),
                $this->database->quoteName('f.is_available'),
                'CASE WHEN ' . $this->database->quoteName('c.id') . ' IS NULL THEN 0 ELSE 1 END AS '
                    . $this->database->quoteName('category_exists'),
            ])
            ->from($this->database->quoteName('#__audioarchive_clips', 'a'))
            ->leftJoin(
                $this->database->quoteName('#__audioarchive_files', 'f')
                . ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
                . ' AND ' . $this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original')
            )
            ->leftJoin(
                $this->database->quoteName('#__categories', 'c')
                . ' ON ' . $this->database->quoteName('c.id') . ' = ' . $this->database->quoteName('a.catid')
                . ' AND ' . $this->database->quoteName('c.extension') . ' = ' . $this->database->quote('com_audioarchive')
            )
            ->order($this->database->quoteName('a.id') . ' ASC');

        return $this->database->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @brief Load all original and preview records.
     *
     * @return object[] File rows.
     */
    private function loadFileRows(): array
    {
        $query = $this->database->getQuery(true)
            ->select(['id', 'clip_id', 'file_role', 'storage_key'])
            ->from($this->database->quoteName('#__audioarchive_files'));

        return $this->database->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @brief Load all waveform records.
     *
     * @return object[] Waveform rows.
     */
    private function loadWaveformRows(): array
    {
        $query = $this->database->getQuery(true)
            ->select(['id', 'clip_id', 'storage_key'])
            ->from($this->database->quoteName('#__audioarchive_waveforms'));

        return $this->database->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * @brief Inspect one managed path without modifying its availability state.
     *
     * @param string $role Managed storage role.
     * @param string $storageKey Relative storage key.
     *
     * @return array{valid:bool,code:string,label:string,detail:string,size:int}
     */
    private function inspectManagedPath(string $role, string $storageKey): array
    {
        try
        {
            $path = $this->storage->resolveManagedPath($role, $storageKey);
        }
        catch (\Throwable $exception)
        {
            return [
                'valid' => false,
                'code' => 'invalid_storage_key',
                'label' => 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_INVALID_STORAGE_KEY',
                'detail' => Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_INVALID_STORAGE_KEY', $exception->getMessage()),
                'size' => 0,
            ];
        }

        if (!file_exists($path))
        {
            return [
                'valid' => false,
                'code' => 'missing_managed_file',
                'label' => 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_MANAGED_FILE',
                'detail' => Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_MISSING_MANAGED_FILE'),
                'size' => 0,
            ];
        }

        if (is_link($path) || !is_file($path))
        {
            return [
                'valid' => false,
                'code' => 'invalid_managed_file_type',
                'label' => 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_INVALID_MANAGED_FILE_TYPE',
                'detail' => Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_INVALID_MANAGED_FILE_TYPE'),
                'size' => 0,
            ];
        }

        if (!is_readable($path))
        {
            return [
                'valid' => false,
                'code' => 'unreadable_managed_file',
                'label' => 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_UNREADABLE_MANAGED_FILE',
                'detail' => Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_UNREADABLE_MANAGED_FILE'),
                'size' => 0,
            ];
        }

        return [
            'valid' => true,
            'code' => '',
            'label' => '',
            'detail' => '',
            'size' => max(0, (int) filesize($path)),
        ];
    }

    /**
     * @brief Append one issue for every duplicate original checksum group.
     *
     * @param array<int, array<string, mixed>> $issues Issue collection.
     *
     * @return void
     */
    private function appendDuplicateIssues(array &$issues): void
    {
        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('f.checksum_sha256'),
                'COUNT(*) AS ' . $this->database->quoteName('duplicate_count'),
                'GROUP_CONCAT(' . $this->database->quoteName('f.clip_id') . ' ORDER BY '
                    . $this->database->quoteName('f.clip_id') . ' SEPARATOR \',\') AS '
                    . $this->database->quoteName('clip_ids'),
            ])
            ->from($this->database->quoteName('#__audioarchive_files', 'f'))
            ->where($this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original'))
            ->where($this->database->quoteName('f.checksum_sha256') . ' <> ' . $this->database->quote(''))
            ->group($this->database->quoteName('f.checksum_sha256'))
            ->having('COUNT(*) > 1');
        $groups = $this->database->setQuery($query)->loadObjectList() ?: [];

        foreach ($groups as $group)
        {
            $checksum = (string) $group->checksum_sha256;
            $issues[] = $this->standaloneIssue(
                'info',
                'duplicate_checksum',
                'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_DUPLICATE_CHECKSUM',
                Text::sprintf(
                    'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_DUPLICATE_CHECKSUM',
                    (int) $group->duplicate_count,
                    (string) $group->clip_ids,
                    substr($checksum, 0, 16)
                )
            );
        }
    }

    /**
     * @brief Append issues for broken Joomla tag mappings.
     *
     * @param array<int, array<string, mixed>> $issues Issue collection.
     * @param array<int, bool> $clipIds Existing clip identifiers.
     *
     * @return void
     */
    private function appendBrokenTagMappingIssues(array &$issues, array $clipIds): void
    {
        try
        {
            $query = $this->database->getQuery(true)
                ->select([
                    $this->database->quoteName('m.content_item_id'),
                    $this->database->quoteName('m.tag_id'),
                    $this->database->quoteName('t.id', 'existing_tag_id'),
                ])
                ->from($this->database->quoteName('#__contentitem_tag_map', 'm'))
                ->leftJoin(
                    $this->database->quoteName('#__tags', 't')
                    . ' ON ' . $this->database->quoteName('t.id') . ' = ' . $this->database->quoteName('m.tag_id')
                )
                ->where($this->database->quoteName('m.type_alias') . ' = ' . $this->database->quote('com_audioarchive.clip'));
            $mappings = $this->database->setQuery($query)->loadObjectList() ?: [];

            foreach ($mappings as $mapping)
            {
                $clipId = (int) $mapping->content_item_id;

                if (!isset($clipIds[$clipId]))
                {
                    $issues[] = $this->standaloneIssue(
                        'warning',
                        'orphan_tag_mapping',
                        'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_ORPHAN_TAG_MAPPING',
                        Text::sprintf(
                            'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_ORPHAN_TAG_MAPPING',
                            (int) $mapping->tag_id,
                            $clipId
                        )
                    );
                }
                elseif ($mapping->existing_tag_id === null)
                {
                    $issues[] = $this->standaloneIssue(
                        'warning',
                        'missing_tag',
                        'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_MISSING_TAG',
                        Text::sprintf(
                            'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_MISSING_TAG',
                            (int) $mapping->tag_id,
                            $clipId
                        ),
                        '',
                        $clipId
                    );
                }
            }
        }
        catch (\Throwable $exception)
        {
            $issues[] = $this->standaloneIssue(
                'info',
                'tag_check_unavailable',
                'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_TAG_CHECK_UNAVAILABLE',
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_TAG_CHECK_UNAVAILABLE')
            );
        }
    }

    /**
     * @brief Append issues for processing jobs that appear abandoned.
     *
     * @param array<int, array<string, mixed>> $issues Issue collection.
     *
     * @return void
     */
    private function appendStuckJobIssues(array &$issues): void
    {
        $now = Factory::getDate()->toSql();
        $query = $this->database->getQuery(true)
            ->select(['id', 'clip_id', 'job_type', 'locked_until'])
            ->from($this->database->quoteName('#__audioarchive_jobs'))
            ->where($this->database->quoteName('state') . ' = ' . $this->database->quote('running'))
            ->where($this->database->quoteName('locked_until') . ' IS NOT NULL')
            ->where($this->database->quoteName('locked_until') . ' < ' . $this->database->quote($now));
        $jobs = $this->database->setQuery($query)->loadObjectList() ?: [];

        foreach ($jobs as $job)
        {
            $issues[] = $this->standaloneIssue(
                'warning',
                'stuck_job',
                'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_STUCK_JOB',
                Text::sprintf(
                    'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_STUCK_JOB',
                    (int) $job->id,
                    (string) $job->job_type,
                    (string) $job->locked_until
                ),
                '',
                (int) $job->clip_id
            );
        }
    }

    /**
     * @brief Find files in one managed root that have no database reference.
     *
     * @param array<int, array<string, mixed>> $issues Issue collection.
     * @param string $role Managed storage role.
     * @param array<string, bool> $referencedKeys Referenced normalised keys.
     *
     * @return array{files:int,unreferenced:int}
     */
    private function appendUnreferencedFilesystemIssues(array &$issues, string $role, array $referencedKeys): array
    {
        try
        {
            $root = $this->storage->getRoot($role);
        }
        catch (\Throwable $exception)
        {
            $issues[] = $this->standaloneIssue(
                'error',
                'storage_root_invalid',
                'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_STORAGE_ROOT_INVALID',
                Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_STORAGE_ROOT_INVALID', $role, $exception->getMessage())
            );
            return ['files' => 0, 'unreferenced' => 0];
        }

        if (!is_dir($root) || !is_readable($root))
        {
            if ($role !== 'original' && $referencedKeys === [])
            {
                return ['files' => 0, 'unreferenced' => 0];
            }

            $issues[] = $this->standaloneIssue(
                'warning',
                'storage_root_unavailable',
                'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_STORAGE_ROOT_UNAVAILABLE',
                Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_STORAGE_ROOT_UNAVAILABLE', $role)
            );
            return ['files' => 0, 'unreferenced' => 0];
        }

        $files = 0;
        $unreferenced = 0;
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

            $files++;
            $absolutePath = $entry->getPathname();
            $storageKey = $this->normaliseKey(substr($absolutePath, $rootLength));

            if (isset($referencedKeys[$storageKey]))
            {
                continue;
            }

            $unreferenced++;
            $temporary = str_contains($entry->getFilename(), '.part-');
            $issues[] = $this->standaloneIssue(
                $temporary ? 'warning' : 'info',
                $temporary ? 'abandoned_temporary_file' : 'unreferenced_managed_file',
                $temporary
                    ? 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_ABANDONED_TEMPORARY_FILE'
                    : 'COM_AUDIOARCHIVE_MAINTENANCE_ISSUE_UNREFERENCED_MANAGED_FILE',
                Text::sprintf(
                    $temporary
                        ? 'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_ABANDONED_TEMPORARY_FILE'
                        : 'COM_AUDIOARCHIVE_MAINTENANCE_DETAIL_UNREFERENCED_MANAGED_FILE',
                    $role
                ),
                $storageKey
            );
        }

        return ['files' => $files, 'unreferenced' => $unreferenced];
    }

    /**
     * @brief Build one clip-bound issue.
     *
     * @param string $severity Severity identifier.
     * @param string $code Stable issue code.
     * @param string $label Language key for the issue label.
     * @param object $clip Clip row.
     * @param string $detail Human-readable details.
     * @param bool $actionable Whether file maintenance can act on the clip.
     * @param string $storageKey Optional storage key.
     *
     * @return array<string, mixed> Issue row.
     */
    private function issue(
        string $severity,
        string $code,
        string $label,
        object $clip,
        string $detail,
        bool $actionable = false,
        string $storageKey = ''
    ): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'label' => $label,
            'clip_id' => (int) $clip->id,
            'clip_title' => (string) $clip->title,
            'original_filename' => (string) $clip->original_filename,
            'storage_key' => $storageKey,
            'detail' => $detail,
            'actionable' => $actionable,
        ];
    }

    /**
     * @brief Build one issue not necessarily tied to a clip row.
     *
     * @param string $severity Severity identifier.
     * @param string $code Stable issue code.
     * @param string $label Language key for the issue label.
     * @param string $detail Human-readable details.
     * @param string $storageKey Optional storage key.
     * @param int $clipId Optional clip identifier.
     *
     * @return array<string, mixed> Issue row.
     */
    private function standaloneIssue(
        string $severity,
        string $code,
        string $label,
        string $detail,
        string $storageKey = '',
        int $clipId = 0
    ): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'label' => $label,
            'clip_id' => $clipId,
            'clip_title' => '',
            'original_filename' => '',
            'storage_key' => $storageKey,
            'detail' => $detail,
            'actionable' => false,
        ];
    }

    /**
     * @brief Build one row per clip that supports selected-item maintenance.
     *
     * @param array<int, array<string, mixed>> $issues All report issues.
     *
     * @return array<int, array<string, mixed>> Actionable clip rows.
     */
    private function buildActionableClips(array $issues): array
    {
        $clips = [];

        foreach ($issues as $issue)
        {
            $clipId = (int) $issue['clip_id'];

            if ($clipId <= 0 || !(bool) $issue['actionable'])
            {
                continue;
            }

            if (!isset($clips[$clipId]))
            {
                $clips[$clipId] = [
                    'id' => $clipId,
                    'title' => (string) $issue['clip_title'],
                    'original_filename' => (string) $issue['original_filename'],
                    'issues' => [],
                ];
            }

            $clips[$clipId]['issues'][(string) $issue['label']] = true;
        }

        foreach ($clips as &$clip)
        {
            $clip['issues'] = array_keys($clip['issues']);
        }
        unset($clip);

        uasort(
            $clips,
            static fn (array $left, array $right): int => strcasecmp((string) $left['title'], (string) $right['title'])
        );

        return array_values($clips);
    }

    /**
     * @brief Normalise a managed storage key for comparisons.
     *
     * @param string $key Storage key.
     *
     * @return string Normalised key.
     */
    private function normaliseKey(string $key): string
    {
        return ltrim(str_replace('\\', '/', $key), '/');
    }
}
