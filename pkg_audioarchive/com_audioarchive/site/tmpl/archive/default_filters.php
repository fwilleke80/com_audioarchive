<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

$showSearch = (int) $this->params->get('archive_show_search', 1) === 1;
$showCategory = (int) $this->params->get('archive_show_category_filter', 1) === 1
	&& (int) $this->params->get('archive_category_restriction', 0) === 0;
$showTags = (int) $this->params->get('archive_show_tag_filter', 1) === 1;
$showDuration = (int) $this->params->get('archive_show_duration_filter', 1) === 1;
$showRecorded = (int) $this->params->get('archive_show_recorded_filter', 1) === 1;
$showUploaded = (int) $this->params->get('archive_show_uploaded_filter', 1) === 1;
$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
$selectedTags = array_map('intval', (array) $this->state->get('filter.tags', []));
?>
<?php if ($showSearch || $showCategory || $showTags || $showDuration || $showRecorded || $showUploaded) : ?>
	<form class="com-audioarchive-filters" method="get" action="<?php echo Route::_('index.php'); ?>">
		<input type="hidden" name="option" value="com_audioarchive">
		<input type="hidden" name="view" value="archive">
		<?php if ($itemId > 0) : ?><input type="hidden" name="Itemid" value="<?php echo $itemId; ?>"><?php endif; ?>

		<div class="com-audioarchive-filter-grid">
			<?php if ($showSearch) : ?>
				<div class="com-audioarchive-filter com-audioarchive-filter-search">
					<label for="audioarchive-filter-q"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_SEARCH'); ?></label>
					<input id="audioarchive-filter-q" class="form-control" type="search" name="q" value="<?php echo $this->escape((string) $this->state->get('filter.search')); ?>">
				</div>
			<?php endif; ?>

			<?php if ($showCategory) : ?>
				<div class="com-audioarchive-filter">
					<label for="audioarchive-filter-category"><?php echo Text::_('JCATEGORY'); ?></label>
					<select id="audioarchive-filter-category" class="form-select" name="category">
						<option value="0"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_ALL_CATEGORIES'); ?></option>
						<?php foreach ($this->categoryOptions as $category) : ?>
							<option value="<?php echo (int) $category->id; ?>"<?php echo (int) $this->state->get('filter.category') === (int) $category->id ? ' selected' : ''; ?>>
								<?php echo str_repeat('— ', max(0, (int) $category->level - 1)) . $this->escape($category->title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ($showTags && $this->tagOptions) : ?>
				<div class="com-audioarchive-filter">
					<label for="audioarchive-filter-tags"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAGS'); ?></label>
					<select id="audioarchive-filter-tags" class="form-select" name="tags[]" multiple size="5">
						<?php foreach ($this->tagOptions as $tag) : ?>
							<option value="<?php echo (int) $tag->id; ?>"<?php echo in_array((int) $tag->id, $selectedTags, true) ? ' selected' : ''; ?>><?php echo $this->escape($tag->title); ?></option>
						<?php endforeach; ?>
					</select>
					<small><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAGS_AND_HINT'); ?></small>
				</div>
			<?php endif; ?>

			<?php if ($showDuration) : ?>
				<div class="com-audioarchive-filter com-audioarchive-filter-pair">
					<label for="audioarchive-filter-duration-min"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_DURATION_MIN'); ?></label>
					<input id="audioarchive-filter-duration-min" class="form-control" type="text" inputmode="numeric" name="duration_min" placeholder="00:30" value="<?php echo $this->escape((string) $this->state->get('filter.duration_min')); ?>">
					<label for="audioarchive-filter-duration-max"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_DURATION_MAX'); ?></label>
					<input id="audioarchive-filter-duration-max" class="form-control" type="text" inputmode="numeric" name="duration_max" placeholder="05:00" value="<?php echo $this->escape((string) $this->state->get('filter.duration_max')); ?>">
				</div>
			<?php endif; ?>

			<?php if ($showRecorded) : ?>
				<div class="com-audioarchive-filter com-audioarchive-filter-pair">
					<label for="audioarchive-filter-recorded-from"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RECORDED_FROM'); ?></label>
					<input id="audioarchive-filter-recorded-from" class="form-control" type="date" name="recorded_from" value="<?php echo $this->escape((string) $this->state->get('filter.recorded_from')); ?>">
					<label for="audioarchive-filter-recorded-to"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RECORDED_TO'); ?></label>
					<input id="audioarchive-filter-recorded-to" class="form-control" type="date" name="recorded_to" value="<?php echo $this->escape((string) $this->state->get('filter.recorded_to')); ?>">
				</div>
			<?php endif; ?>

			<?php if ($showUploaded) : ?>
				<div class="com-audioarchive-filter com-audioarchive-filter-pair">
					<label for="audioarchive-filter-uploaded-from"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_UPLOADED_FROM'); ?></label>
					<input id="audioarchive-filter-uploaded-from" class="form-control" type="date" name="uploaded_from" value="<?php echo $this->escape((string) $this->state->get('filter.uploaded_from')); ?>">
					<label for="audioarchive-filter-uploaded-to"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_UPLOADED_TO'); ?></label>
					<input id="audioarchive-filter-uploaded-to" class="form-control" type="date" name="uploaded_to" value="<?php echo $this->escape((string) $this->state->get('filter.uploaded_to')); ?>">
				</div>
			<?php endif; ?>
		</div>

		<input type="hidden" name="sort" value="<?php echo $this->escape((string) $this->state->get('list.ordering')); ?>">
		<input type="hidden" name="limit" value="<?php echo (int) $this->state->get('list.limit'); ?>">
		<input type="hidden" name="direction" value="<?php echo strtolower($this->escape((string) $this->state->get('list.direction'))); ?>">
		<div class="com-audioarchive-filter-actions">
			<button class="btn btn-primary" type="submit"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_APPLY'); ?></button>
			<a class="btn btn-secondary" href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET'); ?></a>
		</div>
	</form>
<?php endif; ?>
