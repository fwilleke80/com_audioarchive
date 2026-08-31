<?php

use Joomla\CMS\Language\Text;
use Punga\Component\Audioarchive\Site\Helper\StyleHelper;

\defined('_JEXEC') or die;

$keys = array_merge(range(1, 9), [0], range('A', 'Z'));
$samplerWhiteKeys = [
	['offset' => 0, 'key' => 'A'],
	['offset' => 2, 'key' => 'S'],
	['offset' => 4, 'key' => 'D'],
	['offset' => 5, 'key' => 'F'],
	['offset' => 7, 'key' => 'G'],
	['offset' => 9, 'key' => 'H'],
	['offset' => 11, 'key' => 'J'],
	['offset' => 12, 'key' => 'K'],
	['offset' => 14, 'key' => 'L'],
];
$samplerBlackKeys = [
	['offset' => 1, 'key' => 'W', 'position' => '11.111%'],
	['offset' => 3, 'key' => 'E', 'position' => '22.222%'],
	['offset' => 6, 'key' => 'T', 'position' => '44.444%'],
	['offset' => 8, 'key' => 'Z', 'position' => '55.556%'],
	['offset' => 10, 'key' => 'U', 'position' => '66.667%'],
	['offset' => 13, 'key' => 'O', 'position' => '88.889%'],
	['offset' => 15, 'key' => 'P', 'position' => '97%'],
];
$introText = trim((string) $this->params->get('soundboard_header_text', ''));
$soundboardStyle = StyleHelper::buildSoundboardVariables($this->params);
?>
<div
	class="com-audioarchive com-audioarchive-soundboard"
	<?php if ($soundboardStyle !== '') : ?>style="<?php echo $this->escape($soundboardStyle); ?>"<?php endif; ?>
	data-audioarchive-soundboard
	data-audioarchive-return-origin
	data-audioarchive-return-title="<?php echo $this->escape($this->returnTitle); ?>"
	data-audioarchive-pad-count="<?php echo $this->padCount; ?>"
	data-audioarchive-polyphonic="<?php echo $this->polyphonic ? '1' : '0'; ?>"
	data-audioarchive-sampler-enabled="<?php echo $this->samplerEnabled ? '1' : '0'; ?>"
	data-audioarchive-record-soundboard-plays="<?php echo (int) $this->params->get('soundboard_record_plays', 1) === 1 ? '1' : '0'; ?>"
	data-audioarchive-stream-template="<?php echo $this->escape($this->streamTemplate); ?>"
	<?php if ($this->playCountUrl !== '') : ?>
		data-audioarchive-play-count-url="<?php echo $this->escape($this->playCountUrl); ?>"
		data-audioarchive-token-name="<?php echo $this->escape($this->playCountToken); ?>"
	<?php endif; ?>
	data-audioarchive-routes-url="<?php echo $this->escape($this->routesUrl); ?>"
	data-audioarchive-interaction-url="<?php echo $this->escape($this->interactionUrl); ?>"
	data-audioarchive-interaction-token="<?php echo $this->escape($this->interactionToken); ?>"
	data-audioarchive-canonical-url="<?php echo $this->escape($this->canonicalUrl); ?>"
	data-audioarchive-label-empty="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_EMPTY_PAD')); ?>"
	data-audioarchive-label-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYING')); ?>"
	data-audioarchive-label-play-pad="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAY_PAD')); ?>"
	data-audioarchive-label-sampler-select-pad="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_SELECT_PAD')); ?>"
	data-audioarchive-label-copied="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED')); ?>"
	data-audioarchive-label-imported="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_IMPORTED')); ?>"
	data-audioarchive-label-invalid="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_INVALID_FILE')); ?>"
	data-audioarchive-label-shared-added="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_ADDED')); ?>"
	data-audioarchive-label-shared-full="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_FULL')); ?>"
	data-audioarchive-label-shared-replaced="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_REPLACED')); ?>"
	data-audioarchive-label-replace-confirm="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_REPLACE_CONFIRM')); ?>"
	data-audioarchive-label-sampler-prompt="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_PROMPT')); ?>"
	data-audioarchive-label-sampler-loading="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_LOADING')); ?>"
	data-audioarchive-label-sampler-ready="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_READY')); ?>"
	data-audioarchive-label-sampler-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_ERROR')); ?>"
	data-audioarchive-label-keyboard-show="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_SHOW')); ?>"
	data-audioarchive-label-keyboard-hide="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_HIDE')); ?>"
	data-audioarchive-label-keyboard-octave="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE')); ?>"
	data-audioarchive-label-midi-unavailable="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_UNAVAILABLE')); ?>"
	data-audioarchive-label-midi-insecure="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_INSECURE')); ?>"
	data-audioarchive-label-midi-denied="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_DENIED')); ?>"
	data-audioarchive-label-midi-no-inputs="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_NO_INPUTS')); ?>"
	data-audioarchive-label-midi-inputs="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_INPUTS')); ?>"
	data-audioarchive-label-midi-enabled="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_ENABLED')); ?>"
	data-audioarchive-label-playlist-default-name="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYLIST_DEFAULT_NAME')); ?>"
	data-audioarchive-label-playlist-name-prompt="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYLIST_NAME_PROMPT')); ?>"
	data-audioarchive-label-playlist-empty="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYLIST_EMPTY')); ?>"
	data-audioarchive-label-playlist-saved="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYLIST_SAVED')); ?>"
	data-audioarchive-label-playlist-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYLIST_ERROR')); ?>"
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

	<p class="visually-hidden" aria-live="polite" data-audioarchive-soundboard-status></p>

	<section class="com-audioarchive-soundboard-shared" data-audioarchive-soundboard-shared hidden>
		<div class="com-audioarchive-soundboard-shared-copy">
			<span class="icon-info-circle" aria-hidden="true"></span>
			<p><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_NOTICE'); ?></p>
		</div>
		<div class="com-audioarchive-soundboard-shared-actions">
			<button type="button" class="btn btn-primary" data-audioarchive-soundboard-shared-add>
				<span class="icon-plus" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_ADD'); ?>
			</button>
			<button type="button" class="btn btn-outline-danger" data-audioarchive-soundboard-shared-replace>
				<span class="icon-refresh" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_REPLACE'); ?>
			</button>
		</div>
	</section>

	<?php if ($this->samplerEnabled || $this->polyphonic) : ?>
	<div class="com-audioarchive-soundboard-mode" data-audioarchive-soundboard-mode>
		<span class="com-audioarchive-soundboard-mode-label"><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MODE_LABEL'); ?></span>
		<div class="com-audioarchive-soundboard-mode-controls">
			<?php if ($this->samplerEnabled) : ?>
			<div class="com-audioarchive-soundboard-mode-options" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MODE_LABEL'); ?>">
				<button
					type="button"
					class="com-audioarchive-soundboard-mode-button is-active"
					data-audioarchive-soundboard-mode-pad
					aria-pressed="true"
				>
					<span class="icon-grid-view" aria-hidden="true"></span>
					<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MODE_PAD'); ?>
				</button>
				<button
					type="button"
					class="com-audioarchive-soundboard-mode-button"
					data-audioarchive-soundboard-mode-sampler
					aria-pressed="false"
				>
					<span class="icon-music" aria-hidden="true"></span>
					<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MODE_SAMPLER'); ?>
				</button>
			</div>
			<?php endif; ?>

			<?php if ($this->polyphonic) : ?>
			<label
				class="com-audioarchive-soundboard-polyphony"
				title="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_POLYPHONY_DESC')); ?>"
			>
				<input
					class="com-audioarchive-soundboard-polyphony-input"
					type="checkbox"
					role="switch"
					data-audioarchive-soundboard-polyphony
					checked
				>
				<span class="com-audioarchive-soundboard-polyphony-track" aria-hidden="true"></span>
				<span class="com-audioarchive-soundboard-polyphony-label"><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_POLYPHONY'); ?></span>
			</label>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="com-audioarchive-soundboard-grid">
		<?php for ($index = 0; $index < $this->padCount; $index++) : ?>
			<?php $key = (string) ($keys[$index] ?? ($index + 1)); ?>
			<div class="com-audioarchive-soundboard-pad" data-audioarchive-soundboard-pad data-index="<?php echo $index; ?>">
				<button
					type="button"
					class="com-audioarchive-soundboard-trigger"
					data-audioarchive-soundboard-trigger
					aria-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_SOUNDBOARD_PLAY_PAD', $index + 1); ?>"
				>
					<span class="com-audioarchive-soundboard-key" aria-hidden="true"><?php echo $this->escape($key); ?></span>
					<span class="com-audioarchive-soundboard-title" data-audioarchive-soundboard-title><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_EMPTY_PAD'); ?></span>
				</button>
				<a
					class="com-audioarchive-soundboard-detail"
					data-audioarchive-soundboard-detail
					data-audioarchive-detail-link
					href="#"
					aria-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_SOUNDBOARD_DETAIL_PAD', $index + 1); ?>"
					title="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_DETAIL'); ?>"
					hidden
				>
					<span class="icon-eye" aria-hidden="true"></span>
				</a>
				<button
					type="button"
					class="com-audioarchive-soundboard-remove"
					data-audioarchive-soundboard-remove
					aria-label="<?php echo Text::sprintf('COM_AUDIOARCHIVE_SOUNDBOARD_REMOVE_PAD', $index + 1); ?>"
					title="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_REMOVE'); ?>"
				>
					<span aria-hidden="true">×</span>
				</button>
			</div>
		<?php endfor; ?>
	</div>

	<?php if ($this->samplerEnabled) : ?>
	<section class="com-audioarchive-soundboard-sampler" data-audioarchive-soundboard-sampler hidden>
		<div class="com-audioarchive-soundboard-sampler-header">
			<div>
				<h2><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_TITLE'); ?></h2>
				<p data-audioarchive-soundboard-sampler-description><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAMPLER_PROMPT'); ?></p>
			</div>
			<div class="com-audioarchive-soundboard-sampler-actions">
				<button type="button" class="btn btn-outline-secondary" data-audioarchive-soundboard-midi-enable>
					<span class="icon-plug" aria-hidden="true"></span>
					<span data-audioarchive-soundboard-midi-enable-label><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_MIDI_ENABLE'); ?></span>
				</button>
				<button
					type="button"
					class="btn btn-outline-secondary"
					data-audioarchive-soundboard-keyboard-toggle
					aria-expanded="false"
				>
					<span class="icon-keyboard" aria-hidden="true"></span>
					<span data-audioarchive-soundboard-keyboard-toggle-label><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_SHOW'); ?></span>
				</button>
			</div>
		</div>

		<p class="com-audioarchive-soundboard-midi-status" data-audioarchive-soundboard-midi-status aria-live="polite"></p>

		<div class="com-audioarchive-soundboard-keyboard" data-audioarchive-soundboard-keyboard hidden>
			<div class="com-audioarchive-soundboard-keyboard-toolbar">
				<button
					type="button"
					class="btn btn-sm btn-outline-secondary"
					data-audioarchive-soundboard-octave-down
					aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE_DOWN'); ?>"
					title="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE_DOWN'); ?>"
				>
					<span aria-hidden="true">Y</span>
					<span class="icon-chevron-left" aria-hidden="true"></span>
				</button>
				<strong data-audioarchive-soundboard-octave-label><?php echo Text::sprintf('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE', 4); ?></strong>
				<button
					type="button"
					class="btn btn-sm btn-outline-secondary"
					data-audioarchive-soundboard-octave-up
					aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE_UP'); ?>"
					title="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_OCTAVE_UP'); ?>"
				>
					<span class="icon-chevron-right" aria-hidden="true"></span>
					<span aria-hidden="true">X</span>
				</button>
			</div>

			<div class="com-audioarchive-soundboard-piano" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_ARIA_LABEL'); ?>">
				<div class="com-audioarchive-soundboard-white-keys">
					<?php foreach ($samplerWhiteKeys as $samplerKey) : ?>
						<button
							type="button"
							class="com-audioarchive-soundboard-piano-key is-white"
							data-audioarchive-soundboard-piano-key
							data-note-offset="<?php echo (int) $samplerKey['offset']; ?>"
							data-computer-key="<?php echo $this->escape($samplerKey['key']); ?>"
						>
							<span class="com-audioarchive-soundboard-piano-note" data-audioarchive-soundboard-piano-note></span>
							<span class="com-audioarchive-soundboard-piano-shortcut"><?php echo $this->escape($samplerKey['key']); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<?php foreach ($samplerBlackKeys as $samplerKey) : ?>
					<button
						type="button"
						class="com-audioarchive-soundboard-piano-key is-black"
						data-audioarchive-soundboard-piano-key
						data-note-offset="<?php echo (int) $samplerKey['offset']; ?>"
						data-computer-key="<?php echo $this->escape($samplerKey['key']); ?>"
						style="--audioarchive-piano-key-position: <?php echo $this->escape($samplerKey['position']); ?>;"
					>
						<span class="com-audioarchive-soundboard-piano-note" data-audioarchive-soundboard-piano-note></span>
						<span class="com-audioarchive-soundboard-piano-shortcut"><?php echo $this->escape($samplerKey['key']); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="com-audioarchive-soundboard-keyboard-hint"><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_KEYBOARD_HINT'); ?></p>
		</div>
	</section>
	<?php endif; ?>

	<div class="com-audioarchive-soundboard-toolbar" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_TOOLS'); ?>">
		<?php if ($this->playlistsEnabled) : ?>
		<button type="button" class="btn btn-outline-secondary" data-audioarchive-soundboard-save-playlist>
			<span class="icon-list" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SAVE_PLAYLIST'); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="btn btn-outline-secondary" data-audioarchive-soundboard-export>
			<span class="icon-download" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_EXPORT'); ?>
		</button>
		<button type="button" class="btn btn-outline-secondary" data-audioarchive-soundboard-import>
			<span class="icon-upload" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_IMPORT'); ?>
		</button>
		<input class="visually-hidden" type="file" accept="application/json,.json" data-audioarchive-soundboard-file>
		<div class="com-audioarchive-share-menu com-audioarchive-soundboard-share-menu" data-audioarchive-soundboard-share-menu>
			<button
				type="button"
				class="btn btn-outline-secondary com-audioarchive-share-toggle"
				data-audioarchive-soundboard-share-toggle
				aria-haspopup="menu"
				aria-expanded="false"
			>
				<span class="icon-share-alt" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARE'); ?>
			</button>
			<div class="com-audioarchive-share-popover" data-audioarchive-soundboard-share-popover role="menu" hidden>
				<button type="button" class="com-audioarchive-share-option" data-audioarchive-soundboard-share-copy role="menuitem">
					<span class="icon-link" aria-hidden="true"></span>
					<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></span>
				</button>
				<button
					type="button"
					class="com-audioarchive-share-option"
					data-audioarchive-soundboard-share-native
					role="menuitem"
					data-share-unavailable-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER_UNAVAILABLE'), ENT_QUOTES, 'UTF-8'); ?>"
				>
					<span class="icon-share-alt" aria-hidden="true"></span>
					<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER'); ?></span>
				</button>
			</div>
		</div>
		<button type="button" class="btn btn-outline-danger" data-audioarchive-soundboard-clear>
			<span class="icon-trash" aria-hidden="true"></span>
			<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_CLEAR'); ?>
		</button>
	</div>

	<p class="com-audioarchive-conversion-status" data-audioarchive-soundboard-conversion-status aria-live="polite"></p>

	<p class="com-audioarchive-soundboard-note"><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_STORAGE_NOTE'); ?></p>
</div>
