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
<div class="com-audioarchive-active-filters" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_ACTIVE_FILTERS'); ?>">
	<strong><?php echo Text::_('COM_AUDIOARCHIVE_ACTIVE_FILTERS'); ?>:</strong>
	<?php if ((string) $this->state->get('filter.search') !== '') : ?>
		<span><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_SEARCH', $this->escape((string) $this->state->get('filter.search'))); ?></span>
	<?php endif; ?>
	<?php $categoryId = (int) $this->state->get('filter.category'); ?>
	<?php if ($categoryId > 0 && isset($categoryNames[$categoryId])) : ?>
		<span><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_CATEGORY', $this->escape($categoryNames[$categoryId])); ?></span>
	<?php endif; ?>
	<?php foreach ((array) $this->state->get('filter.tags', []) as $tagId) : ?>
		<?php if (isset($tagNames[(int) $tagId])) : ?>
			<span><?php echo Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_TAG', $this->escape($tagNames[(int) $tagId])); ?></span>
		<?php endif; ?>
	<?php endforeach; ?>
	<a href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET'); ?></a>
</div>
