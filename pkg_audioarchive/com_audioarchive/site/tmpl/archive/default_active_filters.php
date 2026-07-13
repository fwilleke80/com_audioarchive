<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('archive_show_active_filters', 1) !== 1 || !$this->hasActiveFilters())
{
	return;
}

$categoryNames = [];
foreach ($this->categoryOptions as $category)
{
	$categoryNames[(int) $category->id] = (string) $category->title;
}

$tagNames = [];
foreach ($this->tagOptions as $tag)
{
	$tagNames[(int) $tag->id] = (string) $tag->title;
}
?>
<section class="com-audioarchive-active-filters" aria-labelledby="audioarchive-active-filter-heading">
	<h2 id="audioarchive-active-filter-heading"><?php echo Text::_('COM_AUDIOARCHIVE_ACTIVE_FILTERS'); ?></h2>
	<ul>
		<?php if ((string) $this->state->get('filter.search') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_SEARCH', $this->escape((string) $this->state->get('filter.search'))); ?></li>
		<?php endif; ?>
		<?php $categoryId = (int) $this->state->get('filter.category'); ?>
		<?php if ($categoryId > 0 && isset($categoryNames[$categoryId])) : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_CATEGORY', $this->escape($categoryNames[$categoryId])); ?></li>
		<?php endif; ?>
		<?php foreach ((array) $this->state->get('filter.tags', []) as $tagId) : ?>
			<?php if (isset($tagNames[(int) $tagId])) : ?>
				<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_TAG', $this->escape($tagNames[(int) $tagId])); ?></li>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ((string) $this->state->get('filter.duration_min') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_DURATION_MIN', $this->escape((string) $this->state->get('filter.duration_min'))); ?></li>
		<?php endif; ?>
		<?php if ((string) $this->state->get('filter.duration_max') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_DURATION_MAX', $this->escape((string) $this->state->get('filter.duration_max'))); ?></li>
		<?php endif; ?>
		<?php if ((string) $this->state->get('filter.recorded_from') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_RECORDED_FROM', $this->escape((string) $this->state->get('filter.recorded_from'))); ?></li>
		<?php endif; ?>
		<?php if ((string) $this->state->get('filter.recorded_to') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_RECORDED_TO', $this->escape((string) $this->state->get('filter.recorded_to'))); ?></li>
		<?php endif; ?>
		<?php if ((string) $this->state->get('filter.uploaded_from') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_UPLOADED_FROM', $this->escape((string) $this->state->get('filter.uploaded_from'))); ?></li>
		<?php endif; ?>
		<?php if ((string) $this->state->get('filter.uploaded_to') !== '') : ?>
			<li><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_UPLOADED_TO', $this->escape((string) $this->state->get('filter.uploaded_to'))); ?></li>
		<?php endif; ?>
	</ul>
	<a href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET_ALL'); ?></a>
</section>
