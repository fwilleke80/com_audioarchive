<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if (!$this->items)
{
	echo '<div class="alert alert-info">' . Text::_('COM_AUDIOARCHIVE_NO_RESULTS') . '</div>';
	return;
}

$columns = [
	'play' => (int) $this->params->get('archive_column_play', 1) === 1,
	'title' => (int) $this->params->get('archive_column_title', 1) === 1,
	'category' => (int) $this->params->get('archive_column_category', 0) === 1,
	'duration' => (int) $this->params->get('archive_column_duration', 1) === 1,
	'recorded' => (int) $this->params->get('archive_column_recorded', 1) === 1,
	'uploaded' => (int) $this->params->get('archive_column_uploaded', 1) === 1,
	'tags' => (int) $this->params->get('archive_column_tags', 1) === 1,
];
$currentSort = (string) $this->state->get('list.ordering', 'uploaded');
$currentDirection = strtoupper((string) $this->state->get('list.direction', 'DESC'));
$sortLink = function(string $field) use ($currentSort, $currentDirection): string
{
	$direction = $currentSort === $field && $currentDirection === 'ASC' ? 'desc' : 'asc';
	return $this->buildUrl(['sort' => $field, 'direction' => $direction, 'limitstart' => null], ['limitstart']);
};
$ariaSort = static function(string $field) use ($currentSort, $currentDirection): string
{
	if ($field !== $currentSort)
	{
		return 'none';
	}
	return $currentDirection === 'ASC' ? 'ascending' : 'descending';
};
?>
<div class="com-audioarchive-table-wrapper">
	<table class="com-audioarchive-table">
		<thead>
			<tr>
				<?php if ($columns['play']) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?></th><?php endif; ?>
				<?php if ($columns['title']) : ?><th scope="col" aria-sort="<?php echo $ariaSort('title'); ?>"><a href="<?php echo $sortLink('title'); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?></a></th><?php endif; ?>
				<?php if ($columns['category']) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?></th><?php endif; ?>
				<?php if ($columns['duration']) : ?><th scope="col" aria-sort="<?php echo $ariaSort('duration'); ?>"><a href="<?php echo $sortLink('duration'); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></a></th><?php endif; ?>
				<?php if ($columns['recorded']) : ?><th scope="col" aria-sort="<?php echo $ariaSort('recorded'); ?>"><a href="<?php echo $sortLink('recorded'); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_RECORDED'); ?></a></th><?php endif; ?>
				<?php if ($columns['uploaded']) : ?><th scope="col" aria-sort="<?php echo $ariaSort('uploaded'); ?>"><a href="<?php echo $sortLink('uploaded'); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_UPLOADED'); ?></a></th><?php endif; ?>
				<?php if ($columns['tags']) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></th><?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->items as $item) : ?>
				<?php $this->item = $item; $this->archiveColumns = $columns; ?>
				<?php echo $this->loadTemplate('row'); ?>
			<?php endforeach; ?>
			<?php $this->item = null; $this->archiveColumns = []; ?>
		</tbody>
	</table>
</div>
