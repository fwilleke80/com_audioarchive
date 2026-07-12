<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

$cards = [
    ['label' => 'COM_AUDIOARCHIVE_DASHBOARD_TOTAL', 'value' => $this->counts['total'], 'link' => 'index.php?option=com_audioarchive&view=clips'],
    ['label' => 'COM_AUDIOARCHIVE_DASHBOARD_PUBLISHED', 'value' => $this->counts['published'], 'link' => 'index.php?option=com_audioarchive&view=clips&filter_state=1'],
    ['label' => 'COM_AUDIOARCHIVE_DASHBOARD_UNPUBLISHED', 'value' => $this->counts['unpublished'], 'link' => 'index.php?option=com_audioarchive&view=clips&filter_state=0'],
    ['label' => 'COM_AUDIOARCHIVE_DASHBOARD_TRASHED', 'value' => $this->counts['trashed'], 'link' => 'index.php?option=com_audioarchive&view=clips&filter_state=-2'],
];

$statusClasses = [
    'ok' => 'bg-success',
    'warning' => 'bg-warning text-dark',
    'error' => 'bg-danger',
    'neutral' => 'bg-secondary',
];

$statusLabels = [
    'ok' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_OK',
    'warning' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_WARNING',
    'error' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_ERROR',
    'neutral' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_INFO',
];

$translateValue = static function (string $value): string
{
    if (str_starts_with($value, 'COM_AUDIOARCHIVE_'))
    {
        return Text::_($value);
    }

    return $value;
};
?>
<div class="com-audioarchive-dashboard">
    <div class="row g-3 mb-4">
        <?php foreach ($cards as $card) : ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="card h-100 text-decoration-none" href="<?php echo Route::_($card['link']); ?>">
                    <div class="card-body">
                        <div class="display-6"><?php echo (int) $card['value']; ?></div>
                        <div><?php echo Text::_($card['label']); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                <h2 class="h4"><?php echo Text::_('COM_AUDIOARCHIVE_DASHBOARD_FOUNDATION_HEADING'); ?></h2>
                <?php if ($this->version !== '') : ?>
                    <span class="text-body-secondary">
                        <?php echo Text::sprintf('COM_AUDIOARCHIVE_DASHBOARD_VERSION', htmlspecialchars($this->version, ENT_QUOTES, 'UTF-8')); ?>
                    </span>
                <?php endif; ?>
            </div>
            <p><?php echo Text::_('COM_AUDIOARCHIVE_DASHBOARD_FOUNDATION_TEXT'); ?></p>
            <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=clips'); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_MANAGE_CLIPS'); ?>
            </a>
            <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=upload'); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_TITLE'); ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_categories&view=categories&extension=com_audioarchive'); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_MANAGE_CATEGORIES'); ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_audioarchive'); ?>">
                <?php echo Text::_('JOPTIONS'); ?>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 mb-1"><?php echo Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_TITLE'); ?></h2>
                <div class="small text-body-secondary">
                    <?php echo Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_LAST_RUN', $this->systemCheck['checked_at']); ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php $overall = (string) $this->systemCheck['overall']; ?>
                <span class="badge <?php echo $statusClasses[$overall] ?? 'bg-secondary'; ?>">
                    <?php echo Text::_($statusLabels[$overall] ?? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_INFO'); ?>
                </span>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=dashboard&retest=1'); ?>">
                    <?php echo Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_RETEST'); ?>
                </a>
                <?php if (Factory::getApplication()->getIdentity()->authorise('audioarchive.managefiles', 'com_audioarchive')) : ?>
                    <form action="<?php echo Route::_('index.php?option=com_audioarchive&task=dashboard.createDirectories'); ?>" method="post" class="d-inline">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <?php echo Text::_('COM_AUDIOARCHIVE_CREATE_STORAGE_DIRECTORIES'); ?>
                        </button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($this->systemCheck['sections'] as $section) : ?>
            <div class="card-body border-bottom">
                <h3 class="h5"><?php echo Text::_($section['title']); ?></h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_ITEM'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_RESULT'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($section['checks'] as $check) : ?>
                                <?php $status = (string) $check['status']; ?>
                                <tr>
                                    <th scope="row"><?php echo htmlspecialchars($translateValue((string) $check['label']), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <td>
                                        <span class="badge <?php echo $statusClasses[$status] ?? 'bg-secondary'; ?>">
                                            <?php echo Text::_($statusLabels[$status] ?? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STATUS_INFO'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($translateValue((string) $check['value']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ((string) $check['detail'] !== '') : ?>
                                            <div class="small text-body-secondary text-break">
                                                <?php echo htmlspecialchars($translateValue((string) $check['detail']), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
