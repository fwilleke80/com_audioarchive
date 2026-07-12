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
     *
     * @return array<string, mixed> Prepared immutable upload data.
     */
    public function prepare(array $upload): array
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

        $duplicate = $this->findDuplicate($checksum);
        $duplicatePolicy = (string) $this->params->get('duplicate_policy', 'warn');

        if ($duplicate !== null && $duplicatePolicy === 'reject')
        {
            throw new \RuntimeException(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_ERROR_DUPLICATE_REJECTED',
                    (string) $duplicate->title,
                    (int) $duplicate->clip_id
                )
            );
        }

        if ($duplicate !== null && $duplicatePolicy === 'warn')
        {
            $warnings[] = Text::sprintf(
                'COM_AUDIOARCHIVE_WARNING_DUPLICATE_ALLOWED',
                (string) $duplicate->title,
                (int) $duplicate->clip_id
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
        ];
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
            (string) $prepared['extension']
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

        return $file;
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

        $waveformQuery = $this->database->getQuery(true)
            ->select(['id', 'storage_key'])
            ->from($this->database->quoteName('#__audioarchive_waveforms'))
            ->whereIn($this->database->quoteName('clip_id'), $clipIds);
        $waveforms = $this->database->setQuery($waveformQuery)->loadObjectList() ?: [];

        foreach ($waveforms as $waveform)
        {
            try
            {
                if (!$this->storage->deleteManagedFile('waveform', (string) $waveform->storage_key))
                {
                    $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $waveform->storage_key);
                }
            }
            catch (\Throwable $exception)
            {
                $warnings[] = Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_DELETE_FAILED', (string) $waveform->storage_key);
            }
        }

        foreach (['#__audioarchive_files', '#__audioarchive_waveforms', '#__audioarchive_jobs'] as $table)
        {
            $delete = $this->database->getQuery(true)
                ->delete($this->database->quoteName($table))
                ->whereIn($this->database->quoteName('clip_id'), $clipIds);
            $this->database->setQuery($delete)->execute();
        }

        return $warnings;
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
     * @brief Find an exact duplicate by SHA-256 checksum.
     *
     * @param string $checksum SHA-256 checksum.
     *
     * @return object|null Existing file and clip summary.
     */
    private function findDuplicate(string $checksum): ?object
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
            ])
            ->from($this->database->quoteName('#__audioarchive_files', 'f'))
            ->join('INNER', $this->database->quoteName('#__audioarchive_clips', 'c'), $this->database->quoteName('c.id') . ' = ' . $this->database->quoteName('f.clip_id'))
            ->where($this->database->quoteName('f.file_role') . ' = :role')
            ->where($this->database->quoteName('f.checksum_sha256') . ' = :checksum')
            ->bind(':role', $role, ParameterType::STRING)
            ->bind(':checksum', $checksum, ParameterType::STRING);
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
