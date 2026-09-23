<?php
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
\defined('_JEXEC') or die;
?>
<div class="com-audioarchive">
<h1><?php echo Text::_('COM_AUDIOARCHIVE_FRONTEND_UPLOAD'); ?></h1>
<?php if ($this->params->get('upload_intro')) : ?><div class="mb-3"><?php echo $this->params->get('upload_intro'); ?></div><?php endif; ?>
<?php echo LayoutHelper::render('contribution.quota', ['usage' => $this->usage, 'quota' => $this->quota, 'params' => $this->params], JPATH_SITE . '/components/com_audioarchive/layouts'); ?>
<?php if (!$this->canUpload) : ?>
<div class="alert alert-info"><?php echo Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_NO_CATEGORY'); ?></div>
<?php else : ?>
<form method="post" enctype="multipart/form-data" class="form-validate" action="<?php echo Route::_('index.php?option=com_audioarchive&task=upload.submit&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid')); ?>">
<?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
<fieldset class="mb-4"><legend class="h4"><?php echo Text::_($fieldset->label ?: $fieldset->name); ?></legend><?php echo $this->form->renderFieldset($fieldset->name); ?></fieldset>
<?php endforeach; ?>
<p class="text-muted"><?php echo Text::_('COM_AUDIOARCHIVE_FRONTEND_MODERATION_HINT'); ?></p>
<button class="btn btn-primary" type="submit"><?php echo Text::_('COM_AUDIOARCHIVE_FRONTEND_UPLOAD'); ?></button>
<a class="btn btn-secondary" href="<?php echo Route::_($this->workspace); ?>"><?php echo Text::_('JCANCEL'); ?></a>
<?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php endif; ?>
</div>
