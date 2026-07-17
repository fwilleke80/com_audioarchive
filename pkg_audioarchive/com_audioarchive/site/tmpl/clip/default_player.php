<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

$mime = trim((string) $this->item->mime_type) ?: 'application/octet-stream';
$playerId = 'audioarchive-detail-audio-' . (int) $this->item->id;
$title = (string) $this->item->title;
?>
<section class="com-audioarchive-detail-player" aria-labelledby="audioarchive-player-heading">
	<h2 id="audioarchive-player-heading" class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_HEADING'); ?></h2>
	<p class="com-audioarchive-player-title"><?php echo Text::_('COM_AUDIOARCHIVE_LISTEN'); ?></p>

	<?php
	echo LayoutHelper::render(
		'player.unified',
		[
			'audioId' => $playerId,
			'clipId' => (int) $this->item->id,
			'title' => $title,
			'streamUrl' => $this->streamUrl,
			'waveformUrl' => $this->waveformUrl,
			'spectrogramUrl' => $this->spectrogramUrl,
			'presentation' => (string) $this->params->get('detail_presentation', 'featured'),
			'mime' => $mime,
			'params' => $this->params,
			'labels' => [
				'play' => Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $title),
				'pause' => Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', $title),
				'seek' => Text::_('COM_AUDIOARCHIVE_PLAYER_SEEK'),
				'mute' => Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE'),
				'unmute' => Text::_('COM_AUDIOARCHIVE_PLAYER_UNMUTE'),
				'volume' => Text::_('COM_AUDIOARCHIVE_PLAYER_VOLUME'),
				'fallback' => Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'),
				'waveformLoading' => Text::_('COM_AUDIOARCHIVE_WAVEFORM_LOADING'),
				'spectrogramLoading' => Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_LOADING'),
				'analysisView' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_VIEW'),
				'waveform' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_WAVEFORM'),
				'spectrum' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_SPECTRUM'),
			],
		],
		null,
		[
			'component' => 'com_audioarchive',
			'client' => 0,
		]
	);
	?>
</section>
