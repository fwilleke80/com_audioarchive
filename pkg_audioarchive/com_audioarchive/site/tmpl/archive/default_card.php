<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

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
			</div>
		<?php endif; ?>

		<?php if ($columns['title']) : ?>
			<div class="com-audioarchive-mobile-card-heading">
				<a class="com-audioarchive-mobile-card-title" data-audioarchive-detail-link href="<?php echo $item->detail_url; ?>">
					<?php echo $this->escape($item->title); ?>
				</a>
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
						<?php $tagDescription = trim((string) ($tag->description_text ?? '')); ?>
						<li>
							<a href="<?php echo $this->getTagUrl((int) $tag->id); ?>"<?php if ($tagDescription !== '') : ?> title="<?php echo $this->escape($tagDescription); ?>"<?php endif; ?>>
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

	<?php if ($columns['rating'] || $columns['actions']) : ?>
		<footer class="com-audioarchive-mobile-card-actions">
			<?php if ($columns['rating']) : ?>
				<?php echo LayoutHelper::render('interaction.rating', ['clipId' => (int) $item->id, 'title' => (string) $item->title, 'up' => (int) ($item->rating_up ?? 0), 'down' => (int) ($item->rating_down ?? 0), 'canVote' => $this->canRate], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
			<?php endif; ?>
			<?php if ($columns['actions']) : ?>
				<div class="com-audioarchive-inline-actions">
					<?php if ((int) $this->params->get('archive_show_share', 1) === 1) : ?>
						<?php echo LayoutHelper::render('interaction.share', ['url' => (string) $item->share_url, 'title' => (string) $item->title], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
					<?php endif; ?>
					<?php if ((int) $this->params->get('enable_soundboard', 1) === 1 || (int) $this->params->get('enable_playlists', 1) === 1) : ?>
						<?php echo LayoutHelper::render('interaction.add_to', ['clipId' => (int) $item->id, 'clipUuid' => (string) ($item->uuid ?? ''), 'title' => (string) $item->title, 'soundboardEnabled' => (int) $this->params->get('enable_soundboard', 1) === 1, 'playlistsEnabled' => (int) $this->params->get('enable_playlists', 1) === 1], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</footer>
	<?php endif; ?>
</article>
