<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$item = $this->item;
$columns = $this->archiveColumns;
$totalSeconds = (int) floor((int) $item->duration_ms / 1000);
$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
$audioId = 'audioarchive-mobile-player-' . (int) $item->id;
$mime = trim((string) $item->mime_type) ?: 'application/octet-stream';
$hasMetadata = $columns['category'] || $columns['duration'] || $columns['recorded'] || $columns['uploaded'];
?>
<article class="com-audioarchive-mobile-card <?php echo $columns['play'] ? 'has-player' : 'no-player'; ?>">
	<header class="com-audioarchive-mobile-card-header">
		<?php if ($columns['play']) : ?>
			<div class="com-audioarchive-mobile-card-player">
				<button
					class="com-audioarchive-play-button"
					type="button"
					aria-controls="<?php echo $audioId; ?>"
					aria-pressed="false"
					aria-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $this->escape($item->title)); ?>"
					title="<?php echo Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $this->escape($item->title)); ?>"
					data-audioarchive-play
					data-clip-id="<?php echo (int) $item->id; ?>"
					data-clip-title="<?php echo $this->escape((string) $item->title); ?>"
					data-play-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $this->escape($item->title)); ?>"
					data-pause-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', $this->escape($item->title)); ?>"
					data-error-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_PLAY_ERROR_LABEL', $this->escape($item->title)); ?>"
				>
					<span data-audioarchive-icon aria-hidden="true">▶</span>
				</button>
				<audio id="<?php echo $audioId; ?>" class="com-audioarchive-row-audio" preload="none">
					<source src="<?php echo $item->stream_url; ?>" type="<?php echo $this->escape($mime); ?>">
				</audio>
				<noscript><a href="<?php echo $item->detail_url; ?>"><?php echo Text::_('COM_AUDIOARCHIVE_OPEN_CLIP'); ?></a></noscript>
			</div>
		<?php endif; ?>

		<?php if ($columns['title']) : ?>
			<div class="com-audioarchive-mobile-card-heading">
				<a class="com-audioarchive-mobile-card-title" href="<?php echo $item->detail_url; ?>">
					<?php echo $this->escape($item->title); ?>
				</a>
				<?php if (!$columns['category'] && trim((string) $item->category_title) !== '') : ?>
					<span class="com-audioarchive-mobile-card-category"><?php echo $this->escape($item->category_title); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ($hasMetadata) : ?>
		<dl class="com-audioarchive-mobile-card-metadata">
			<?php if ($columns['category']) : ?>
				<div class="com-audioarchive-mobile-card-metadata-item">
					<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?></dt>
					<dd><?php echo $this->escape($item->category_title); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ($columns['duration']) : ?>
				<div class="com-audioarchive-mobile-card-metadata-item">
					<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></dt>
					<dd><time datetime="PT<?php echo $totalSeconds; ?>S"><?php echo $duration; ?></time></dd>
				</div>
			<?php endif; ?>

			<?php if ($columns['recorded']) : ?>
				<div class="com-audioarchive-mobile-card-metadata-item">
					<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_RECORDED'); ?></dt>
					<dd><?php echo $item->recorded_at ? HTMLHelper::_('date', $item->recorded_at, Text::_('DATE_FORMAT_LC4')) : '—'; ?></dd>
				</div>
			<?php endif; ?>

			<?php if ($columns['uploaded']) : ?>
				<div class="com-audioarchive-mobile-card-metadata-item">
					<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_UPLOADED'); ?></dt>
					<dd><?php echo HTMLHelper::_('date', $item->uploaded_at, Text::_('DATE_FORMAT_LC4')); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>

	<?php if ($columns['tags']) : ?>
		<section class="com-audioarchive-mobile-card-tags" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?>">
			<span class="com-audioarchive-mobile-card-section-label"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></span>
			<?php if ($item->tags) : ?>
				<ul class="com-audioarchive-tag-list com-audioarchive-tag-list--linked">
					<?php foreach ($item->tags as $tag) : ?>
						<li>
							<a href="<?php echo $this->getTagUrl((int) $tag->id); ?>">
								<?php echo $this->escape($tag->title); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<span class="com-audioarchive-empty-value">—</span>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</article>
