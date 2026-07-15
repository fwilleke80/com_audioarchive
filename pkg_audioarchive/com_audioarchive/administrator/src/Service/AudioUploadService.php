<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisJobService;
use Willeke\Component\Audioarchive\Administrator\Service\Analysis\AnalysisRepositoryService;

\defined('_JEXEC') or die;

/**
 * @brief Validate uploads and attach original audio files to clips.
 */
class AudioUploadService
{
    /** @var array<string, string[]> */
    private const MIME_TYPES = [
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'],
        'mp4' => ['audio/mp4', 'video/mp4', 'application/mp4'],
        'aac' => ['audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts'],
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mp3'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'oga' => ['audio/ogg', 'application/ogg'],
        'opus' => ['audio/ogg', 'audio/opus', 'application/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'webm' => ['audio/webm', 'video/webm'],
    ];

    /** @var DatabaseInterface */
    private DatabaseInterface $database;

    /** @var Registry */
    private Registry $params;

    /** @var User */
    private User $user;

    /** @var MediaInspectorService */
    private MediaInspectorService $inspector;

    /** @var ManagedStorageService */
    private ManagedStorageService $storage;

    /**
     * @brief Construct the upload service.
     *
     * @param DatabaseInterface $database Joomla database connection.
     * @param Registry $params Component parameters.
     * @param User $user Current administrator.
     */
    public function __construct(DatabaseInterface $database, Registry $params, User $user)
    {
        $this->database = $database;
        $this->params = $params;
        $this->user = $user;
        $this->inspector = new MediaInspectorService();
        $this->storage = new ManagedStorageService($params);
    }

    /**
     * @brief Validate one PHP upload before saving the clip record.
     *
     * @param array<string, mixed> $upload PHP upload array.
     * @param int $excludeClipId Clip to exclude from duplicate detection during replacement.
     *
     * @return array<string, mixed> Prepared immutable upload data.
     */
    public function prepare(array $upload, int $excludeClipId = 0, string $duplicatePolicyOverride = ''): array
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK)
        {
            throw new \RuntimeException($this->uploadErrorMessage($error));
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $originalFilename = $this->normaliseOriginalFilename((string) ($upload['name'] ?? ''));

        if ($temporaryPath === '' || !is_file($temporaryPath) || !is_readable($temporaryPath))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_TEMPORARY_FILE'));
        }

        if ($originalFilename === '')
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_FILENAME'));
        }

        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $allowedExtensions = $this->getAllowedExtensions();

        if ($extension === '' || !in_array($extension, $allowedExtensions, true))
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_EXTENSION', $extension !== '' ? $extension : '?'));
        }

        $fileSize = max(0, (int) ($upload['size'] ?? filesize($temporaryPath)));
        $maximumMegabytes = max(0, (int) $this->params->get('maximum_file_size', 0));

        if ($maximumMegabytes > 0 && $fileSize > $maximumMegabytes * 1024 * 1024)
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_SIZE', $maximumMegabytes));
        }

        $metadata = $this->inspector->inspect($temporaryPath, $originalFilename);

        if (!(bool) ($metadata['valid'] ?? false))
        {
            $errors = array_filter((array) ($metadata['errors'] ?? []));
            throw new \RuntimeException(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_ERROR_UPLOAD_INVALID_MEDIA',
                    $errors !== [] ? implode(' ', $errors) : Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_INVALID_MEDIA_UNKNOWN')
                )
            );
        }

        $maximumDuration = max(0, (int) $this->params->get('maximum_duration', 0));
        $durationMs = max(0, (int) ($metadata['duration_ms'] ?? 0));

        if ($maximumDuration > 0 && $durationMs > $maximumDuration * 1000)
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_DURATION', $maximumDuration));
        }

        $warnings = (array) ($metadata['warnings'] ?? []);
        $mimeType = strtolower(trim((string) ($metadata['mime_type'] ?? '')));
        $expectedMimes = self::MIME_TYPES[$extension] ?? [];

        if ($mimeType !== '' && $expectedMimes !== [] && !in_array($mimeType, $expectedMimes, true))
        {
            $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_MIME_MISMATCH', $mimeType, $extension);
        }

        $configuredMimes = $this->getConfiguredMimeTypes();

        if ($configuredMimes !== [] && $mimeType !== '' && !in_array($mimeType, $configuredMimes, true))
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_MIME', $mimeType));
        }

        $checksum = hash_file('sha256', $temporaryPath);

        if (!is_string($checksum) || strlen($checksum) !== 64)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_CHECKSUM'));
        }

        $duplicate = $this->findDuplicate($checksum, $excludeClipId);
        $duplicatePolicy = $duplicatePolicyOverride !== ''
            ? $duplicatePolicyOverride
            : (string) $this->params->get('duplicate_policy', 'warn');

        if ($duplicate !== null && $duplicatePolicy === 'reject')
        {
            throw new \RuntimeException(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_ERROR_DUPLICATE_REJECTED',
                    $this->formatDuplicateDetails($duplicate)
                )
            );
        }

        if ($duplicate !== null && $duplicatePolicy === 'warn')
        {
            $warnings[] = Text::sprintf(
                'COM_AUDIOARCHIVE_WARNING_DUPLICATE_ALLOWED',
                $this->formatDuplicateDetails($duplicate)
            );
        }

        $metadata['warnings'] = array_values(array_unique(array_filter($warnings)));

        return [
            'temporary_path' => $temporaryPath,
            'original_filename' => $originalFilename,
            'extension' => $extension,
            'file_size' => $fileSize,
            'checksum_sha256' => $checksum,
            'metadata' => $metadata,
            'duplicate' => $duplicate,
            'preserve_source' => false,
        ];
    }

    /**
     * @brief Validate one existing file from the configured import inbox.
     *
     * @param string $path Absolute source path.
     * @param string $filename Original filename or relative inbox path.
     * @param string $duplicatePolicy Optional duplicate-policy override.
     * @param int $excludeClipId Clip excluded from duplicate detection.
     *
     * @return array<string, mixed> Prepared immutable file data.
     */
    public function prepareLocalFile(
        string $path,
        string $filename,
        string $duplicatePolicy = '',
        int $excludeClipId = 0
    ): array
    {
        $prepared = $this->prepare(
            [
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $path,
                'name' => basename($filename),
                'size' => is_file($path) ? (int) filesize($path) : 0,
            ],
            $excludeClipId,
            $duplicatePolicy
        );
        $prepared['preserve_source'] = true;
        $prepared['source_relative_path'] = str_replace('\\', '/', $filename);

        return $prepared;
    }

    /**
     * @brief Store a prepared upload and update its clip's managed metadata.
     *
     * @param int $clipId Clip identifier.
     * @param string $uuid Clip UUID.
     * @param array<string, mixed> $prepared Prepared upload data.
     *
     * @return object Stored file row.
     */
    public function storeForClip(int $clipId, string $uuid, array $prepared): object
    {
        if ($clipId <= 0)
        {
            throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ERROR_INVALID_CLIP_ID'));
        }

        if ($this->getOriginalFile($clipId) !== null)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_ORIGINAL_ALREADY_ATTACHED'));
        }

        $stored = $this->storage->storeOriginal(
            (string) $prepared['temporary_path'],
            $uuid,
            (string) $prepared['extension'],
            !(bool) ($prepared['preserve_source'] ?? false)
        );
        $metadata = (array) $prepared['metadata'];
        $now = Factory::getDate()->toSql();
        $file = (object) [
            'clip_id' => $clipId,
            'file_role' => 'original',
            'storage_key' => (string) $stored['storage_key'],
            'file_extension' => (string) $prepared['extension'],
            'mime_type' => (string) ($metadata['mime_type'] ?? ''),
            'container_format' => (string) ($metadata['container_format'] ?? ''),
            'audio_codec' => (string) ($metadata['audio_codec'] ?? ''),
            'file_size' => (int) $prepared['file_size'],
            'duration_ms' => (int) ($metadata['duration_ms'] ?? 0),
            'checksum_sha256' => (string) $prepared['checksum_sha256'],
            'created' => $now,
            'created_by' => (int) $this->user->id,
            'is_available' => 1,
            'processing_error' => '',
        ];
        $technicalMetadata = $this->technicalMetadataJson($metadata);
        $previewStatus = $this->requiresCompatibilityPreview($metadata) ? 'unavailable' : 'not_required';
        $originalFilename = (string) $prepared['original_filename'];
        $duration = (int) $file->duration_ms;
        $metadataStatus = 'available';
        $waveformStatus = 'missing';

        $this->database->transactionStart();

        try
        {
            $this->database->insertObject('#__audioarchive_files', $file, 'id');
            $query = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_clips'))
                ->set($this->database->quoteName('original_filename') . ' = :originalFilename')
                ->set($this->database->quoteName('duration_ms') . ' = :duration')
                ->set($this->database->quoteName('uploaded_at') . ' = :uploadedAt')
                ->set($this->database->quoteName('metadata_status') . ' = :metadataStatus')
                ->set($this->database->quoteName('preview_status') . ' = :previewStatus')
                ->set($this->database->quoteName('waveform_status') . ' = :waveformStatus')
                ->set($this->database->quoteName('technical_metadata') . ' = :technicalMetadata')
                ->where($this->database->quoteName('id') . ' = :clipId')
                ->bind(':originalFilename', $originalFilename, ParameterType::STRING)
                ->bind(':duration', $duration, ParameterType::INTEGER)
                ->bind(':uploadedAt', $now, ParameterType::STRING)
                ->bind(':metadataStatus', $metadataStatus, ParameterType::STRING)
                ->bind(':previewStatus', $previewStatus, ParameterType::STRING)
                ->bind(':waveformStatus', $waveformStatus, ParameterType::STRING)
                ->bind(':technicalMetadata', $technicalMetadata, ParameterType::STRING)
                ->bind(':clipId', $clipId, ParameterType::INTEGER);
            $this->database->setQuery($query)->execute();
            $this->database->transactionCommit();
        }
        catch (\Throwable $exception)
        {
            $this->database->transactionRollback();
            $this->storage->deleteManagedFile('original', (string) $stored['storage_key']);
            throw $exception;
        }

        $this->queueWaveformAfterUpload($clipId);

        return $file;
    }

    /**
     * @brief Replace a clip's original while preserving the previous file until commit.
     *
     * @param int $clipId Clip identifier.
     * @param string $uuid Clip UUID.
     * @param array<string, mixed> $prepared Prepared replacement upload.
     *
     * @return array{file:object,warnings:string[],previous_original_retained:bool} Updated file and cleanup warnings.
     */
    public function replaceForClip(int $clipId, string $uuid, array $prepared): array
    {
        $current = $this->getOriginalFile($clipId);

        if ($current === null)
        {
            return [
                'file' => $this->storeForClip($clipId, $uuid, $prepared),
                'warnings' => [],
                'previous_original_retained' => false,
            ];
        }

        $stored = $this->storage->storeReplacementOriginal(
            (string) $prepared['temporary_path'],
            $uuid,
            (string) $prepared['extension'],
            !(bool) ($prepared['preserve_source'] ?? false)
        );
        $metadata = (array) $prepared['metadata'];
        $now = Factory::getDate()->toSql();
        $technicalMetadata = $this->technicalMetadataJson($metadata);
        $hasPreview = $this->hasFileRole($clipId, 'preview');
        $hasWaveform = $this->hasWaveform($clipId);
        $previewStatus = $hasPreview
            ? 'stale'
            : ($this->requiresCompatibilityPreview($metadata) ? 'unavailable' : 'not_required');
        $waveformStatus = $hasWaveform ? 'stale' : 'missing';
        $storageKey = (string) $stored['storage_key'];
        $extension = (string) $prepared['extension'];
        $mimeType = (string) ($metadata['mime_type'] ?? '');
        $container = (string) ($metadata['container_format'] ?? '');
        $codec = (string) ($metadata['audio_codec'] ?? '');
        $fileSize = (int) $prepared['file_size'];
        $duration = (int) ($metadata['duration_ms'] ?? 0);
        $checksum = (string) $prepared['checksum_sha256'];
        $userId = (int) $this->user->id;
        $available = 1;
        $processingError = '';
        $fileId = (int) $current->id;
        $originalFilename = (string) $prepared['original_filename'];
        $metadataStatus = 'available';

        $this->database->transactionStart();

        try
        {
            $fileQuery = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_files'))
                ->set($this->database->quoteName('storage_key') . ' = :storageKey')
                ->set($this->database->quoteName('file_extension') . ' = :extension')
                ->set($this->database->quoteName('mime_type') . ' = :mimeType')
                ->set($this->database->quoteName('container_format') . ' = :container')
                ->set($this->database->quoteName('audio_codec') . ' = :codec')
                ->set($this->database->quoteName('file_size') . ' = :fileSize')
                ->set($this->database->quoteName('duration_ms') . ' = :duration')
                ->set($this->database->quoteName('checksum_sha256') . ' = :checksum')
                ->set($this->database->quoteName('created') . ' = :created')
                ->set($this->database->quoteName('created_by') . ' = :createdBy')
                ->set($this->database->quoteName('is_available') . ' = :available')
                ->set($this->database->quoteName('processing_error') . ' = :processingError')
                ->where($this->database->quoteName('id') . ' = :fileId')
                ->bind(':storageKey', $storageKey, ParameterType::STRING)
                ->bind(':extension', $extension, ParameterType::STRING)
                ->bind(':mimeType', $mimeType, ParameterType::STRING)
                ->bind(':container', $container, ParameterType::STRING)
                ->bind(':codec', $codec, ParameterType::STRING)
                ->bind(':fileSize', $fileSize, ParameterType::INTEGER)
                ->bind(':duration', $duration, ParameterType::INTEGER)
                ->bind(':checksum', $checksum, ParameterType::STRING)
                ->bind(':created', $now, ParameterType::STRING)
                ->bind(':createdBy', $userId, ParameterType::INTEGER)
                ->bind(':available', $available, ParameterType::INTEGER)
                ->bind(':processingError', $processingError, ParameterType::STRING)
                ->bind(':fileId', $fileId, ParameterType::INTEGER);
            $this->database->setQuery($fileQuery)->execute();

            $clipQuery = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_clips'))
                ->set($this->database->quoteName('original_filename') . ' = :originalFilename')
                ->set($this->database->quoteName('duration_ms') . ' = :duration')
                ->set($this->database->quoteName('metadata_status') . ' = :metadataStatus')
                ->set($this->database->quoteName('preview_status') . ' = :previewStatus')
                ->set($this->database->quoteName('waveform_status') . ' = :waveformStatus')
                ->set($this->database->quoteName('technical_metadata') . ' = :technicalMetadata')
                ->where($this->database->quoteName('id') . ' = :clipId')
                ->bind(':originalFilename', $originalFilename, ParameterType::STRING)
                ->bind(':duration', $duration, ParameterType::INTEGER)
                ->bind(':metadataStatus', $metadataStatus, ParameterType::STRING)
                ->bind(':previewStatus', $previewStatus, ParameterType::STRING)
                ->bind(':waveformStatus', $waveformStatus, ParameterType::STRING)
                ->bind(':technicalMetadata', $technicalMetadata, ParameterType::STRING)
                ->bind(':clipId', $clipId, ParameterType::INTEGER);
            $this->database->setQuery($clipQuery)->execute();
            $this->synchroniseWaveformAnalysisStatus($clipId, $waveformStatus);
            $this->database->transactionCommit();
        }
        catch (\Throwable $exception)
        {
            $this->database->transactionRollback();

            try
            {
                $this->storage->deleteManagedFile('original', $storageKey);
            }
            catch (\Throwable $cleanupException)
            {
                // Preserve the database exception; the staged replacement remains unreferenced.
            }

            throw $exception;
        }

        $this->queueWaveformAfterUpload($clipId);
        $warnings = [];
        $previousOriginalRetained = (bool) ($prepared['retain_previous_original'] ?? false);

        if (!$previousOriginalRetained)
        {
            try
            {
                if (!$this->storage->deleteManagedFile('original', (string) $current->storage_key))
                {
                    $warnings[] = Text::_('COM_AUDIOARCHIVE_WARNING_OLD_ORIGINAL_NOT_DELETED');
                }
            }
            catch (\Throwable $exception)
            {
                $warnings[] = Text::_('COM_AUDIOARCHIVE_WARNING_OLD_ORIGINAL_NOT_DELETED');
            }
        }

        $current->storage_key = $storageKey;
        $current->file_extension = $extension;
        $current->mime_type = $mimeType;
        $current->container_format = $container;
        $current->audio_codec = $codec;
        $current->file_size = $fileSize;
        $current->duration_ms = $duration;
        $current->checksum_sha256 = $checksum;
        $current->created = $now;
        $current->created_by = $userId;
        $current->is_available = 1;
        $current->processing_error = '';

        return [
            'file' => $current,
            'warnings' => $warnings,
            'previous_original_retained' => $previousOriginalRetained,
        ];
    }

    /**
     * @brief Reinspect the stored original and refresh technical metadata.
     *
     * @param int $clipId Clip identifier.
     *
     * @return string[] Inspector warnings.
     */
    public function reanalyseForClip(int $clipId): array
    {
        $file = $this->requireOriginalFile($clipId);
        $path = $this->storage->resolveManagedPath('original', (string) $file->storage_key);

        if (!is_file($path) || is_link($path) || !is_readable($path))
        {
            $this->setFileAvailability((int) $file->id, false, Text::_('COM_AUDIOARCHIVE_VERIFY_FILE_MISSING'));
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_ORIGINAL_NOT_READABLE'));
        }

        $clip = $this->getClipSummary($clipId);
        $metadata = $this->inspector->inspect($path, (string) $clip->original_filename);

        if (!(bool) ($metadata['valid'] ?? false))
        {
            $errors = array_values(array_filter((array) ($metadata['errors'] ?? [])));
            $message = $errors !== [] ? implode(' ', $errors) : Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_INVALID_MEDIA_UNKNOWN');
            $this->setFileAvailability((int) $file->id, false, $message);
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_REANALYSE_INVALID', $message));
        }

        $checksum = hash_file('sha256', $path);

        if (!is_string($checksum) || strlen($checksum) !== 64)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_CHECKSUM'));
        }

        $fileSize = (int) filesize($path);
        $duration = (int) ($metadata['duration_ms'] ?? 0);
        $mimeType = (string) ($metadata['mime_type'] ?? '');
        $container = (string) ($metadata['container_format'] ?? '');
        $codec = (string) ($metadata['audio_codec'] ?? '');
        $technicalMetadata = $this->technicalMetadataJson($metadata);
        $available = 1;
        $processingError = '';
        $fileId = (int) $file->id;
        $metadataStatus = 'available';
        $contentChanged = !hash_equals((string) $file->checksum_sha256, $checksum);
        $previewStatus = $this->hasFileRole($clipId, 'preview')
            ? 'stale'
            : ($this->requiresCompatibilityPreview($metadata) ? 'unavailable' : 'not_required');
        $waveformStatus = $this->hasWaveform($clipId) ? 'stale' : 'missing';

        $this->database->transactionStart();

        try
        {
            $fileQuery = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_files'))
                ->set($this->database->quoteName('mime_type') . ' = :mimeType')
                ->set($this->database->quoteName('container_format') . ' = :container')
                ->set($this->database->quoteName('audio_codec') . ' = :codec')
                ->set($this->database->quoteName('file_size') . ' = :fileSize')
                ->set($this->database->quoteName('duration_ms') . ' = :duration')
                ->set($this->database->quoteName('checksum_sha256') . ' = :checksum')
                ->set($this->database->quoteName('is_available') . ' = :available')
                ->set($this->database->quoteName('processing_error') . ' = :processingError')
                ->where($this->database->quoteName('id') . ' = :fileId')
                ->bind(':mimeType', $mimeType, ParameterType::STRING)
                ->bind(':container', $container, ParameterType::STRING)
                ->bind(':codec', $codec, ParameterType::STRING)
                ->bind(':fileSize', $fileSize, ParameterType::INTEGER)
                ->bind(':duration', $duration, ParameterType::INTEGER)
                ->bind(':checksum', $checksum, ParameterType::STRING)
                ->bind(':available', $available, ParameterType::INTEGER)
                ->bind(':processingError', $processingError, ParameterType::STRING)
                ->bind(':fileId', $fileId, ParameterType::INTEGER);
            $this->database->setQuery($fileQuery)->execute();

            $clipQuery = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_clips'))
                ->set($this->database->quoteName('duration_ms') . ' = :duration')
                ->set($this->database->quoteName('metadata_status') . ' = :metadataStatus')
                ->set($this->database->quoteName('technical_metadata') . ' = :technicalMetadata')
                ->where($this->database->quoteName('id') . ' = :clipId')
                ->bind(':duration', $duration, ParameterType::INTEGER)
                ->bind(':metadataStatus', $metadataStatus, ParameterType::STRING)
                ->bind(':technicalMetadata', $technicalMetadata, ParameterType::STRING)
                ->bind(':clipId', $clipId, ParameterType::INTEGER);

            if ($contentChanged)
            {
                $clipQuery->set($this->database->quoteName('preview_status') . ' = :previewStatus')
                    ->set($this->database->quoteName('waveform_status') . ' = :waveformStatus')
                    ->bind(':previewStatus', $previewStatus, ParameterType::STRING)
                    ->bind(':waveformStatus', $waveformStatus, ParameterType::STRING);
            }

            $this->database->setQuery($clipQuery)->execute();

            if ($contentChanged)
            {
                $this->synchroniseWaveformAnalysisStatus($clipId, $waveformStatus);
            }

            $this->database->transactionCommit();
        }
        catch (\Throwable $exception)
        {
            $this->database->transactionRollback();
            throw $exception;
        }

        if ($contentChanged)
        {
            $this->queueWaveformAfterUpload($clipId);
        }

        return array_values(array_unique(array_filter((array) ($metadata['warnings'] ?? []))));
    }

    /**
     * @brief Recalculate one stored original's SHA-256 checksum and recorded size.
     *
     * This operation does not reanalyse media metadata. When a previously valid
     * checksum or file size changes, metadata and existing derivatives are marked
     * stale so that a full reanalysis can be performed deliberately.
     *
     * @param int $clipId Clip identifier.
     *
     * @return array{content_changed:bool,checksum:string,file_size:int}
     */
    public function recalculateChecksumForClip(int $clipId): array
    {
        $file = $this->requireOriginalFile($clipId);
        $path = $this->storage->resolveManagedPath('original', (string) $file->storage_key);

        if (!is_file($path) || is_link($path) || !is_readable($path))
        {
            $this->setFileAvailability((int) $file->id, false, Text::_('COM_AUDIOARCHIVE_VERIFY_FILE_MISSING'));
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_ORIGINAL_NOT_READABLE'));
        }

        $checksum = hash_file('sha256', $path);

        if (!is_string($checksum) || strlen($checksum) !== 64)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_CHECKSUM'));
        }

        $fileSize = max(0, (int) filesize($path));
        $oldChecksum = strtolower(trim((string) $file->checksum_sha256));
        $oldChecksumValid = preg_match('/^[0-9a-f]{64}$/', $oldChecksum) === 1;
        $contentChanged = ($oldChecksumValid && !hash_equals($oldChecksum, $checksum))
            || (int) $file->file_size !== $fileSize;
        $available = 1;
        $processingError = '';
        $fileId = (int) $file->id;

        $this->database->transactionStart();

        try
        {
            $fileQuery = $this->database->getQuery(true)
                ->update($this->database->quoteName('#__audioarchive_files'))
                ->set($this->database->quoteName('checksum_sha256') . ' = :checksum')
                ->set($this->database->quoteName('file_size') . ' = :fileSize')
                ->set($this->database->quoteName('is_available') . ' = :available')
                ->set($this->database->quoteName('processing_error') . ' = :processingError')
                ->where($this->database->quoteName('id') . ' = :fileId')
                ->bind(':checksum', $checksum, ParameterType::STRING)
                ->bind(':fileSize', $fileSize, ParameterType::INTEGER)
                ->bind(':available', $available, ParameterType::INTEGER)
                ->bind(':processingError', $processingError, ParameterType::STRING)
                ->bind(':fileId', $fileId, ParameterType::INTEGER);
            $this->database->setQuery($fileQuery)->execute();

            if ($contentChanged)
            {
                $metadataStatus = 'missing';
                $clipQuery = $this->database->getQuery(true)
                    ->update($this->database->quoteName('#__audioarchive_clips'))
                    ->set($this->database->quoteName('metadata_status') . ' = :metadataStatus')
                    ->where($this->database->quoteName('id') . ' = :clipId')
                    ->bind(':metadataStatus', $metadataStatus, ParameterType::STRING)
                    ->bind(':clipId', $clipId, ParameterType::INTEGER);

                if ($this->hasFileRole($clipId, 'preview'))
                {
                    $previewStatus = 'stale';
                    $clipQuery->set($this->database->quoteName('preview_status') . ' = :previewStatus')
                        ->bind(':previewStatus', $previewStatus, ParameterType::STRING);
                }

                if ($this->hasWaveform($clipId))
                {
                    $waveformStatus = 'stale';
                    $clipQuery->set($this->database->quoteName('waveform_status') . ' = :waveformStatus')
                        ->bind(':waveformStatus', $waveformStatus, ParameterType::STRING);
                }

                $this->database->setQuery($clipQuery)->execute();

                if ($this->hasWaveform($clipId))
                {
                    $this->synchroniseWaveformAnalysisStatus($clipId, 'stale');
                }
            }

            $this->database->transactionCommit();
        }
        catch (\Throwable $exception)
        {
            $this->database->transactionRollback();
            throw $exception;
        }

        return [
            'content_changed' => $contentChanged,
            'checksum' => $checksum,
            'file_size' => $fileSize,
        ];
    }

    /**
     * @brief Verify existence, readability, size, and checksum of a stored original.
     *
     * @param int $clipId Clip identifier.
     *
     * @return array{ok:bool,message:string}
     */
    public function verifyForClip(int $clipId): array
    {
        $file = $this->requireOriginalFile($clipId);
        $path = $this->storage->resolveManagedPath('original', (string) $file->storage_key);
        $problems = [];

        if (!file_exists($path))
        {
            $problems[] = Text::_('COM_AUDIOARCHIVE_VERIFY_FILE_MISSING');
        }
        elseif (is_link($path) || !is_file($path))
        {
            $problems[] = Text::_('COM_AUDIOARCHIVE_VERIFY_FILE_INVALID_TYPE');
        }
        elseif (!is_readable($path))
        {
            $problems[] = Text::_('COM_AUDIOARCHIVE_VERIFY_FILE_NOT_READABLE');
        }
        else
        {
            $actualSize = (int) filesize($path);

            if ($actualSize !== (int) $file->file_size)
            {
                $problems[] = Text::sprintf('COM_AUDIOARCHIVE_VERIFY_SIZE_MISMATCH', (int) $file->file_size, $actualSize);
            }

            $actualChecksum = hash_file('sha256', $path);

            if (!is_string($actualChecksum) || !hash_equals((string) $file->checksum_sha256, $actualChecksum))
            {
                $problems[] = Text::_('COM_AUDIOARCHIVE_VERIFY_CHECKSUM_MISMATCH');
            }
        }

        $ok = $problems === [];
        $message = $ok ? Text::_('COM_AUDIOARCHIVE_VERIFY_SUCCESS') : implode(' ', $problems);
        $this->setFileAvailability((int) $file->id, $ok, $ok ? '' : $message);

        return ['ok' => $ok, 'message' => $message];
    }

    /**
     * @brief Return the original file record attached to a clip.
     *
     * @param int $clipId Clip identifier.
     *
     * @return object|null File row.
     */
    public function getOriginalFile(int $clipId): ?object
    {
        if ($clipId <= 0)
        {
            return null;
        }

        $role = 'original';
        $query = $this->database->getQuery(true)
            ->select('*')
            ->from($this->database->quoteName('#__audioarchive_files'))
            ->where($this->database->quoteName('clip_id') . ' = :clipId')
            ->where($this->database->quoteName('file_role') . ' = :role')
            ->bind(':clipId', $clipId, ParameterType::INTEGER)
            ->bind(':role', $role, ParameterType::STRING);

        $result = $this->database->setQuery($query, 0, 1)->loadObject();

        return is_object($result) ? $result : null;
    }

    /**
     * @brief Remove every managed file record belonging to permanently deleted clips.
     *
     * @param int[] $clipIds Clip identifiers.
     *
     * @return string[] Non-fatal cleanup warnings.
     */
    public function removeFilesForClips(array $clipIds): array
    {
        $clipIds = array_values(array_unique(array_filter(array_map('intval', $clipIds), static fn (int $id): bool => $id > 0)));

        if ($clipIds === [])
        {
            return [];
        }

        $query = $this->database->getQuery(true)
            ->select(['id', 'clip_id', 'file_role', 'storage_key'])
            ->from($this->database->quoteName('#__audioarchive_files'))
            ->whereIn($this->database->quoteName('clip_id'), $clipIds);
        $files = $this->database->setQuery($query)->loadObjectList() ?: [];
        $warnings = [];

        foreach ($files as $file)
        {
            $role = (string) $file->file_role;

            if (!in_array($role, ['original', 'preview'], true))
            {
                continue;
            }

            try
            {
                if (!$this->storage->deleteManagedFile($role, (string) $file->storage_key))
                {
                    $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $file->storage_key);
                }
            }
            catch (\Throwable $exception)
            {
                $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $file->storage_key);
            }
        }

        $analysisQuery = $this->database->getQuery(true)
            ->select(['id', 'storage_key'])
            ->from($this->database->quoteName('#__audioarchive_analyses'))
            ->whereIn($this->database->quoteName('clip_id'), $clipIds)
            ->where($this->database->quoteName('storage_key') . ' <> ' . $this->database->quote(''));
        $analyses = $this->database->setQuery($analysisQuery)->loadObjectList() ?: [];

        foreach ($analyses as $analysis)
        {
            try
            {
                if (!$this->storage->deleteManagedFile('analysis', (string) $analysis->storage_key))
                {
                    $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $analysis->storage_key);
                }
            }
            catch (\Throwable $exception)
            {
                $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $analysis->storage_key);
            }
        }

        foreach (['#__audioarchive_files', '#__audioarchive_waveforms', '#__audioarchive_analyses', '#__audioarchive_jobs'] as $table)
        {
            $delete = $this->database->getQuery(true)
                ->delete($this->database->quoteName($table))
                ->whereIn($this->database->quoteName('clip_id'), $clipIds);
            $this->database->setQuery($delete)->execute();
        }

        return $warnings;
    }

    /**
     * @brief Synchronise the generic waveform record with a denormalised clip status.
     *
     * @param int $clipId Clip identifier.
     * @param string $status Waveform status.
     *
     * @return void
     */
    private function synchroniseWaveformAnalysisStatus(int $clipId, string $status): void
    {
        if ($status !== 'stale')
        {
            return;
        }

        (new AnalysisRepositoryService($this->database))->markStale($clipId, 'waveform');
    }

    /**
     * @brief Queue waveform generation after a successful upload or replacement.
     *
     * Queue failures are non-fatal because the original file and clip record are
     * already valid and administrators can queue the waveform from maintenance.
     *
     * @param int $clipId Clip identifier.
     *
     * @return void
     */
    private function queueWaveformAfterUpload(int $clipId): void
    {
        if (
            (int) $this->params->get('enable_waveform_generation', 1) !== 1
            || (int) $this->params->get('queue_waveform_after_upload', 1) !== 1
        )
        {
            return;
        }

        try
        {
            (new AnalysisJobService($this->database, $this->params, $this->user))
                ->queueWaveform($clipId);
        }
        catch (\Throwable)
        {
            // Deferred generation can be queued manually from maintenance.
        }
    }

    /**
     * @brief Generate a useful title from embedded metadata and filename.
     *
     * @param array<string, mixed> $prepared Prepared upload data.
     *
     * @return string Generated title.
     */
    public function generateTitle(array $prepared): string
    {
        $policy = (string) $this->params->get('title_policy', 'embedded_filename');
        $embedded = trim((string) (($prepared['metadata']['embedded_title'] ?? '')));

        if ($policy === 'embedded_filename' && $embedded !== '')
        {
            return $embedded;
        }

        $filename = (string) pathinfo((string) $prepared['original_filename'], PATHINFO_FILENAME);
        $filename = preg_replace('/_+/', ' ', $filename) ?? $filename;
        $filename = preg_replace('/\s+/', ' ', $filename) ?? $filename;

        return trim($filename) !== '' ? trim($filename) : Text::_('COM_AUDIOARCHIVE_DEFAULT_CLIP_TITLE');
    }

    /**
     * @brief Choose a recording date according to component policy.
     *
     * @param array<string, mixed> $prepared Prepared upload data.
     *
     * @return array{date:string,source:string} Date and source.
     */
    public function determineRecordingDate(array $prepared): array
    {
        $policy = (string) $this->params->get('recorded_date_policy', 'embedded_filesystem_import');
        $embedded = (string) ($prepared['metadata']['recorded_at'] ?? '');

        if ($policy === 'embedded_filesystem_import' && $embedded !== '')
        {
            return ['date' => $embedded, 'source' => 'embedded'];
        }

        if (in_array($policy, ['embedded_filesystem_import', 'filesystem_import'], true))
        {
            $timestamp = @filemtime((string) $prepared['temporary_path']);

            if (is_int($timestamp) && $timestamp > 0)
            {
                return ['date' => gmdate('Y-m-d H:i:s', $timestamp), 'source' => 'filesystem'];
            }
        }

        return ['date' => Factory::getDate()->toSql(), 'source' => 'import'];
    }

    /**
     * @brief Require an original-file record for a clip.
     *
     * @param int $clipId Clip identifier.
     *
     * @return object File row.
     */
    private function requireOriginalFile(int $clipId): object
    {
        $file = $this->getOriginalFile($clipId);

        if ($file === null)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_NO_ORIGINAL_FILE'));
        }

        return $file;
    }

    /**
     * @brief Load the minimal clip fields needed by file operations.
     *
     * @param int $clipId Clip identifier.
     *
     * @return object Clip summary.
     */
    private function getClipSummary(int $clipId): object
    {
        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('id'),
                $this->database->quoteName('uuid'),
                $this->database->quoteName('original_filename'),
            ])
            ->from($this->database->quoteName('#__audioarchive_clips'))
            ->where($this->database->quoteName('id') . ' = :clipId')
            ->bind(':clipId', $clipId, ParameterType::INTEGER);
        $clip = $this->database->setQuery($query, 0, 1)->loadObject();

        if (!is_object($clip))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_SAVED_CLIP_NOT_FOUND'));
        }

        return $clip;
    }

    /**
     * @brief Persist the latest stored-file availability result.
     *
     * @param int $fileId File-record identifier.
     * @param bool $available Availability state.
     * @param string $error Processing diagnostic.
     *
     * @return void
     */
    private function setFileAvailability(int $fileId, bool $available, string $error): void
    {
        $availability = $available ? 1 : 0;
        $query = $this->database->getQuery(true)
            ->update($this->database->quoteName('#__audioarchive_files'))
            ->set($this->database->quoteName('is_available') . ' = :available')
            ->set($this->database->quoteName('processing_error') . ' = :processingError')
            ->where($this->database->quoteName('id') . ' = :fileId')
            ->bind(':available', $availability, ParameterType::INTEGER)
            ->bind(':processingError', $error, ParameterType::STRING)
            ->bind(':fileId', $fileId, ParameterType::INTEGER);
        $this->database->setQuery($query)->execute();
    }

    /**
     * @brief Check whether a clip has a file record with a given role.
     *
     * @param int $clipId Clip identifier.
     * @param string $role File role.
     *
     * @return bool True when a matching record exists.
     */
    private function hasFileRole(int $clipId, string $role): bool
    {
        $query = $this->database->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->database->quoteName('#__audioarchive_files'))
            ->where($this->database->quoteName('clip_id') . ' = :clipId')
            ->where($this->database->quoteName('file_role') . ' = :role')
            ->bind(':clipId', $clipId, ParameterType::INTEGER)
            ->bind(':role', $role, ParameterType::STRING);

        return (int) $this->database->setQuery($query)->loadResult() > 0;
    }

    /**
     * @brief Check whether a clip already has generated waveform data.
     *
     * @param int $clipId Clip identifier.
     *
     * @return bool True when a waveform row exists.
     */
    private function hasWaveform(int $clipId): bool
    {
        $analysisType = 'waveform';
        $query = $this->database->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->database->quoteName('#__audioarchive_analyses'))
            ->where($this->database->quoteName('clip_id') . ' = :clipId')
            ->where($this->database->quoteName('analysis_type') . ' = :analysisType')
            ->bind(':clipId', $clipId, ParameterType::INTEGER)
            ->bind(':analysisType', $analysisType, ParameterType::STRING);

        return (int) $this->database->setQuery($query)->loadResult() > 0;
    }

    /**
     * @brief Build a detailed duplicate description with an administrator edit link.
     *
     * @param object $duplicate Existing duplicate summary.
     *
     * @return string HTML-safe duplicate details.
     */
    private function formatDuplicateDetails(object $duplicate): string
    {
        $clipId = (int) $duplicate->clip_id;
        $title = htmlspecialchars((string) $duplicate->title, ENT_QUOTES, 'UTF-8');
        $filenameText = trim((string) $duplicate->original_filename);
        $categoryText = trim((string) ($duplicate->category_title ?? ''));
        $uploadedText = trim((string) $duplicate->uploaded_at);
        $filename = htmlspecialchars($filenameText !== '' ? $filenameText : Text::_('JNONE'), ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars($categoryText !== '' ? $categoryText : Text::_('JNONE'), ENT_QUOTES, 'UTF-8');
        $uploaded = htmlspecialchars($uploadedText !== '' ? $uploadedText : Text::_('JNONE'), ENT_QUOTES, 'UTF-8');
        $state = htmlspecialchars($this->stateLabel((int) $duplicate->state), ENT_QUOTES, 'UTF-8');
        $url = 'index.php?option=com_audioarchive&task=clip.edit&id=' . $clipId;
        $link = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Text::_('COM_AUDIOARCHIVE_DUPLICATE_EDIT_LINK'), ENT_QUOTES, 'UTF-8')
            . '</a>';

        return Text::sprintf(
            'COM_AUDIOARCHIVE_DUPLICATE_DETAILS',
            $title,
            $clipId,
            $filename,
            $category,
            $uploaded,
            $state,
            $link
        );
    }

    /**
     * @brief Translate one Joomla publication state.
     *
     * @param int $state Publication state.
     *
     * @return string State label.
     */
    private function stateLabel(int $state): string
    {
        return match ($state)
        {
            1 => Text::_('JPUBLISHED'),
            2 => Text::_('JARCHIVED'),
            -2 => Text::_('JTRASHED'),
            default => Text::_('JUNPUBLISHED'),
        };
    }

    /**
     * @brief Find an exact duplicate by SHA-256 checksum.
     *
     * @param string $checksum SHA-256 checksum.
     * @param int $excludeClipId Clip excluded during original replacement.
     *
     * @return object|null Existing file and clip summary.
     */
    private function findDuplicate(string $checksum, int $excludeClipId = 0): ?object
    {
        $role = 'original';
        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('f.id', 'file_id'),
                $this->database->quoteName('f.clip_id'),
                $this->database->quoteName('c.title'),
                $this->database->quoteName('c.original_filename'),
                $this->database->quoteName('c.state'),
                $this->database->quoteName('c.uploaded_at'),
                $this->database->quoteName('cat.title', 'category_title'),
            ])
            ->from($this->database->quoteName('#__audioarchive_files', 'f'))
            ->join('INNER', $this->database->quoteName('#__audioarchive_clips', 'c'), $this->database->quoteName('c.id') . ' = ' . $this->database->quoteName('f.clip_id'))
            ->join('LEFT', $this->database->quoteName('#__categories', 'cat'), $this->database->quoteName('cat.id') . ' = ' . $this->database->quoteName('c.catid'))
            ->where($this->database->quoteName('f.file_role') . ' = :role')
            ->where($this->database->quoteName('f.checksum_sha256') . ' = :checksum')
            ->bind(':role', $role, ParameterType::STRING)
            ->bind(':checksum', $checksum, ParameterType::STRING);

        if ($excludeClipId > 0)
        {
            $query->where($this->database->quoteName('f.clip_id') . ' <> :excludeClipId')
                ->bind(':excludeClipId', $excludeClipId, ParameterType::INTEGER);
        }
        $result = $this->database->setQuery($query, 0, 1)->loadObject();

        return is_object($result) ? $result : null;
    }

    /**
     * @brief Return configured accepted extensions.
     *
     * @return string[] Lowercase extensions.
     */
    private function getAllowedExtensions(): array
    {
        $extensions = preg_split('/[\s,;]+/', strtolower((string) $this->params->get('permitted_extensions', '')));

        return array_values(array_unique(array_filter(array_map('trim', is_array($extensions) ? $extensions : []))));
    }

    /**
     * @brief Return explicitly configured accepted MIME types.
     *
     * @return string[] Lowercase MIME types.
     */
    private function getConfiguredMimeTypes(): array
    {
        $mimes = preg_split('/[\s,;]+/', strtolower((string) $this->params->get('permitted_mime_types', '')));

        return array_values(array_unique(array_filter(array_map('trim', is_array($mimes) ? $mimes : []))));
    }

    /**
     * @brief Create the persisted technical-metadata JSON document.
     *
     * @param array<string, mixed> $metadata Inspector result.
     *
     * @return string JSON document.
     */
    private function technicalMetadataJson(array $metadata): string
    {
        unset($metadata['path'], $metadata['valid'], $metadata['errors']);
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_METADATA_ENCODING'));
        }

        return $json;
    }

    /**
     * @brief Determine whether the original requires a compatibility preview.
     *
     * @param array<string, mixed> $metadata Inspector result.
     *
     * @return bool True when direct browser compatibility is uncertain.
     */
    private function requiresCompatibilityPreview(array $metadata): bool
    {
        $codec = strtoupper((string) ($metadata['audio_codec'] ?? ''));
        $container = strtoupper((string) ($metadata['container_format'] ?? ''));

        if (str_contains($codec, 'ALAC') || str_contains($codec, 'AC-3'))
        {
            return true;
        }

        if ($container === 'ADTS' || $codec === 'FLAC')
        {
            return true;
        }

        return false;
    }

    /**
     * @brief Normalise an untrusted original filename.
     *
     * @param string $filename Client filename.
     *
     * @return string Basename safe for metadata and download headers.
     */
    private function normaliseOriginalFilename(string $filename): string
    {
        $filename = str_replace('\\', '/', str_replace("\0", '', trim($filename)));
        $filename = basename($filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';

        return mb_substr(trim($filename), 0, 255);
    }

    /**
     * @brief Translate one PHP upload error code.
     *
     * @param int $error Upload error code.
     *
     * @return string Error message.
     */
    private function uploadErrorMessage(int $error): string
    {
        return match ($error)
        {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_SERVER_SIZE'),
            UPLOAD_ERR_PARTIAL => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_PARTIAL'),
            UPLOAD_ERR_NO_FILE => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_REQUIRED'),
            UPLOAD_ERR_NO_TMP_DIR => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_NO_TEMP'),
            UPLOAD_ERR_CANT_WRITE => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_WRITE'),
            UPLOAD_ERR_EXTENSION => Text::_('COM_AUDIOARCHIVE_ERROR_UPLOAD_EXTENSION_BLOCKED'),
            default => Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_UNKNOWN', $error),
        };
    }
}
