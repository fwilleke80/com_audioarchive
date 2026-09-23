<?php
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
\defined('_JEXEC') or die;
/** @brief Shared minimal player for Archive and owner-workspace table rows. */
$item = $displayData['item'];
$audioId = 'audioarchive-player-' . (int) $item->id;
$mime = trim((string) ($item->mime_type ?? '')) ?: 'application/octet-stream';
echo LayoutHelper::render(
	'player.unified',
	[
		'audioId' => $audioId,
		'clipId' => (int) $item->id,
		'title' => (string) $item->title,
		'streamUrl' => (string) $item->stream_url,
		'waveformUrl' => '',
		'mime' => $mime,
		'params' => $displayData['params'],
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
