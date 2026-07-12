<?php

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

    <div class="card">
        <div class="card-body">
            <h2 class="h4"><?php echo Text::_('COM_AUDIOARCHIVE_DASHBOARD_FOUNDATION_HEADING'); ?></h2>
            <p><?php echo Text::_('COM_AUDIOARCHIVE_DASHBOARD_FOUNDATION_TEXT'); ?></p>
            <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&view=clips'); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_MANAGE_CLIPS'); ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_categories&extension=com_audioarchive'); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_MANAGE_CATEGORIES'); ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_audioarchive'); ?>">
                <?php echo Text::_('JOPTIONS'); ?>
            </a>
        </div>
    </div>
</div>
