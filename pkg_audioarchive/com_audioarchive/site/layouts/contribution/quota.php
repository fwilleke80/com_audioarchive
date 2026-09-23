<?php
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
$usage = $displayData['usage'];
$quota = $displayData['quota'];
$params = $displayData['params'];
?>
<div class="row g-3 mb-4" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_USERS_QUOTAS'); ?>">
<?php foreach (['storage', 'clips'] as $dimension) : ?>
<?php if (!$params->get('quota_' . $dimension . '_enabled', 0)) { continue; } ?>
<?php
$limit = (int) $quota[$dimension];
$used = (int) $usage[$dimension];
$format = static fn(int $value): string => $dimension === 'storage' ? number_format($value / 1048576, 2) . ' MB' : (string) $value;
$over = $limit >= 0 && $used > $limit;
?>
<div class="col-md-6"><div class="border rounded p-3">
<strong><?php echo Text::_($dimension === 'storage' ? 'COM_AUDIOARCHIVE_QUOTA_STORAGE_MB' : 'COM_AUDIOARCHIVE_QUOTA_CLIPS'); ?></strong>
<p class="mb-1"><?php echo $format($used) . ' / ' . ($limit < 0 ? Text::_('COM_AUDIOARCHIVE_QUOTA_UNLIMITED') : $format($limit)); ?></p>
<?php if ($limit >= 0) : ?><progress class="w-100" max="<?php echo max(1, $limit); ?>" value="<?php echo min($used, max(1, $limit)); ?>" aria-label="<?php echo $format($used); ?>"></progress><?php endif; ?>
<?php if ($over) : ?><p class="text-danger mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_FRONTEND_OVER_QUOTA'); ?></p><?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
