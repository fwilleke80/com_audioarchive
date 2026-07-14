<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$item = $this->item;
$columns = $this->archiveColumns;
$totalSeconds = (int) floor((int) $item->duration_ms / 1000);
$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
$audioId = 'audioarchive-player-' . (int) $item->id;
$mime = trim((string) $item->mime_type) ?: 'application/octet-stream';
?>
<tr class="com-audioarchive-result-row <?php echo $columns['play'] ? 'has-player' : 'no-player'; ?>">
	<?php if ($columns['play']) : ?>
		<td class="com-audioarchive-play-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?>">
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
		</td>
	<?php endif; ?>
	<?php if ($columns['title']) : ?>
		<th class="com-audioarchive-title-cell" scope="row" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?>">
			<a class="com-audioarchive-title-link" href="<?php echo $item->detail_url; ?>"><?php echo $this->escape($item->title); ?></a>
			<?php if (!$columns['category'] && trim((string) $item->category_title) !== '') : ?>
				<span class="com-audioarchive-row-category"><?php echo $this->escape($item->category_title); ?></span>
			<?php endif; ?>
		</th>
	<?php endif; ?>
	<?php if ($columns['category']) : ?><td class="com-audioarchive-category-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?>"><?php echo $this->escape($item->category_title); ?></td><?php endif; ?>
	<?php if ($columns['duration']) : ?><td class="com-audioarchive-duration-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?>"><time datetime="PT<?php echo $totalSeconds; ?>S"><?php echo $duration; ?></time></td><?php endif; ?>
	<?php if ($columns['recorded']) : ?><td class="com-audioarchive-date-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_RECORDED'); ?>"><?php echo $item->recorded_at ? HTMLHelper::_('date', $item->recorded_at, Text::_('DATE_FORMAT_LC4')) : '—'; ?></td><?php endif; ?>
	<?php if ($columns['uploaded']) : ?><td class="com-audioarchive-date-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_UPLOADED'); ?>"><?php echo HTMLHelper::_('date', $item->uploaded_at, Text::_('DATE_FORMAT_LC4')); ?></td><?php endif; ?>
	<?php if ($columns['tags']) : ?>
		<td class="com-audioarchive-tags-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?>">
			<?php if ($item->tags) : ?>
				<ul class="com-audioarchive-tag-list com-audioarchive-tag-list--linked">
					<?php foreach ($item->tags as $tag) : ?>
						<?php $tagDescription = trim((string) ($tag->description_text ?? '')); ?>
						<li>
							<a href="<?php echo $this->getTagUrl((int) $tag->id); ?>"<?php if ($tagDescription !== '') : ?> title="<?php echo $this->escape($tagDescription); ?>"<?php endif; ?>>
								<?php echo $this->escape($tag->title); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?><span class="com-audioarchive-empty-value">—</span><?php endif; ?>
		</td>
	<?php endif; ?>
</tr>
