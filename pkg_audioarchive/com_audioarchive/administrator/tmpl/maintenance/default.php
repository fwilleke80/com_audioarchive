<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.multiselect');

$summary = (array) ($this->report['summary'] ?? []);
$issues = (array) ($this->report['issues'] ?? []);
$actionableClips = (array) ($this->report['actionable_clips'] ?? []);
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

    <form action="<?php echo Route::_('index.php?option=com_audioarchive'); ?>" method="post" id="adminForm" name="adminForm">
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
