<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Willeke\Component\Audioarchive\Administrator\Model\MaintenanceModel;
use Willeke\Component\Audioarchive\Administrator\Service\ArchiveExportService;
use Willeke\Component\Audioarchive\Administrator\Service\ArchiveImportService;
use Willeke\Component\Audioarchive\Administrator\Service\AudioUploadService;

\defined('_JEXEC') or die;

/**
 * @brief Controller for selected-item maintenance actions and report export.
 */
class MaintenanceController extends BaseController
{
    /** @var int */
    private const MAX_BATCH_SIZE = 50;

    /**
     * @brief Verify selected originals against recorded size and SHA-256.
     *
     * @return void
     */
    public function verify(): void
    {
        $this->runClipAction(
            static fn (AudioUploadService $service, int $clipId): array => $service->verifyForClip($clipId),
            'COM_AUDIOARCHIVE_MAINTENANCE_VERIFY_COMPLETE'
        );
    }

    /**
     * @brief Reinspect selected originals and refresh all technical metadata.
     *
     * @return void
     */
    public function reanalyse(): void
    {
        $this->runClipAction(
            static function (AudioUploadService $service, int $clipId): array
            {
                $warnings = $service->reanalyseForClip($clipId);

                return [
                    'ok' => true,
                    'message' => $warnings === []
                        ? Text::_('COM_AUDIOARCHIVE_REANALYSE_SUCCESS')
                        : implode(' ', $warnings),
                ];
            },
            'COM_AUDIOARCHIVE_MAINTENANCE_REANALYSE_COMPLETE'
        );
    }

    /**
     * @brief Recalculate selected originals' checksum and recorded size.
     *
     * @return void
     */
    public function recalculateChecksums(): void
    {
        $this->runClipAction(
            static function (AudioUploadService $service, int $clipId): array
            {
                $result = $service->recalculateChecksumForClip($clipId);

                return [
                    'ok' => true,
                    'message' => (bool) $result['content_changed']
                        ? Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CHECKSUM_CHANGED')
                        : Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CHECKSUM_REFRESHED'),
                ];
            },
            'COM_AUDIOARCHIVE_MAINTENANCE_CHECKSUM_COMPLETE'
        );
    }

    /**
     * @brief Delete selected stale derivatives and unreferenced managed files.
     *
     * @return void
     */
    public function deleteStale(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();

        if (!$application->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $tokens = $application->getInput()->post->get('stale', [], 'array');
        $tokens = array_values(array_unique(array_filter(array_map('strval', is_array($tokens) ? $tokens : []))));

        if ($tokens === [])
        {
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_NO_SELECTION'), 'warning');
            $this->setRedirect($this->maintenanceUrl('stale'));
            return;
        }

        if (count($tokens) > 200)
        {
            $application->enqueueMessage(Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_STALE_BATCH_LIMIT', 200), 'warning');
            $this->setRedirect($this->maintenanceUrl('stale'));
            return;
        }

        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        $result = $model->deleteStaleItems($tokens);

        foreach ((array) ($result['messages'] ?? []) as $message)
        {
            $application->enqueueMessage((string) $message, 'warning');
        }

        $failed = (int) ($result['failed'] ?? 0);
        $application->enqueueMessage(
            Text::sprintf(
                'COM_AUDIOARCHIVE_MAINTENANCE_STALE_DELETE_COMPLETE',
                (int) ($result['succeeded'] ?? 0),
                $failed
            ),
            $failed > 0 ? 'warning' : 'success'
        );
        $this->setRedirect($this->maintenanceUrl('stale'));
    }

    /**
     * @brief Delete one AJAX batch of selected stale files.
     *
     * @return void
     */
    public function deleteStaleBatch(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();

        if (!$application->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $tokens = $application->getInput()->post->get('stale', [], 'array');
        $tokens = array_values(array_unique(array_filter(array_map('strval', is_array($tokens) ? $tokens : []))));

        if ($tokens === [] || count($tokens) > 200)
        {
            $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse(null, Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_STALE_BATCH_LIMIT', 200), true);
            $application->close();
        }

        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        $result = $model->deleteStaleItems($tokens);
        $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo new JsonResponse($result);
        $application->close();
    }

    /**
     * @brief Queue waveform jobs for one maintenance status group.
     *
     * @return void
     */
    public function queueWaveforms(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();
        $mode = $application->getInput()->post->getCmd('waveform_mode', '');
        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        try
        {
            $queued = $model->queueWaveforms($mode);
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_WAVEFORM_QUEUE_COMPLETE', $queued),
                $queued > 0 ? 'success' : 'info'
            );
        }
        catch (\Throwable $exception)
        {
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_WAVEFORM_QUEUE_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Queue spectrogram jobs for one maintenance status group.
     *
     * @return void
     */
    public function queueSpectrograms(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();
        $mode = $application->getInput()->post->getCmd('spectrogram_mode', '');
        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        try
        {
            $queued = $model->queueSpectrograms($mode);
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_SPECTROGRAM_QUEUE_COMPLETE', $queued),
                $queued > 0 ? 'success' : 'info'
            );
        }
        catch (\Throwable $exception)
        {
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_SPECTROGRAM_QUEUE_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->maintenanceUrl());
    }


    /**
     * @brief Queue and begin regeneration of spectral analyses for all eligible clips.
     *
     * @return void
     */
    public function regenerateSpectrograms(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();
        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        try
        {
            $queued = $model->queueSpectrograms('all');
            $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse(['queued' => $queued]);
        }
        catch (\Throwable $exception)
        {
            $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse(null, $exception->getMessage(), true);
        }

        $application->close();
    }

    /**
     * @brief Delete every generated waveform data file and reset waveform states.
     *
     * @return void
     */
    public function deleteWaveforms(): void
    {
        $this->deleteAnalysisData('waveform');
    }

    /**
     * @brief Delete every generated spectral-analysis file and reset its states.
     *
     * @return void
     */
    public function deleteSpectrograms(): void
    {
        $this->deleteAnalysisData('spectrogram');
    }

    /**
     * @brief Process one pending analysis job for the maintenance progress UI.
     *
     * @return void
     */
    public function processAnalysisJob(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();
        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        try
        {
            $result = $model->processNextAnalysisJob();
            $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse($result);
        }
        catch (\Throwable $exception)
        {
            $application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse(null, $exception->getMessage(), true);
        }

        $application->close();
    }

    /**
     * @brief Export the current integrity report as UTF-8 CSV.
     *
     * @return void
     */
    public function export(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        $report = $model->getIntegrityReport();
        $application = Factory::getApplication();
        $filename = 'audioarchive-integrity-' . gmdate('Y-m-d-His') . '.csv';
        $application->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $application->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $application->sendHeaders();
        $stream = fopen('php://output', 'wb');

        if ($stream === false)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_EXPORT'), 500);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            [
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_SEVERITY'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_ISSUE'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP_ID'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_FILENAME'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_STORAGE_KEY'),
                Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_DETAILS'),
            ],
            ',',
            '"',
            ''
        );

        foreach ((array) ($report['issues'] ?? []) as $issue)
        {
            fputcsv(
                $stream,
                [
                    Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SEVERITY_' . strtoupper((string) $issue['severity'])),
                    Text::_((string) $issue['label']),
                    (int) $issue['clip_id'] > 0 ? (string) $issue['clip_id'] : '',
                    (string) $issue['clip_title'],
                    (string) $issue['original_filename'],
                    (string) $issue['storage_key'],
                    (string) $issue['detail'],
                ],
                ',',
                '"',
                ''
            );
        }

        fclose($stream);
        $application->close();
    }

    /**
     * @brief Create and download a portable Audio Archive ZIP export.
     *
     * @return void
     */
    public function exportArchive(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertArchivePermission();
        $application = Factory::getApplication();
        $scope = $application->getInput()->post->getCmd('archive_scope', 'metadata');
        $service = new ArchiveExportService(
            Factory::getContainer()->get(DatabaseInterface::class),
            ComponentHelper::getParams('com_audioarchive'),
            $application->getIdentity()
        );
        $export = null;
        $stream = null;
        $size = 0;

        try
        {
            if (function_exists('set_time_limit'))
            {
                @set_time_limit(0);
            }

            $export = $service->create($scope, $this->getInstalledComponentVersion());
            $path = (string) $export['path'];
            $filename = (string) $export['filename'];
            clearstatcache(true, $path);
            $size = filesize($path);

            if (!is_int($size) || $size <= 0)
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_STREAM'));
            }

            $stream = fopen($path, 'rb');

            if (!is_resource($stream))
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_ERROR_STREAM'));
            }
        }
        catch (\Throwable $exception)
        {
            if (is_resource($stream))
            {
                fclose($stream);
            }

            if (is_array($export) && isset($export['path']))
            {
                @unlink((string) $export['path']);
            }

            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_EXPORT_FAILED', $exception->getMessage()),
                'error'
            );
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        $this->prepareArchiveDownload();
        $application->clearHeaders();
        $application->setHeader('Content-Type', 'application/zip', true);
        $application->setHeader('Content-Disposition', 'attachment; filename="' . addcslashes($filename, '"\\') . '"', true);
        $application->setHeader('Content-Length', (string) $size, true);
        $application->setHeader('Content-Encoding', 'identity', true);
        $application->setHeader('Content-Transfer-Encoding', 'binary', true);
        $application->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $application->setHeader('Pragma', 'no-cache', true);
        $application->setHeader('X-Content-Type-Options', 'nosniff', true);
        $application->setHeader('X-Accel-Buffering', 'no', true);

        try
        {
            $application->sendHeaders();
            $this->streamArchive($stream);
        }
        finally
        {
            fclose($stream);
            @unlink($path);
        }

        $application->close();
    }

    /**
     * @brief Remove text-response buffering and compression before a ZIP download.
     *
     * Joomla administrator output or an active PHP compression handler must not
     * prefix or transform the already-compressed archive bytes.
     *
     * @return void
     */
    private function prepareArchiveDownload(): void
    {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE)
        {
            @session_write_close();
        }

        if (function_exists('ini_set'))
        {
            @ini_set('zlib.output_compression', '0');
        }

        if (function_exists('apache_setenv'))
        {
            @apache_setenv('no-gzip', '1');
        }

        while (ob_get_level() > 0)
        {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level)
            {
                break;
            }
        }
    }

    /**
     * @brief Stream an already validated archive without appending error output.
     *
     * @param resource $stream Open binary archive stream.
     *
     * @return bool True when the complete stream was written.
     */
    private function streamArchive($stream): bool
    {
        while (!feof($stream))
        {
            $chunk = fread($stream, 1048576);

            if ($chunk === false)
            {
                return false;
            }

            if ($chunk === '')
            {
                return feof($stream);
            }

            echo $chunk;

            if (function_exists('flush'))
            {
                @flush();
            }
        }

        return true;
    }

    /**
     * @brief Stage and inspect a portable Audio Archive ZIP without restoring it.
     *
     * @return void
     */
    public function inspectArchive(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertArchivePermission();
        $application = Factory::getApplication();
        $service = $this->createArchiveImportService();
        $files = $application->getInput()->files->get('archive_file', null, 'array');
        $upload = is_array($files) ? $files : null;
        $inboxArchive = $application->getInput()->post->getString('inbox_archive', '');
        $session = $application->getSession();
        $previous = $session->get('com_audioarchive.restore_inspection', []);

        if (is_array($previous) && trim((string) ($previous['token'] ?? '')) !== '')
        {
            $service->deleteStaged((string) $previous['token']);
        }

        try
        {
            $token = $service->stage($upload, $inboxArchive);
            $inspection = $service->inspect($service->getStagedPath($token));
            $session->set('com_audioarchive.restore_inspection', [
                'token' => $token,
                'inspection' => $inspection,
            ]);
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_INSPECTION_COMPLETE'), 'success');
        }
        catch (\Throwable $exception)
        {
            $session->clear('com_audioarchive.restore_inspection');
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_INSPECTION_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Restore the currently inspected Audio Archive ZIP.
     *
     * @return void
     */
    public function restoreArchive(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertArchivePermission();
        $application = Factory::getApplication();
        $session = $application->getSession();
        $staged = $session->get('com_audioarchive.restore_inspection', []);
        $token = is_array($staged) ? trim((string) ($staged['token'] ?? '')) : '';

        if ($token === '')
        {
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_ERROR_NO_INSPECTION'), 'error');
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        if ($application->getInput()->post->getInt('confirm_restore', 0) !== 1)
        {
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_CONFIRM_REQUIRED'), 'warning');
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        $restoreMode = $application->getInput()->post->getCmd('restore_mode', 'empty');
        $conflictPolicy = $application->getInput()->post->getCmd('conflict_policy', 'skip');
        $restoreConfiguration = $application->getInput()->post->getInt('restore_configuration', 0) === 1;
        $service = $this->createArchiveImportService();

        try
        {
            if (function_exists('set_time_limit'))
            {
                @set_time_limit(0);
            }

            $result = $service->restore(
                $service->getStagedPath($token),
                $restoreMode,
                $conflictPolicy,
                $restoreConfiguration
            );
            $service->deleteStaged($token);
            $session->clear('com_audioarchive.restore_inspection');
            $application->enqueueMessage(
                Text::sprintf(
                    'COM_AUDIOARCHIVE_ARCHIVE_IMPORT_RESTORE_COMPLETE',
                    (int) ($result['created'] ?? 0),
                    (int) ($result['updated'] ?? 0),
                    (int) ($result['skipped'] ?? 0),
                    (int) ($result['files_restored'] ?? 0),
                    (int) ($result['analyses_restored'] ?? 0)
                ),
                'success'
            );
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_REINDEX_NOTICE'), 'info');
        }
        catch (\Throwable $exception)
        {
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_RESTORE_FAILED', $exception->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Discard the currently inspected staged archive.
     *
     * @return void
     */
    public function clearArchiveInspection(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertArchivePermission();
        $application = Factory::getApplication();
        $session = $application->getSession();
        $staged = $session->get('com_audioarchive.restore_inspection', []);
        $token = is_array($staged) ? trim((string) ($staged['token'] ?? '')) : '';

        if ($token !== '')
        {
            $this->createArchiveImportService()->deleteStaged($token);
        }

        $session->clear('com_audioarchive.restore_inspection');
        $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_ARCHIVE_IMPORT_INSPECTION_CLEARED'), 'info');
        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Execute one maintenance operation for selected clip identifiers.
     *
     * @param callable $operation Operation receiving service and clip ID.
     * @param string $completeMessage Language key for summary message.
     *
     * @return void
     */
    private function runClipAction(callable $operation, string $completeMessage): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();
        $clipIds = $this->getSelectedClipIds();

        if ($clipIds === [])
        {
            $application->enqueueMessage(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_NO_SELECTION'), 'warning');
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        if (count($clipIds) > self::MAX_BATCH_SIZE)
        {
            $application->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_BATCH_LIMIT', self::MAX_BATCH_SIZE),
                'warning'
            );
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        $service = new AudioUploadService(
            Factory::getContainer()->get(DatabaseInterface::class),
            ComponentHelper::getParams('com_audioarchive'),
            $application->getIdentity()
        );
        $succeeded = 0;
        $failed = 0;

        foreach ($clipIds as $clipId)
        {
            try
            {
                $result = $operation($service, $clipId);

                if ((bool) ($result['ok'] ?? false))
                {
                    $succeeded++;

                    if (trim((string) ($result['message'] ?? '')) !== '')
                    {
                        $application->enqueueMessage(
                            Text::sprintf(
                                'COM_AUDIOARCHIVE_MAINTENANCE_CLIP_RESULT',
                                $clipId,
                                (string) $result['message']
                            ),
                            'success'
                        );
                    }
                }
                else
                {
                    $failed++;
                    $application->enqueueMessage(
                        Text::sprintf(
                            'COM_AUDIOARCHIVE_MAINTENANCE_CLIP_RESULT',
                            $clipId,
                            (string) ($result['message'] ?? Text::_('JERROR_AN_ERROR_HAS_OCCURRED'))
                        ),
                        'warning'
                    );
                }
            }
            catch (\Throwable $exception)
            {
                $failed++;
                $application->enqueueMessage(
                    Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_CLIP_RESULT', $clipId, $exception->getMessage()),
                    'error'
                );
            }
        }

        $application->enqueueMessage(Text::sprintf($completeMessage, $succeeded, $failed), $failed > 0 ? 'warning' : 'success');
        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Return unique positive selected clip identifiers.
     *
     * @return int[] Clip identifiers.
     */
    private function getSelectedClipIds(): array
    {
        $values = Factory::getApplication()->getInput()->post->get('cid', [], 'array');
        $ids = array_map('intval', is_array($values) ? $values : []);
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * @brief Delete all generated data for one analysis type.
     *
     * @param string $analysisType Waveform or spectrogram.
     *
     * @return void
     */
    private function deleteAnalysisData(string $analysisType): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertProcessPermission();
        $application = Factory::getApplication();

        if (!$application->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel('Maintenance');

        if (!$model instanceof MaintenanceModel)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ERROR_MODEL'), 500);
        }

        try
        {
            $result = $model->deleteAllAnalysis($analysisType);
            $key = $analysisType === 'waveform'
                ? 'COM_AUDIOARCHIVE_WAVEFORM_DELETE_ALL_COMPLETE'
                : 'COM_AUDIOARCHIVE_SPECTROGRAM_DELETE_ALL_COMPLETE';
            $application->enqueueMessage(
                Text::sprintf(
                    $key,
                    (int) ($result['records'] ?? 0),
                    (int) ($result['deleted'] ?? 0),
                    (int) ($result['cancelled'] ?? 0),
                    (int) ($result['failed'] ?? 0)
                ),
                (int) ($result['failed'] ?? 0) > 0 ? 'warning' : 'success'
            );
        }
        catch (\Throwable $exception)
        {
            $key = $analysisType === 'waveform'
                ? 'COM_AUDIOARCHIVE_WAVEFORM_DELETE_ALL_FAILED'
                : 'COM_AUDIOARCHIVE_SPECTROGRAM_DELETE_ALL_FAILED';
            $application->enqueueMessage(Text::sprintf($key, $exception->getMessage()), 'error');
        }

        $this->setRedirect($this->maintenanceUrl());
    }

    /**
     * @brief Require both processing and managed-file permissions for archive backup operations.
     *
     * @return void
     */
    private function assertArchivePermission(): void
    {
        $this->assertProcessPermission();

        if (!Factory::getApplication()->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @brief Create the archive import service for the current request.
     *
     * @return ArchiveImportService Import service.
     */
    private function createArchiveImportService(): ArchiveImportService
    {
        return new ArchiveImportService(
            Factory::getContainer()->get(DatabaseInterface::class),
            ComponentHelper::getParams('com_audioarchive'),
            Factory::getApplication()->getIdentity()
        );
    }

    /**
     * @brief Read the installed component version from Joomla's extension manifest cache.
     *
     * @return string Installed component version.
     */
    private function getInstalledComponentVersion(): string
    {
        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $database->getQuery(true)
            ->select($database->quoteName('manifest_cache'))
            ->from($database->quoteName('#__extensions'))
            ->where($database->quoteName('type') . ' = ' . $database->quote('component'))
            ->where($database->quoteName('element') . ' = ' . $database->quote('com_audioarchive'));
        $manifest = json_decode((string) $database->setQuery($query, 0, 1)->loadResult(), true);

        return is_array($manifest) && trim((string) ($manifest['version'] ?? '')) !== ''
            ? trim((string) $manifest['version'])
            : '0.9.4';
    }

    /**
     * @brief Require technical-processing permission.
     *
     * @return void
     */
    private function assertProcessPermission(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('audioarchive.process', 'com_audioarchive'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @brief Build the maintenance-page redirect URL.
     *
     * @return string Routed administrator URL.
     */
    private function maintenanceUrl(string $check = ''): string
    {
        $url = 'index.php?option=com_audioarchive&view=maintenance';

        if (in_array($check, ['integrity', 'codecs', 'stale'], true))
        {
            $url .= '&check=' . $check;
        }

        return Route::_($url, false);
    }
}
