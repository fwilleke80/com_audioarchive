<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.multiselect');

$summary = (array) ($this->report['summary'] ?? []);
$issues = (array) ($this->report['issues'] ?? []);
$actionableClips = (array) ($this->report['actionable_clips'] ?? []);
$codecInventory = (array) ($this->report['codec_inventory'] ?? []);
$codecFilter = (string) ($this->report['codec_filter'] ?? '');
$codecClips = (array) ($this->report['codec_clips'] ?? []);
$staleItems = (array) ($this->report['stale_items'] ?? []);
$waveforms = (array) ($this->report['waveforms'] ?? []);
$spectrograms = (array) ($this->report['spectrograms'] ?? []);
$analysisProcessUrl = Route::_('index.php?option=com_audioarchive&task=maintenance.processAnalysisJob&format=json', false);
$analysisToken = Session::getFormToken();
$formatBytes = static function (int $bytes): string
{
    if ($bytes <= 0)
    {
        return '0 B';
    }

    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $index = min((int) floor(log($bytes, 1024)), count($units) - 1);

    return number_format($bytes / (1024 ** $index), $index === 0 ? 0 : 1) . ' ' . $units[$index];
};
$severityClasses = [
    'error' => 'bg-danger',
    'warning' => 'bg-warning text-dark',
    'info' => 'bg-info text-dark',
];
$severityKeys = [
    'error' => 'COM_AUDIOARCHIVE_MAINTENANCE_SEVERITY_ERROR',
    'warning' => 'COM_AUDIOARCHIVE_MAINTENANCE_SEVERITY_WARNING',
    'info' => 'COM_AUDIOARCHIVE_MAINTENANCE_SEVERITY_INFO',
];
?>
<div class="com-audioarchive-maintenance">
    <div class="alert alert-info">
        <h2 class="h5 alert-heading"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_NON_DESTRUCTIVE_TITLE'); ?></h2>
        <p class="mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_NON_DESTRUCTIVE_TEXT'); ?></p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['clips'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_CLIPS'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['original_records'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_ORIGINAL_RECORDS'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['managed_original_files'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_MANAGED_FILES'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['errors'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_ERRORS'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['warnings'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_WARNINGS'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="display-6"><?php echo (int) ($summary['issues'] ?? 0); ?></div>
                    <div><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_SUMMARY_ISSUES'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <span><?php echo Text::sprintf('COM_AUDIOARCHIVE_MAINTENANCE_STALE_SUMMARY', (int) ($summary['stale_files'] ?? 0)); ?></span>
        <?php if ((int) ($summary['stale_files'] ?? 0) > 0) : ?>
            <a href="#audioarchive-stale-files" class="btn btn-sm btn-outline-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_SHOW'); ?></a>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-body-secondary mb-0">
            <?php echo Text::sprintf(
                'COM_AUDIOARCHIVE_MAINTENANCE_SCAN_TIME',
                htmlspecialchars((string) ($this->report['checked_at'] ?? ''), ENT_QUOTES, 'UTF-8')
            ); ?>
        </p>
        <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=maintenance'); ?>">
            <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_RESCAN'); ?>
        </a>
    </div>

    <div
        class="card mb-4"
        data-audioarchive-analysis-maintenance
        data-process-url="<?php echo htmlspecialchars($analysisProcessUrl, ENT_QUOTES, 'UTF-8'); ?>"
        data-token-name="<?php echo htmlspecialchars($analysisToken, ENT_QUOTES, 'UTF-8'); ?>"
        data-progress-template="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_ANALYSIS_PROCESS_PROGRESS'), ENT_QUOTES, 'UTF-8'); ?>"
        data-failure-text="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_ANALYSIS_PROCESS_AJAX_FAILED'), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <div class="card-header">
            <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_ANALYSIS_MAINTENANCE_TITLE'); ?></h2>
            <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_ANALYSIS_MAINTENANCE_TEXT'); ?></p>
        </div>
        <div class="card-body">
            <?php foreach ([
                [
                    'summary' => $waveforms,
                    'title' => 'COM_AUDIOARCHIVE_WAVEFORM_MAINTENANCE_TITLE',
                    'text' => 'COM_AUDIOARCHIVE_WAVEFORM_MAINTENANCE_TEXT',
                    'task' => 'maintenance.queueWaveforms',
                    'mode_name' => 'waveform_mode',
                    'queue_missing' => 'COM_AUDIOARCHIVE_WAVEFORM_QUEUE_MISSING',
                    'queue_stale' => 'COM_AUDIOARCHIVE_WAVEFORM_QUEUE_STALE',
                    'retry_failed' => 'COM_AUDIOARCHIVE_WAVEFORM_RETRY_FAILED',
                    'status_prefix' => 'COM_AUDIOARCHIVE_WAVEFORM_STATUS_',
                ],
                [
                    'summary' => $spectrograms,
                    'title' => 'COM_AUDIOARCHIVE_SPECTROGRAM_MAINTENANCE_TITLE',
                    'text' => 'COM_AUDIOARCHIVE_SPECTROGRAM_MAINTENANCE_TEXT',
                    'task' => 'maintenance.queueSpectrograms',
                    'mode_name' => 'spectrogram_mode',
                    'queue_missing' => 'COM_AUDIOARCHIVE_SPECTROGRAM_QUEUE_MISSING',
                    'queue_stale' => 'COM_AUDIOARCHIVE_SPECTROGRAM_QUEUE_STALE',
                    'retry_failed' => 'COM_AUDIOARCHIVE_SPECTROGRAM_RETRY_FAILED',
                    'status_prefix' => 'COM_AUDIOARCHIVE_SPECTROGRAM_STATUS_',
                ],
            ] as $analysisSection) : ?>
                <section class="<?php echo $analysisSection['task'] === 'maintenance.queueSpectrograms' ? 'border-top pt-4 mt-4' : ''; ?>">
                    <h3 class="h5 mb-1"><?php echo Text::_($analysisSection['title']); ?></h3>
                    <p class="text-body-secondary"><?php echo Text::_($analysisSection['text']); ?></p>

                    <div class="row g-3 mb-3">
                        <?php foreach (['available', 'missing', 'pending', 'failed', 'stale', 'queued'] as $key) : ?>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="border rounded p-3 h-100">
                                    <div class="h3 mb-1"><?php echo (int) ($analysisSection['summary'][$key] ?? 0); ?></div>
                                    <div class="small text-body-secondary"><?php echo Text::_($analysisSection['status_prefix'] . strtoupper($key)); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form action="<?php echo Route::_('index.php?option=com_audioarchive'); ?>" method="post" class="d-flex flex-wrap gap-2">
                        <input type="hidden" name="task" value="<?php echo htmlspecialchars($analysisSection['task'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="<?php echo htmlspecialchars($analysisSection['mode_name'], ENT_QUOTES, 'UTF-8'); ?>" value="missing" class="btn btn-outline-primary" <?php echo (int) ($analysisSection['summary']['missing'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                            <?php echo Text::_($analysisSection['queue_missing']); ?>
                        </button>
                        <button type="submit" name="<?php echo htmlspecialchars($analysisSection['mode_name'], ENT_QUOTES, 'UTF-8'); ?>" value="stale" class="btn btn-outline-primary" <?php echo (int) ($analysisSection['summary']['stale'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                            <?php echo Text::_($analysisSection['queue_stale']); ?>
                        </button>
                        <button type="submit" name="<?php echo htmlspecialchars($analysisSection['mode_name'], ENT_QUOTES, 'UTF-8'); ?>" value="failed" class="btn btn-outline-primary" <?php echo (int) ($analysisSection['summary']['failed'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                            <?php echo Text::_($analysisSection['retry_failed']); ?>
                        </button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </section>
            <?php endforeach; ?>

            <div class="d-flex flex-wrap align-items-center gap-3 border-top pt-4 mt-4">
                <button
                    type="button"
                    class="btn btn-primary"
                    data-audioarchive-process-analyses
                    <?php echo ((int) ($waveforms['queued'] ?? 0) + (int) ($spectrograms['queued'] ?? 0)) <= 0 ? 'disabled' : ''; ?>
                >
                    <span class="icon-play" aria-hidden="true"></span>
                    <?php echo Text::_('COM_AUDIOARCHIVE_ANALYSIS_PROCESS_QUEUE'); ?>
                </button>
                <div class="flex-grow-1" style="min-width: 16rem;">
                    <div class="progress" role="progressbar" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_ANALYSIS_PROCESS_PROGRESS_LABEL'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-audioarchive-analysis-progress hidden>
                        <div class="progress-bar" style="width: 0%" data-audioarchive-analysis-progress-bar></div>
                    </div>
                    <div class="small text-body-secondary mt-1" data-audioarchive-analysis-status aria-live="polite"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CODEC_TITLE'); ?></h2>
            <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CODEC_TEXT'); ?></p>
        </div>
        <?php if ($codecInventory === []) : ?>
            <div class="card-body text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CODEC_EMPTY'); ?></div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CODEC'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CONTAINER'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_EXTENSION'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CLIP_COUNT'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_TOTAL_SIZE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codecInventory as $row) : ?>
                            <?php $filterValue = (string) $row['codec_filter']; ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?php echo Route::_('index.php?option=com_audioarchive&view=maintenance&codec=' . rawurlencode($filterValue)); ?>">
                                        <?php echo (string) $row['codec'] !== ''
                                            ? htmlspecialchars((string) $row['codec'], ENT_QUOTES, 'UTF-8')
                                            : Text::_('COM_AUDIOARCHIVE_MAINTENANCE_UNKNOWN_CODEC'); ?>
                                    </a>
                                </th>
                                <td><?php echo htmlspecialchars((string) ($row['container'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><code><?php echo htmlspecialchars((string) ($row['extension'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td class="text-end"><?php echo (int) $row['clip_count']; ?></td>
                                <td class="text-end"><?php echo htmlspecialchars($formatBytes((int) $row['total_size']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($codecFilter !== '') : ?>
            <div class="card-header border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0">
                    <?php echo Text::sprintf(
                        'COM_AUDIOARCHIVE_MAINTENANCE_CODEC_CLIPS_TITLE',
                        $codecFilter === '__unknown__'
                            ? Text::_('COM_AUDIOARCHIVE_MAINTENANCE_UNKNOWN_CODEC')
                            : htmlspecialchars($codecFilter, ENT_QUOTES, 'UTF-8')
                    ); ?>
                </h3>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=maintenance'); ?>">
                    <?php echo Text::_('JCLEAR'); ?>
                </a>
            </div>
            <?php if ($codecClips === []) : ?>
                <div class="card-body text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CODEC_CLIPS_EMPTY'); ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_FILENAME'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CONTAINER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_DURATION'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_TOTAL_SIZE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($codecClips as $clip) : ?>
                                <?php
                                $seconds = max(0, intdiv((int) $clip->duration_ms, 1000));
                                $duration = sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
                                ?>
                                <tr>
                                    <th scope="row">
                                        <a href="<?php echo Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . (int) $clip->id); ?>">
                                            <?php echo htmlspecialchars((string) $clip->title, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <div class="small text-body-secondary">#<?php echo (int) $clip->id; ?></div>
                                    </th>
                                    <td><?php echo htmlspecialchars((string) $clip->original_filename, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) ($clip->container_format ?: '—'), ENT_QUOTES, 'UTF-8'); ?>
                                        <code class="ms-1"><?php echo htmlspecialchars((string) ($clip->file_extension ?: ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                    </td>
                                    <td><?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($formatBytes((int) $clip->file_size), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_audioarchive'); ?>" method="post" id="adminForm" name="adminForm">
        <div class="card mb-4" id="audioarchive-stale-files">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_TITLE'); ?></h2>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_TEXT'); ?></p>
                </div>
                <?php if ($staleItems !== []) : ?>
                    <button
                        type="button"
                        id="audioarchive-delete-stale"
                        class="btn btn-danger"
                    >
                        <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_DELETE_SELECTED'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div id="audioarchive-stale-progress" class="alert alert-info m-3 d-none" role="status" aria-live="polite"></div>
            <?php if ($staleItems === []) : ?>
                <div class="card-body">
                    <div class="alert alert-success mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_EMPTY'); ?></div>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="w-1 text-center">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        aria-label="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                                        onclick="document.querySelectorAll('input[name=&quot;stale[]&quot;]').forEach((item) => item.checked = this.checked);"
                                    >
                                </th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_REASON'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_ROLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_STORAGE_KEY'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_TOTAL_SIZE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staleItems as $item) : ?>
                                <tr>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="stale[]"
                                            value="<?php echo htmlspecialchars((string) $item['token'], ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars((string) $item['storage_key'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                    </td>
                                    <th scope="row"><?php echo Text::_((string) $item['reason']); ?></th>
                                    <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) $item['role'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <?php if ((int) $item['clip_id'] > 0) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . (int) $item['clip_id']); ?>">
                                                <?php echo htmlspecialchars((string) ($item['clip_title'] ?: '#' . $item['clip_id']), ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <?php if ((string) $item['original_filename'] !== '') : ?>
                                                <div class="small text-body-secondary"><?php echo htmlspecialchars((string) $item['original_filename'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="text-body-secondary"><?php echo Text::_('JNONE'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code class="text-break"><?php echo htmlspecialchars((string) $item['storage_key'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td class="text-end"><?php echo htmlspecialchars($formatBytes((int) $item['size']), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($actionableClips !== []) : ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ACTIONS_TITLE'); ?></h2>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_ACTIONS_TEXT'); ?></p>
                </div>
                <div class="card-body border-bottom">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="task" value="maintenance.verify" class="btn btn-primary">
                            <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_VERIFY_SELECTED'); ?>
                        </button>
                        <button type="submit" name="task" value="maintenance.reanalyse" class="btn btn-outline-primary">
                            <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_REANALYSE_SELECTED'); ?>
                        </button>
                        <button
                            type="submit"
                            name="task"
                            value="maintenance.recalculateChecksums"
                            class="btn btn-outline-secondary"
                            onclick="return confirm('<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CHECKSUM_CONFIRM')); ?>');"
                        >
                            <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CHECKSUM_SELECTED'); ?>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="w-1 text-center">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="checkall-toggle"
                                        value=""
                                        title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                                        onclick="Joomla.checkAll(this);"
                                    >
                                </th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_FILENAME'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_ISSUES'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actionableClips as $index => $clip) : ?>
                                <tr>
                                    <td class="text-center">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="cb<?php echo (int) $index; ?>"
                                            name="cid[]"
                                            value="<?php echo (int) $clip['id']; ?>"
                                            onclick="Joomla.isChecked(this.checked);"
                                        >
                                    </td>
                                    <th scope="row">
                                        <a href="<?php echo Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . (int) $clip['id']); ?>">
                                            <?php echo htmlspecialchars((string) $clip['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <div class="small text-body-secondary">#<?php echo (int) $clip['id']; ?></div>
                                    </th>
                                    <td><?php echo htmlspecialchars((string) $clip['original_filename'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php foreach ((array) $clip['issues'] as $label) : ?>
                                            <span class="badge text-bg-secondary me-1 mb-1"><?php echo Text::_((string) $label); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_REPORT_TITLE'); ?></h2>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_REPORT_TEXT'); ?></p>
                </div>
                <button type="submit" name="task" value="maintenance.export" class="btn btn-outline-secondary">
                    <?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_EXPORT'); ?>
                </button>
            </div>

            <?php if ($issues === []) : ?>
                <div class="card-body">
                    <div class="alert alert-success mb-0">
                        <h3 class="h5 alert-heading"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CLEAN_TITLE'); ?></h3>
                        <p class="mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_CLEAN_TEXT'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_SEVERITY'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_ISSUE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_CLIP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_MAINTENANCE_COLUMN_DETAILS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issues as $issue) : ?>
                                <?php $severity = (string) $issue['severity']; ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $severityClasses[$severity] ?? 'bg-secondary'; ?>">
                                            <?php echo Text::_($severityKeys[$severity] ?? 'COM_AUDIOARCHIVE_MAINTENANCE_SEVERITY_INFO'); ?>
                                        </span>
                                    </td>
                                    <th scope="row"><?php echo Text::_((string) $issue['label']); ?></th>
                                    <td>
                                        <?php if ((int) $issue['clip_id'] > 0) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . (int) $issue['clip_id']); ?>">
                                                <?php echo (string) $issue['clip_title'] !== ''
                                                    ? htmlspecialchars((string) $issue['clip_title'], ENT_QUOTES, 'UTF-8')
                                                    : '#' . (int) $issue['clip_id']; ?>
                                            </a>
                                            <?php if ((string) $issue['original_filename'] !== '') : ?>
                                                <div class="small text-body-secondary">
                                                    <?php echo htmlspecialchars((string) $issue['original_filename'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="text-body-secondary"><?php echo Text::_('JNONE'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars((string) $issue['detail'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ((string) $issue['storage_key'] !== '') : ?>
                                            <code class="d-block mt-1 text-break"><?php echo htmlspecialchars((string) $issue['storage_key'], ENT_QUOTES, 'UTF-8'); ?></code>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
(() =>
{
    const button = document.getElementById('audioarchive-delete-stale');

    if (!button)
    {
        return;
    }

    const progress = document.getElementById('audioarchive-stale-progress');
    const endpoint = <?php echo json_encode(Route::_('index.php?option=com_audioarchive&task=maintenance.deleteStaleBatch&format=json', false)); ?>;
    const confirmText = <?php echo json_encode(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_CONFIRM')); ?>;
    const progressText = <?php echo json_encode(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_PROGRESS')); ?>;
    const failedText = <?php echo json_encode(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_AJAX_FAILED')); ?>;

    button.addEventListener('click', async () =>
    {
        const selected = Array.from(document.querySelectorAll('input[name="stale[]"]:checked'));

        if (selected.length === 0)
        {
            Joomla.renderMessages({warning: [<?php echo json_encode(Text::_('COM_AUDIOARCHIVE_MAINTENANCE_STALE_NO_SELECTION')); ?>]});
            return;
        }

        if (!window.confirm(confirmText))
        {
            return;
        }

        const token = document.querySelector('#adminForm input[type="hidden"][value="1"]');
        let deleted = 0;
        let failed = 0;
        button.disabled = true;
        progress.classList.remove('d-none');

        try
        {
            for (let offset = 0; offset < selected.length; offset += 200)
            {
                const batch = selected.slice(offset, offset + 200);
                const formData = new FormData();

                if (token)
                {
                    formData.append(token.name, '1');
                }

                batch.forEach((checkbox) => formData.append('stale[]', checkbox.value));
                progress.textContent = progressText
                    .replace('%1$d', String(Math.min(offset + batch.length, selected.length)))
                    .replace('%2$d', String(selected.length));

                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await response.json();

                if (!response.ok || payload.success === false)
                {
                    throw new Error(payload.message || failedText);
                }

                const result = payload.data || {};
                deleted += Number(result.succeeded || 0);
                failed += Number(result.failed || 0);
                batch.forEach((checkbox) => checkbox.closest('tr')?.remove());
            }

            window.location.reload();
        }
        catch (error)
        {
            button.disabled = false;
            progress.classList.add('d-none');
            Joomla.renderMessages({error: [error instanceof Error ? error.message : failedText]});
        }
    });
})();
</script>
