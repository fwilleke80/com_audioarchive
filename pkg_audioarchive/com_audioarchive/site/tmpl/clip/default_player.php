<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$mime = trim((string) $this->item->mime_type) ?: 'application/octet-stream';
$playerId = 'audioarchive-detail-audio-' . (int) $this->item->id;
$seekId = $playerId . '-seek';
$volumeId = $playerId . '-volume';
$title = (string) $this->item->title;
$playLabel = Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $title);
$pauseLabel = Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', $title);
?>
<section class="com-audioarchive-detail-player" aria-labelledby="audioarchive-player-heading">
	<div class="com-audioarchive-player-symbol" aria-hidden="true">♪</div>
	<div class="com-audioarchive-player-content">
		<h2 id="audioarchive-player-heading" class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_HEADING'); ?></h2>
		<p class="com-audioarchive-player-title"><?php echo Text::_('COM_AUDIOARCHIVE_LISTEN'); ?></p>

		<div class="audioarchive-custom-player" data-audioarchive-custom-player>
			<button
				type="button"
				class="audioarchive-custom-player-toggle"
				aria-controls="<?php echo $this->escape($playerId); ?>"
				aria-label="<?php echo $this->escape($playLabel); ?>"
				aria-pressed="false"
				title="<?php echo $this->escape($playLabel); ?>"
				data-audioarchive-custom-toggle
				data-play-label="<?php echo $this->escape($playLabel); ?>"
				data-pause-label="<?php echo $this->escape($pauseLabel); ?>"
			>
				<span data-audioarchive-icon-play aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>
				</span>
				<span data-audioarchive-icon-pause aria-hidden="true" hidden>
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M6.5 5h4v14h-4zm7 0h4v14h-4z"/></svg>
				</span>
			</button>

			<div class="audioarchive-custom-player-main">
				<label class="visually-hidden" for="<?php echo $this->escape($seekId); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_SEEK'); ?></label>
				<input
					id="<?php echo $this->escape($seekId); ?>"
					class="audioarchive-custom-player-seek"
					type="range"
					min="0"
					max="1000"
					step="1"
					value="0"
					disabled
					data-audioarchive-custom-seek
				>
				<div class="audioarchive-custom-player-times" aria-hidden="true">
					<span data-audioarchive-current-time>0:00</span>
					<span data-audioarchive-duration>0:00</span>
				</div>
			</div>

			<div class="audioarchive-custom-player-volume-controls">
				<button
					type="button"
					class="audioarchive-custom-player-mute"
					aria-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE')); ?>"
					aria-pressed="false"
					title="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE')); ?>"
					data-audioarchive-custom-mute
					data-mute-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE')); ?>"
					data-unmute-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_UNMUTE')); ?>"
				>
					<span data-audioarchive-icon-volume aria-hidden="true">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.5-.8v2.1c1.2.6 2 1.8 2 3.2s-.8 2.6-2 3.2v2.1c2.3-.7 4-2.8 4-5.3s-1.7-4.6-4-5.3z"/></svg>
					</span>
					<span data-audioarchive-icon-muted aria-hidden="true" hidden>
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.7 1.3-1.4 1.4 1.8 1.8-1.8 1.8 1.4 1.4 1.8-1.8 1.8 1.8 1.4-1.4-1.8-1.8 1.8-1.8-1.4-1.4-1.8 1.8z"/></svg>
					</span>
				</button>
				<label class="visually-hidden" for="<?php echo $this->escape($volumeId); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_VOLUME'); ?></label>
				<input
					id="<?php echo $this->escape($volumeId); ?>"
					class="audioarchive-custom-player-volume"
					type="range"
					min="0"
					max="1"
					step="0.05"
					value="1"
					data-audioarchive-custom-volume
				>
			</div>

			<audio
				id="<?php echo $this->escape($playerId); ?>"
				preload="metadata"
				data-audioarchive-custom-audio
				data-clip-id="<?php echo (int) $this->item->id; ?>"
				data-clip-title="<?php echo $this->escape($title); ?>"
			>
				<source src="<?php echo $this->streamUrl; ?>" type="<?php echo $this->escape($mime); ?>">
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'); ?>
			</audio>
		</div>

		<noscript>
			<audio class="audioarchive-custom-player-noscript" controls preload="metadata">
				<source src="<?php echo $this->streamUrl; ?>" type="<?php echo $this->escape($mime); ?>">
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'); ?>
			</audio>
		</noscript>
	</div>
</section>
