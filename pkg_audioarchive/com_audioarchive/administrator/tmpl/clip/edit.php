<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<form action="<?php echo Route::_('index.php?option=com_audioarchive&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="clip-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'clipTab', ['active' => 'details', 'recall' => true]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'details', Text::_('COM_AUDIOARCHIVE_FIELDSET_DETAILS')); ?>
            <div class="row">
                <div class="col-lg-9">
                    <?php echo $this->form->renderField('description'); ?>
                </div>
                <div class="col-lg-3">
                    <?php echo $this->form->renderField('catid'); ?>
                    <?php echo $this->form->renderField('tags'); ?>
                    <?php echo $this->form->renderField('recorded_at'); ?>
                    <?php echo $this->form->renderField('recorded_date_source'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
            <div class="row">
                <div class="col-lg-6">
                    <?php echo $this->form->renderFieldset('publishing'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'file', Text::_('COM_AUDIOARCHIVE_FIELDSET_FILE_INFORMATION')); ?>
            <div class="alert alert-info"><?php echo Text::_('COM_AUDIOARCHIVE_FILE_HANDLING_NOT_IMPLEMENTED'); ?></div>
            <?php echo $this->form->renderFieldset('file'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->getInput('id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
