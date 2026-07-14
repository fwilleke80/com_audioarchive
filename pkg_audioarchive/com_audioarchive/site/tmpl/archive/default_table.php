<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if (!$this->items)
{
	?>
	<div class="com-audioarchive-empty-state" role="status">
		<span class="com-audioarchive-empty-icon" aria-hidden="true">♪</span>
		<h2><?php echo Text::_('COM_AUDIOARCHIVE_NO_RESULTS_HEADING'); ?></h2>
		<p><?php echo Text::_('COM_AUDIOARCHIVE_NO_RESULTS'); ?></p>
		<?php if ($this->hasActiveFilters()) : ?>
			<a class="btn btn-primary" href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET_ALL'); ?></a>
		<?php endif; ?>
	</div>
	<?php
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
$sortIndicator = static function(string $field) use ($currentSort, $currentDirection): string
{
	if ($field !== $currentSort)
	{
		return '↕';
	}

	return $currentDirection === 'ASC' ? '↑' : '↓';
};
$mobileSortFields = [
	'title' => $columns['title'] ? Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE') : null,
	'duration' => $columns['duration'] ? Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION') : null,
	'recorded' => $columns['recorded'] ? Text::_('COM_AUDIOARCHIVE_COLUMN_RECORDED') : null,
	'uploaded' => $columns['uploaded'] ? Text::_('COM_AUDIOARCHIVE_COLUMN_UPLOADED') : null,
];
$mobileSortFields = array_filter($mobileSortFields);
?>
<?php if ($mobileSortFields !== []) : ?>
	<nav class="com-audioarchive-mobile-sort" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SORT_RESULTS'); ?>">
		<span class="com-audioarchive-mobile-sort-label"><?php echo Text::_('COM_AUDIOARCHIVE_SORT_BY'); ?></span>
		<div class="com-audioarchive-mobile-sort-options">
			<?php foreach ($mobileSortFields as $field => $label) : ?>
				<a
					class="com-audioarchive-mobile-sort-link<?php echo $currentSort === $field ? ' is-active' : ''; ?>"
					href="<?php echo $sortLink($field); ?>"
					<?php echo $currentSort === $field ? 'aria-current="true"' : ''; ?>
				>
					<span><?php echo $label; ?></span>
					<span aria-hidden="true"><?php echo $sortIndicator($field); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
<?php endif; ?>
<div class="com-audioarchive-mobile-card-list">
	<?php foreach ($this->items as $item) : ?>
		<?php $this->item = $item; $this->archiveColumns = $columns; ?>
		<?php echo $this->loadTemplate('card'); ?>
	<?php endforeach; ?>
	<?php $this->item = null; $this->archiveColumns = []; ?>
</div>
<div class="com-audioarchive-table-wrapper">
	<table class="com-audioarchive-table">
		<thead>
			<tr>
				<?php if ($columns['play']) : ?><th class="com-audioarchive-play-column" scope="col"><span class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?></span></th><?php endif; ?>
				<?php if ($columns['title']) : ?>
					<th scope="col" aria-sort="<?php echo $ariaSort('title'); ?>">
						<a class="com-audioarchive-sort-link" href="<?php echo $sortLink('title'); ?>">
							<span><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?></span>
							<span aria-hidden="true"><?php echo $sortIndicator('title'); ?></span>
						</a>
					</th>
				<?php endif; ?>
				<?php if ($columns['category']) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?></th><?php endif; ?>
				<?php if ($columns['duration']) : ?>
					<th scope="col" aria-sort="<?php echo $ariaSort('duration'); ?>">
						<a class="com-audioarchive-sort-link" href="<?php echo $sortLink('duration'); ?>">
							<span><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></span>
							<span aria-hidden="true"><?php echo $sortIndicator('duration'); ?></span>
						</a>
					</th>
				<?php endif; ?>
				<?php if ($columns['recorded']) : ?>
					<th scope="col" aria-sort="<?php echo $ariaSort('recorded'); ?>">
						<a class="com-audioarchive-sort-link" href="<?php echo $sortLink('recorded'); ?>">
							<span><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_RECORDED'); ?></span>
							<span aria-hidden="true"><?php echo $sortIndicator('recorded'); ?></span>
						</a>
					</th>
				<?php endif; ?>
				<?php if ($columns['uploaded']) : ?>
					<th scope="col" aria-sort="<?php echo $ariaSort('uploaded'); ?>">
						<a class="com-audioarchive-sort-link" href="<?php echo $sortLink('uploaded'); ?>">
							<span><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_UPLOADED'); ?></span>
							<span aria-hidden="true"><?php echo $sortIndicator('uploaded'); ?></span>
						</a>
					</th>
				<?php endif; ?>
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
