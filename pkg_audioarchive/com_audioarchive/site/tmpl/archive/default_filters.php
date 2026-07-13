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
$forceOpen = $this->hasActiveFilters() || $this->filterErrors !== [];
$defaultExpanded = (string) $this->params->get('archive_filters_initial_state', 'expanded') !== 'collapsed';
$filterContentId = 'audioarchive-filter-content';
$tagListId = 'audioarchive-filter-tag-list';
?>
<?php if ($showSearch || $showCategory || $showTags || $showDuration || $showRecorded || $showUploaded) : ?>
	<section
		class="com-audioarchive-filter-panel"
		aria-labelledby="audioarchive-filter-heading"
		data-audioarchive-filter-panel
		data-force-open="<?php echo $forceOpen ? 'true' : 'false'; ?>"
		data-default-expanded="<?php echo $defaultExpanded ? 'true' : 'false'; ?>"
	>
		<header class="com-audioarchive-filter-header">
			<div>
				<h2 id="audioarchive-filter-heading"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_HEADING'); ?></h2>
				<p><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_INTRO'); ?></p>
			</div>
			<div class="com-audioarchive-filter-header-actions">
				<?php if ($this->hasActiveFilters()) : ?>
					<a class="com-audioarchive-filter-reset-link" href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET_ALL'); ?></a>
				<?php endif; ?>
				<button
					class="com-audioarchive-filter-toggle"
					type="button"
					aria-controls="<?php echo $filterContentId; ?>"
					aria-expanded="true"
					data-audioarchive-filter-toggle
					data-show-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_SHOW')); ?>"
					data-hide-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_HIDE')); ?>"
					hidden
				>
					<span data-audioarchive-filter-toggle-label><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_HIDE'); ?></span>
					<span class="com-audioarchive-filter-toggle-icon" aria-hidden="true">⌃</span>
				</button>
			</div>
		</header>

		<div id="<?php echo $filterContentId; ?>" data-audioarchive-filter-content>
			<form class="com-audioarchive-filters" method="get" action="<?php echo Route::_('index.php'); ?>">
				<input type="hidden" name="option" value="com_audioarchive">
				<input type="hidden" name="view" value="archive">
				<?php if ($itemId > 0) : ?><input type="hidden" name="Itemid" value="<?php echo $itemId; ?>"><?php endif; ?>

				<div class="com-audioarchive-filter-grid">
					<?php if ($showSearch) : ?>
						<div class="com-audioarchive-filter com-audioarchive-filter-search">
							<label for="audioarchive-filter-q"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_SEARCH'); ?></label>
							<div class="com-audioarchive-search-control">
								<span aria-hidden="true">⌕</span>
								<input
									id="audioarchive-filter-q"
									class="form-control"
									type="search"
									name="q"
									placeholder="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_SEARCH_PLACEHOLDER')); ?>"
									value="<?php echo $this->escape((string) $this->state->get('filter.search')); ?>"
								>
							</div>
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
						<fieldset class="com-audioarchive-filter com-audioarchive-filter-tags">
							<legend><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAGS'); ?></legend>
							<div class="com-audioarchive-tag-search" data-audioarchive-tag-search-wrapper hidden>
								<label class="visually-hidden" for="audioarchive-filter-tag-search"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAG_SEARCH_LABEL'); ?></label>
								<div class="com-audioarchive-search-control">
									<span aria-hidden="true">⌕</span>
									<input
										id="audioarchive-filter-tag-search"
										class="form-control"
										type="search"
										autocomplete="off"
										placeholder="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_TAG_SEARCH_PLACEHOLDER')); ?>"
										aria-controls="<?php echo $tagListId; ?>"
										data-audioarchive-tag-search
									>
								</div>
							</div>
							<div
								id="<?php echo $tagListId; ?>"
								class="com-audioarchive-tag-options"
								role="group"
								aria-describedby="audioarchive-tag-hint"
								data-audioarchive-tag-options
							>
								<?php foreach ($this->tagOptions as $tag) : ?>
									<label class="com-audioarchive-tag-option" data-audioarchive-tag-option>
										<input
											type="checkbox"
											name="tags[]"
											value="<?php echo (int) $tag->id; ?>"
											<?php echo in_array((int) $tag->id, $selectedTags, true) ? ' checked' : ''; ?>
										>
										<span><?php echo $this->escape($tag->title); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="com-audioarchive-tag-no-matches" data-audioarchive-tag-no-matches hidden aria-live="polite">
								<?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAG_NO_MATCHES'); ?>
							</p>
							<small id="audioarchive-tag-hint"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TAGS_AND_HINT'); ?></small>
						</fieldset>
					<?php endif; ?>

					<?php if ($showDuration) : ?>
						<?php
						$sliderMaximum = max(0, (int) $this->maximumDurationSeconds);
						$sliderMinimumValue = max(0, min($sliderMaximum, (int) floor((int) $this->state->get('filter.duration_min_ms', 0) / 1000)));
						$durationMaximumMs = $this->state->get('filter.duration_max_ms');
						$sliderMaximumValue = $durationMaximumMs !== null
							? max($sliderMinimumValue, min($sliderMaximum, (int) ceil((int) $durationMaximumMs / 1000)))
							: $sliderMaximum;
						?>
						<fieldset class="com-audioarchive-filter com-audioarchive-filter-range com-audioarchive-filter-duration">
							<legend><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_DURATION'); ?></legend>
							<?php if ($sliderMaximum > 0) : ?>
								<div
									class="com-audioarchive-duration-slider"
									data-audioarchive-duration-slider
									data-maximum="<?php echo $sliderMaximum; ?>"
									hidden
								>
									<div class="com-audioarchive-duration-track" data-audioarchive-duration-track>
										<input
											type="range"
											min="0"
											max="<?php echo $sliderMaximum; ?>"
											step="1"
											value="<?php echo $sliderMinimumValue; ?>"
											aria-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_DURATION_SLIDER_MIN')); ?>"
											data-audioarchive-duration-min-range
										>
										<input
											type="range"
											min="0"
											max="<?php echo $sliderMaximum; ?>"
											step="1"
											value="<?php echo $sliderMaximumValue; ?>"
											aria-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_FILTER_DURATION_SLIDER_MAX')); ?>"
											data-audioarchive-duration-max-range
										>
									</div>
									<div class="com-audioarchive-duration-slider-labels" aria-hidden="true">
										<span>0:00</span>
										<span data-audioarchive-duration-maximum-label></span>
									</div>
								</div>
							<?php endif; ?>
							<div class="com-audioarchive-range-fields">
								<div>
									<label for="audioarchive-filter-duration-min"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_FROM'); ?></label>
									<input id="audioarchive-filter-duration-min" class="form-control" type="text" inputmode="numeric" name="duration_min" placeholder="00:30" value="<?php echo $this->escape((string) $this->state->get('filter.duration_min')); ?>" data-audioarchive-duration-min-field>
								</div>
								<div>
									<label for="audioarchive-filter-duration-max"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TO'); ?></label>
									<input id="audioarchive-filter-duration-max" class="form-control" type="text" inputmode="numeric" name="duration_max" placeholder="05:00" value="<?php echo $this->escape((string) $this->state->get('filter.duration_max')); ?>" data-audioarchive-duration-max-field>
								</div>
							</div>
						</fieldset>
					<?php endif; ?>

					<?php if ($showRecorded) : ?>
						<fieldset class="com-audioarchive-filter com-audioarchive-filter-range">
							<legend><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RECORDING_DATE'); ?></legend>
							<div class="com-audioarchive-range-fields">
								<div>
									<label for="audioarchive-filter-recorded-from"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_FROM'); ?></label>
									<input id="audioarchive-filter-recorded-from" class="form-control" type="date" name="recorded_from" value="<?php echo $this->escape((string) $this->state->get('filter.recorded_from')); ?>">
								</div>
								<div>
									<label for="audioarchive-filter-recorded-to"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TO'); ?></label>
									<input id="audioarchive-filter-recorded-to" class="form-control" type="date" name="recorded_to" value="<?php echo $this->escape((string) $this->state->get('filter.recorded_to')); ?>">
								</div>
							</div>
						</fieldset>
					<?php endif; ?>

					<?php if ($showUploaded) : ?>
						<fieldset class="com-audioarchive-filter com-audioarchive-filter-range">
							<legend><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_UPLOAD_DATE'); ?></legend>
							<div class="com-audioarchive-range-fields">
								<div>
									<label for="audioarchive-filter-uploaded-from"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_FROM'); ?></label>
									<input id="audioarchive-filter-uploaded-from" class="form-control" type="date" name="uploaded_from" value="<?php echo $this->escape((string) $this->state->get('filter.uploaded_from')); ?>">
								</div>
								<div>
									<label for="audioarchive-filter-uploaded-to"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_TO'); ?></label>
									<input id="audioarchive-filter-uploaded-to" class="form-control" type="date" name="uploaded_to" value="<?php echo $this->escape((string) $this->state->get('filter.uploaded_to')); ?>">
								</div>
							</div>
						</fieldset>
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
		</div>
	</section>
<?php endif; ?>
