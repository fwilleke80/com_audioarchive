<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

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
			<?php
			echo LayoutHelper::render(
				'player.unified',
				[
					'audioId' => $audioId,
					'clipId' => (int) $item->id,
					'title' => (string) $item->title,
					'streamUrl' => (string) $item->stream_url,
					'waveformUrl' => '',
					'mime' => $mime,
					'params' => $this->params,
					'presentation' => 'minimal',
					'labels' => [
						'play' => Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', (string) $item->title),
						'pause' => Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', (string) $item->title),
						'seek' => Text::_('COM_AUDIOARCHIVE_PLAYER_SEEK'),
						'mute' => Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE'),
						'unmute' => Text::_('COM_AUDIOARCHIVE_PLAYER_UNMUTE'),
						'volume' => Text::_('COM_AUDIOARCHIVE_PLAYER_VOLUME'),
						'fallback' => Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'),
						'waveformLoading' => Text::_('COM_AUDIOARCHIVE_WAVEFORM_LOADING'),
					],
				],
				null,
				[
					'component' => 'com_audioarchive',
					'client' => 0,
				]
			);
			?>
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
