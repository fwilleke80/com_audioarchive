<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

$showPagination = (int) $this->params->get('archive_show_pagination', 1) === 1;
$showPageSize = (int) $this->params->get('archive_show_page_size', 1) === 1;

if (!$showPagination && !$showPageSize)
{
	return;
}

$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
$queryValues = $this->getQueryValues();
unset($queryValues['limit']);
?>
<div class="com-audioarchive-pagination-bar">
	<?php if ($showPageSize) : ?>
		<form class="com-audioarchive-page-size" method="get" action="<?php echo Route::_('index.php'); ?>">
			<input type="hidden" name="option" value="com_audioarchive">
			<input type="hidden" name="view" value="archive">
			<?php if ($itemId > 0) : ?><input type="hidden" name="Itemid" value="<?php echo $itemId; ?>"><?php endif; ?>
			<?php foreach ($queryValues as $key => $value) : ?>
				<?php if (is_array($value)) : ?>
					<?php foreach ($value as $entry) : ?><input type="hidden" name="<?php echo $this->escape($key); ?>[]" value="<?php echo $this->escape((string) $entry); ?>"><?php endforeach; ?>
				<?php else : ?>
					<input type="hidden" name="<?php echo $this->escape($key); ?>" value="<?php echo $this->escape((string) $value); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<label for="audioarchive-page-size"><?php echo Text::_('COM_AUDIOARCHIVE_PAGE_SIZE'); ?></label>
			<select id="audioarchive-page-size" class="form-select" name="limit">
				<?php foreach ($this->pageSizeOptions as $pageSize) : ?>
					<option value="<?php echo (int) $pageSize; ?>"<?php echo (int) $this->state->get('list.limit') === (int) $pageSize ? ' selected' : ''; ?>><?php echo (int) $pageSize; ?></option>
				<?php endforeach; ?>
			</select>
			<button class="btn btn-secondary" type="submit"><?php echo Text::_('COM_AUDIOARCHIVE_PAGE_SIZE_APPLY'); ?></button>
		</form>
	<?php endif; ?>

	<?php if ($showPagination && (int) $this->pagination->pagesTotal > 1) : ?>
		<nav class="com-audioarchive-pagination" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_PAGINATION_LABEL'); ?>">
			<?php echo $this->pagination->getPagesLinks(); ?>
		</nav>
	<?php endif; ?>
</div>
