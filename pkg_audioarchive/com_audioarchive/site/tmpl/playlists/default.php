<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Punga\Component\Audioarchive\Site\Helper\StyleHelper;

\defined('_JEXEC') or die;

$introText = trim((string) $this->params->get('playlist_header_text', ''));
$audioId = 'audioarchive-playlist-player';
$playlistStyle = StyleHelper::buildPlaylistListVariables($this->params);
?>
<div
	class="com-audioarchive com-audioarchive-playlists"
	<?php if ($playlistStyle !== '') : ?>style="<?php echo $this->escape($playlistStyle); ?>"<?php endif; ?>
	data-audioarchive-playlists
	data-audioarchive-return-origin
	data-audioarchive-return-title="<?php echo $this->escape($this->returnTitle); ?>"
	data-audioarchive-items-url="<?php echo $this->escape($this->itemsUrl); ?>"
	data-audioarchive-interaction-url="<?php echo $this->escape($this->interactionUrl); ?>"
	data-audioarchive-interaction-token="<?php echo $this->escape($this->interactionToken); ?>"
	data-audioarchive-canonical-url="<?php echo $this->escape($this->canonicalUrl); ?>"
	data-audioarchive-soundboard-enabled="<?php echo $this->soundboardEnabled ? '1' : '0'; ?>"
	data-audioarchive-soundboard-pad-count="<?php echo $this->soundboardPadCount; ?>"
	data-audioarchive-soundboard-full-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_FULL')); ?>"
	<?php if ($this->playCountUrl !== '') : ?>
		data-audioarchive-play-count-url="<?php echo $this->escape($this->playCountUrl); ?>"
		data-audioarchive-token-name="<?php echo $this->escape($this->playCountToken); ?>"
	<?php endif; ?>
	data-audioarchive-label-default-name="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_DEFAULT_NAME')); ?>"
	data-audioarchive-label-name-prompt="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_NAME_PROMPT')); ?>"
	data-audioarchive-label-rename-prompt="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_RENAME_PROMPT')); ?>"
	data-audioarchive-label-delete-confirm="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_DELETE_CONFIRM')); ?>"
	data-audioarchive-label-empty="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_EMPTY')); ?>"
	data-audioarchive-label-unavailable="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_CLIP_UNAVAILABLE')); ?>"
	data-audioarchive-label-copied="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED')); ?>"
	data-audioarchive-label-invalid="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_INVALID_FILE')); ?>"
	data-audioarchive-label-imported="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_IMPORTED')); ?>"
	data-audioarchive-label-saved-shared="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_SHARED_SAVED')); ?>"
	data-audioarchive-label-position="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_POSITION')); ?>"
	data-audioarchive-label-title="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE')); ?>"
	data-audioarchive-label-play="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_PLAY_CLIP')); ?>"
	data-audioarchive-label-pause="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_PAUSE')); ?>"
	data-audioarchive-label-remove="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_REMOVE_CLIP')); ?>"
	data-audioarchive-label-move-up="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_MOVE_UP')); ?>"
	data-audioarchive-label-move-down="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_MOVE_DOWN')); ?>"
	data-audioarchive-label-share="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE')); ?>"
	data-audioarchive-label-copy-link="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK')); ?>"
	data-audioarchive-label-browser-share="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER')); ?>"
	data-audioarchive-label-browser-unavailable="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER_UNAVAILABLE')); ?>"
	data-audioarchive-label-add-soundboard="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_ADD')); ?>"
	data-audioarchive-status-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PLAYING')); ?>"
	data-audioarchive-status-paused="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PAUSED')); ?>"
	data-audioarchive-status-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_ERROR')); ?>"
>
	<header class="com-audioarchive-page-header">
		<?php if ((int) $this->params->get('show_page_heading', 1) === 1) : ?>
			<h1><?php echo $this->escape($this->pageHeading); ?></h1>
		<?php endif; ?>
	</header>

	<?php if ($introText !== '') : ?>
		<div class="com-audioarchive-intro">
			<?php echo $introText; ?>
		</div>
	<?php endif; ?>

	<p class="visually-hidden" aria-live="polite" data-audioarchive-playlist-status data-audioarchive-status></p>

	<section class="com-audioarchive-playlist-shared" data-audioarchive-playlist-shared hidden>
		<div>
			<span class="icon-info-circle" aria-hidden="true"></span>
			<p><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_SHARED_NOTICE'); ?></p>
		</div>
		<button type="button" class="btn btn-primary" data-audioarchive-playlist-save-shared>
			<span class="icon-save" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_SAVE_SHARED'); ?>
		</button>
	</section>

	<div class="com-audioarchive-playlist-manager">
		<div class="com-audioarchive-playlist-selector">
			<label for="audioarchive-playlist-select"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_SELECT'); ?></label>
			<select id="audioarchive-playlist-select" class="form-select" data-audioarchive-playlist-select></select>
		</div>
		<div class="com-audioarchive-playlist-manager-actions" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_MANAGE'); ?>">
			<button type="button" class="btn btn-outline-secondary" data-audioarchive-playlist-create>
				<span class="icon-plus" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_CREATE'); ?>
			</button>
			<button type="button" class="btn btn-outline-secondary" data-audioarchive-playlist-rename>
				<span class="icon-edit" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_RENAME'); ?>
			</button>
			<button type="button" class="btn btn-outline-danger" data-audioarchive-playlist-delete>
				<span class="icon-trash" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_DELETE'); ?>
			</button>
		</div>
	</div>

	<section class="com-audioarchive-playlist-player" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_PLAYER'); ?>">
		<?php
		echo LayoutHelper::render(
			'player.unified',
			[
				'audioId' => $audioId,
				'clipId' => 0,
				'title' => Text::_('COM_AUDIOARCHIVE_PLAYLIST_NOTHING_SELECTED'),
				'streamUrl' => '',
				'mime' => 'application/octet-stream',
				'params' => $this->params,
				'presentation' => 'playlist',
				'labels' => [
					'play' => Text::_('COM_AUDIOARCHIVE_PLAYER_PLAY'),
					'pause' => Text::_('COM_AUDIOARCHIVE_PLAYER_PAUSE'),
					'seek' => Text::_('COM_AUDIOARCHIVE_PLAYER_SEEK'),
					'mute' => Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE'),
					'unmute' => Text::_('COM_AUDIOARCHIVE_PLAYER_UNMUTE'),
					'fallback' => Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'),
					'previous' => Text::_('COM_AUDIOARCHIVE_PLAYLIST_PREVIOUS'),
					'next' => Text::_('COM_AUDIOARCHIVE_PLAYLIST_NEXT'),
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

	<template data-audioarchive-playlist-row-player-template>
		<?php
		echo LayoutHelper::render(
			'player.unified',
			[
				'audioId' => 'audioarchive-playlist-row-player-template',
				'clipId' => 0,
				'title' => '',
				'streamUrl' => '',
				'mime' => 'application/octet-stream',
				'params' => $this->params,
				'presentation' => 'minimal',
				'labels' => [
					'play' => Text::_('COM_AUDIOARCHIVE_PLAYLIST_PLAY_CLIP'),
					'pause' => Text::_('COM_AUDIOARCHIVE_PLAYER_PAUSE'),
					'fallback' => Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'),
				],
			],
			null,
			[
				'component' => 'com_audioarchive',
				'client' => 0,
			]
		);
		?>
	</template>

	<div class="com-audioarchive-playlist-empty" data-audioarchive-playlist-empty hidden>
		<span aria-hidden="true">♪</span>
		<p><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_EMPTY'); ?></p>
	</div>

	<div class="com-audioarchive-table-wrapper com-audioarchive-playlist-table-wrapper" data-audioarchive-playlist-table-wrapper>
		<table class="com-audioarchive-table com-audioarchive-playlist-table">
			<thead>
				<tr>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_ORDER'); ?></th>
					<th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_PLAY'); ?></span></th>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TITLE'); ?></th>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_DURATION'); ?></th>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></th>
					<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_ACTIONS'); ?></th>
				</tr>
			</thead>
			<tbody data-audioarchive-playlist-items></tbody>
		</table>
	</div>

	<div class="com-audioarchive-playlist-toolbar" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_TOOLS'); ?>">
		<button type="button" class="btn btn-outline-secondary" data-audioarchive-playlist-export>
			<span class="icon-download" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_EXPORT'); ?>
		</button>
		<button type="button" class="btn btn-outline-secondary" data-audioarchive-playlist-import>
			<span class="icon-upload" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_IMPORT'); ?>
		</button>
		<input class="visually-hidden" type="file" accept="application/json,.json" data-audioarchive-playlist-file>
		<div class="com-audioarchive-share-menu" data-audioarchive-playlist-share-menu>
			<button
				type="button"
				class="btn btn-outline-secondary com-audioarchive-share-toggle"
				data-audioarchive-playlist-share-toggle
				aria-haspopup="menu"
				aria-expanded="false"
			>
				<span class="icon-share-alt" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_SHARE'); ?>
			</button>
			<div class="com-audioarchive-share-popover" data-audioarchive-playlist-share-popover role="menu" hidden>
				<button type="button" class="com-audioarchive-share-option" data-audioarchive-playlist-share-copy role="menuitem">
					<span class="icon-link" aria-hidden="true"></span>
					<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></span>
				</button>
				<button
					type="button"
					class="com-audioarchive-share-option"
					data-audioarchive-playlist-share-native
					role="menuitem"
				>
					<span class="icon-share-alt" aria-hidden="true"></span>
					<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER'); ?></span>
				</button>
			</div>
		</div>
	</div>

	<p class="com-audioarchive-playlist-storage-note"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_STORAGE_NOTE'); ?></p>
</div>
