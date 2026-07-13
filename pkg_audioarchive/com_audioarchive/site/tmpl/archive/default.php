<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;
?>
<div
	class="com-audioarchive com-audioarchive-archive"
	data-audioarchive-status-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PLAYING')); ?>"
	data-audioarchive-status-paused="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PAUSED')); ?>"
	data-audioarchive-status-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_ERROR')); ?>"
	<?php if ($this->playCountUrl !== '') : ?>
		data-audioarchive-play-count-url="<?php echo $this->escape($this->playCountUrl); ?>"
		data-audioarchive-token-name="<?php echo $this->escape($this->playCountToken); ?>"
	<?php endif; ?>
>
	<header class="com-audioarchive-page-header">
		<?php if ((int) $this->params->get('show_page_heading', 1) === 1) : ?>
			<h1><?php echo $this->escape($this->pageHeading); ?></h1>
		<?php endif; ?>
	</header>

	<div class="visually-hidden" aria-live="polite" aria-atomic="true" data-audioarchive-status></div>

	<?php if ($this->filterErrors) : ?>
		<div class="com-audioarchive-messages" role="status">
			<?php foreach ($this->filterErrors as $errorKey) : ?>
				<div class="alert alert-warning" role="alert"><?php echo Text::_($errorKey); ?></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php echo $this->loadTemplate('filters'); ?>
	<?php echo $this->loadTemplate('active_filters'); ?>

	<div class="com-audioarchive-results-header">
		<?php if ((int) $this->params->get('archive_show_result_count', 1) === 1) : ?>
			<?php
			$pageCount = count($this->items);
			$totalCount = (int) $this->pagination->total;
			$firstItem = $pageCount > 0 ? (int) $this->pagination->limitstart + 1 : 0;
			$lastItem = $pageCount > 0 ? $firstItem + $pageCount - 1 : 0;
			?>
			<p class="com-audioarchive-result-count" aria-live="polite">
				<strong><?php echo Text::plural('COM_AUDIOARCHIVE_RESULTS_DISPLAYED', $pageCount); ?></strong>
				<?php if ($pageCount === 1) : ?>
					<span><?php echo Text::sprintf('COM_AUDIOARCHIVE_RESULTS_RANGE_ONE', $firstItem, $totalCount); ?></span>
				<?php elseif ($pageCount > 1) : ?>
					<span><?php echo Text::sprintf('COM_AUDIOARCHIVE_RESULTS_RANGE_MORE', $firstItem, $lastItem, $totalCount); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

	<?php echo $this->loadTemplate('table'); ?>
	<?php echo $this->loadTemplate('pagination'); ?>
</div>
