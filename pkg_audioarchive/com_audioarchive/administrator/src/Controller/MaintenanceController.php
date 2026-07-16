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
            $this->setRedirect($this->maintenanceUrl());
            return;
        }

        if (count($tokens) > 200)
        {
            $application->enqueueMessage(Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_STALE_BATCH_LIMIT', 200), 'warning');
            $this->setRedirect($this->maintenanceUrl());
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
        $this->setRedirect($this->maintenanceUrl());
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
            $queued = $model->queueAllSpectrograms();
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

        $report = $model->getReport();
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
    private function maintenanceUrl(): string
    {
        return Route::_('index.php?option=com_audioarchive&view=maintenance', false);
    }
}
