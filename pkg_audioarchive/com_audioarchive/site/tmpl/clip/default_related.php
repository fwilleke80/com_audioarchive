<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

if ((int) $this->params->get('related_show', 1) !== 1 || !$this->relatedClips)
{
	return;
}

$showCategory = (int) $this->params->get('related_show_category', 0) === 1;
$showDuration = (int) $this->params->get('related_show_duration', 1) === 1;
$showTags = (int) $this->params->get('related_show_tags', 1) === 1;
$showShare = (int) $this->params->get('related_show_share', 1) === 1;
$showAdd = (int) $this->params->get('related_show_add', 1) === 1;
$soundboardEnabled = (int) $this->params->get('enable_soundboard', 1) === 1;
$playlistsEnabled = (int) $this->params->get('enable_playlists', 1) === 1;
$showAddAction = $showAdd && ($soundboardEnabled || $playlistsEnabled);
$showActions = $showShare || $showAddAction;

$renderPlayer = function(object $relatedClip, string $audioId): string
{
	$mime = trim((string) $relatedClip->mime_type) ?: 'application/octet-stream';

	return LayoutHelper::render(
		'player.unified',
		[
			'audioId' => $audioId,
			'clipId' => (int) $relatedClip->id,
			'title' => (string) $relatedClip->title,
			'streamUrl' => (string) $relatedClip->stream_url,
			'waveformUrl' => '',
			'mime' => $mime,
			'params' => $this->params,
			'presentation' => 'minimal',
			'labels' => [
				'play' => Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', (string) $relatedClip->title),
				'pause' => Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', (string) $relatedClip->title),
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
};

$renderTags = function(object $relatedClip): string
{
	if (!$relatedClip->tags)
	{
		return '<span class="com-audioarchive-empty-value">—</span>';
	}

	$html = '<ul class="com-audioarchive-tag-list com-audioarchive-tag-list--linked">';

	foreach ($relatedClip->tags as $tag)
	{
		$url = $this->getTagUrl($tag);
		$description = trim((string) ($tag->description_text ?? ''));
		$html .= '<li><a href="' . $this->escape($url) . '"';

		if ($description !== '')
		{
			$html .= ' title="' . $this->escape($description) . '"';
		}

		$html .= '>' . $this->escape((string) $tag->title) . '</a></li>';
	}

	return $html . '</ul>';
};

$renderActions = function(object $relatedClip) use ($showShare, $showAddAction, $soundboardEnabled, $playlistsEnabled): string
{
	$html = '<div class="com-audioarchive-inline-actions">';

	if ($showShare)
	{
		$html .= LayoutHelper::render(
			'interaction.share',
			[
				'url' => (string) $relatedClip->share_url,
				'title' => (string) $relatedClip->title,
			],
			null,
			['component' => 'com_audioarchive', 'client' => 0]
		);
	}

	if ($showAddAction)
	{
		$html .= LayoutHelper::render(
			'interaction.add_to',
			[
				'clipId' => (int) $relatedClip->id,
				'clipUuid' => (string) ($relatedClip->uuid ?? ''),
				'title' => (string) $relatedClip->title,
				'soundboardEnabled' => $soundboardEnabled,
				'playlistsEnabled' => $playlistsEnabled,
			],
			null,
			['component' => 'com_audioarchive', 'client' => 0]
		);
	}

	return $html . '</div>';
};
?>
<section class="com-audioarchive-related" aria-labelledby="audioarchive-related-heading">
	<h2 id="audioarchive-related-heading"><?php echo Text::_('COM_AUDIOARCHIVE_RELATED_CLIPS_HEADING'); ?></h2>

	<div class="com-audioarchive-mobile-card-list com-audioarchive-related-mobile-list">
		<?php foreach ($this->relatedClips as $relatedClip) : ?>
			<?php
			$totalSeconds = (int) floor((int) $relatedClip->duration_ms / 1000);
			$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
			?>
			<article class="com-audioarchive-mobile-card has-player">
				<header class="com-audioarchive-mobile-card-header">
					<div class="com-audioarchive-mobile-card-player">
						<?php echo $renderPlayer($relatedClip, 'audioarchive-related-mobile-player-' . (int) $relatedClip->id); ?>
					</div>
					<div class="com-audioarchive-mobile-card-heading">
						<a class="com-audioarchive-mobile-card-title" data-audioarchive-detail-link href="<?php echo $this->escape($relatedClip->detail_url); ?>">
							<?php echo $this->escape($relatedClip->title); ?>
						</a>
					</div>
				</header>

				<?php if ($showCategory || $showDuration) : ?>
					<dl class="com-audioarchive-mobile-card-metadata">
						<?php if ($showCategory) : ?>
							<div class="com-audioarchive-mobile-card-metadata-item">
								<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?></dt>
								<dd><?php echo $this->escape($relatedClip->category_title); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ($showDuration) : ?>
							<div class="com-audioarchive-mobile-card-metadata-item">
								<dt><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></dt>
								<dd><time datetime="PT<?php echo $totalSeconds; ?>S"><?php echo $duration; ?></time></dd>
							</div>
						<?php endif; ?>
					</dl>
				<?php endif; ?>

				<?php if ($showTags) : ?>
					<section class="com-audioarchive-mobile-card-tags" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?>">
						<span class="com-audioarchive-mobile-card-section-label"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></span>
						<?php echo $renderTags($relatedClip); ?>
					</section>
				<?php endif; ?>

				<?php if ($showActions) : ?>
					<footer class="com-audioarchive-mobile-card-actions">
						<?php echo $renderActions($relatedClip); ?>
					</footer>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="com-audioarchive-table-wrapper">
		<table class="com-audioarchive-table com-audioarchive-related-table">
			<thead>
				<tr>
					<th class="com-audioarchive-play-column" scope="col"><span class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?></span></th>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?></th>
					<?php if ($showCategory) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?></th><?php endif; ?>
					<?php if ($showDuration) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></th><?php endif; ?>
					<?php if ($showTags) : ?><th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></th><?php endif; ?>
					<?php if ($showActions) : ?><th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_ACTIONS'); ?></span></th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->relatedClips as $relatedClip) : ?>
					<?php
					$totalSeconds = (int) floor((int) $relatedClip->duration_ms / 1000);
					$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
					?>
					<tr class="com-audioarchive-result-row has-player">
						<td class="com-audioarchive-play-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?>">
							<?php echo $renderPlayer($relatedClip, 'audioarchive-related-player-' . (int) $relatedClip->id); ?>
						</td>
						<th class="com-audioarchive-title-cell" scope="row" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?>">
							<a class="com-audioarchive-title-link" data-audioarchive-detail-link href="<?php echo $this->escape($relatedClip->detail_url); ?>"><?php echo $this->escape($relatedClip->title); ?></a>
						</th>
						<?php if ($showCategory) : ?><td class="com-audioarchive-category-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_CATEGORY'); ?>"><?php echo $this->escape($relatedClip->category_title); ?></td><?php endif; ?>
						<?php if ($showDuration) : ?><td class="com-audioarchive-duration-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?>"><time datetime="PT<?php echo $totalSeconds; ?>S"><?php echo $duration; ?></time></td><?php endif; ?>
						<?php if ($showTags) : ?><td class="com-audioarchive-tags-cell" data-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?>"><?php echo $renderTags($relatedClip); ?></td><?php endif; ?>
						<?php if ($showActions) : ?><td class="com-audioarchive-actions-cell"><?php echo $renderActions($relatedClip); ?></td><?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
