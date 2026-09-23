<?php
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
\defined('_JEXEC') or die;
$format = static fn($value, int $divisor): string => $value === null ? Text::_('COM_AUDIOARCHIVE_QUOTA_INHERIT') : ((int) $value < 0 ? Text::_('COM_AUDIOARCHIVE_QUOTA_UNLIMITED') : number_format((int) $value / $divisor, 0));
?>
<div class="main-card p-3">
<p><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_RULES_HELP'); ?></p>
<table class="table">
<caption><?php echo Text::_('COM_AUDIOARCHIVE_QUOTAS'); ?></caption>
<thead><tr><th><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_GROUP'); ?></th><th><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_STORAGE_MB'); ?></th><th><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_CLIPS'); ?></th></tr></thead>
<tbody><?php foreach ($this->rules as $rule) : ?>
<tr><th><?php echo $this->escape($rule->title ?? ('#' . $rule->group_id)); ?></th><td><?php echo $format($rule->storage_quota_bytes, 1048576); ?></td><td><?php echo $format($rule->clip_quota, 1); ?></td></tr>
<?php endforeach; ?></tbody></table>
<form method="post" action="<?php echo Route::_('index.php?option=com_audioarchive&task=quotas.save'); ?>">
<h2 class="h4"><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_EDIT_RULE'); ?></h2>
<label for="quota-group"><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_GROUP'); ?></label>
<select class="form-select mb-3" id="quota-group" name="group_id" required>
<?php foreach ($this->groups as $group) : ?><option value="<?php echo (int) $group->id; ?>"><?php echo $this->escape($group->title); ?></option><?php endforeach; ?>
</select>
<?php foreach (['storage' => 'COM_AUDIOARCHIVE_QUOTA_STORAGE_MB', 'clips' => 'COM_AUDIOARCHIVE_QUOTA_CLIPS'] as $dimension => $label) : ?>
<fieldset class="mb-3"><legend class="h6"><?php echo Text::_($label); ?></legend>
<label class="visually-hidden" for="<?php echo $dimension; ?>-mode"><?php echo Text::_($label); ?></label>
<select class="form-select" name="<?php echo $dimension; ?>_mode" id="<?php echo $dimension; ?>-mode">
<?php foreach (['inherit', 'unlimited', 'custom'] as $mode) : ?><option value="<?php echo $mode; ?>"><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_' . strtoupper($mode)); ?></option><?php endforeach; ?>
</select>
<label for="<?php echo $dimension; ?>-value"><?php echo Text::_('COM_AUDIOARCHIVE_QUOTA_CUSTOM'); ?></label>
<input class="form-control" type="number" min="0" max="2147483647" step="1" value="0" name="<?php echo $dimension; ?>_value" id="<?php echo $dimension; ?>-value">
</fieldset>
<?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
<?php echo HTMLHelper::_('form.token'); ?>
</form></div>
