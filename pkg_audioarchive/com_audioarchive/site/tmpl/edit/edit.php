<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

$action = 'index.php?option=com_audioarchive&view=edit&id=' . (int) $this->item->id;

if ($this->itemId > 0)
{
	$action .= '&Itemid=' . $this->itemId;
}
?>
<div class="com-audioarchive com-audioarchive-edit">
	<header class="com-audioarchive-page-header">
		<h1><?php echo $this->escape(Text::sprintf('COM_AUDIOARCHIVE_FRONTEND_EDIT_TITLE', (string) $this->item->title)); ?></h1>
	</header>

	<form action="<?php echo Route::_($action); ?>" method="post" class="form-validate">
		<div class="com-audioarchive-edit-actions btn-toolbar gap-2 mb-4" role="toolbar">
			<button type="submit" name="task" value="edit.apply" class="btn btn-primary">
				<span class="icon-apply" aria-hidden="true"></span>
				<?php echo Text::_('JAPPLY'); ?>
			</button>
			<button type="submit" name="task" value="edit.save" class="btn btn-success">
				<span class="icon-save" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_SAVE_AND_CLOSE'); ?>
			</button>
			<button type="submit" name="task" value="edit.cancel" class="btn btn-secondary" formnovalidate>
				<span class="icon-cancel" aria-hidden="true"></span>
				<?php echo Text::_('JCANCEL'); ?>
			</button>
		</div>

		<div class="row g-4">
			<div class="col-12 col-xl-8">
				<fieldset class="options-form">
					<legend><?php echo Text::_('COM_AUDIOARCHIVE_FIELDSET_DETAILS'); ?></legend>
					<?php echo $this->form->renderFieldset('details'); ?>
				</fieldset>
			</div>

			<?php if ($this->form->getFieldset('publishing') !== []) : ?>
				<div class="col-12 col-xl-4">
					<fieldset class="options-form">
						<legend><?php echo Text::_('COM_AUDIOARCHIVE_FIELDSET_PUBLISHING'); ?></legend>
						<?php echo $this->form->renderFieldset('publishing'); ?>
					</fieldset>
				</div>
			<?php endif; ?>
		</div>

		<input type="hidden" name="return" value="<?php echo $this->escape($this->returnValue); ?>">
		<?php echo $this->form->getInput('id'); ?>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
