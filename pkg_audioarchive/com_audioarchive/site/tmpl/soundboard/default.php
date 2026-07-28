<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$keys = array_merge(range(1, 9), [0], range('A', 'Z'));
$headerText = trim((string) $this->params->get('soundboard_header_text', ''));
?>
<div
	class="com-audioarchive com-audioarchive-soundboard"
	data-audioarchive-soundboard
	data-audioarchive-pad-count="<?php echo $this->padCount; ?>"
	data-audioarchive-stream-template="<?php echo $this->escape($this->streamTemplate); ?>"
	data-audioarchive-routes-url="<?php echo $this->escape($this->routesUrl); ?>"
	data-audioarchive-canonical-url="<?php echo $this->escape($this->canonicalUrl); ?>"
	data-audioarchive-label-empty="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_EMPTY_PAD')); ?>"
	data-audioarchive-label-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_PLAYING')); ?>"
	data-audioarchive-label-copied="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED')); ?>"
	data-audioarchive-label-imported="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_IMPORTED')); ?>"
	data-audioarchive-label-invalid="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_INVALID_FILE')); ?>"
	data-audioarchive-label-shared-added="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_ADDED')); ?>"
	data-audioarchive-label-shared-full="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_FULL')); ?>"
	data-audioarchive-label-shared-replaced="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_REPLACED')); ?>"
	data-audioarchive-label-replace-confirm="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_SHARED_REPLACE_CONFIRM')); ?>"
>
	<header class="com-audioarchive-page-header">
		<?php if ((int) $this->params->get('show_page_heading', 1) === 1) : ?>
			<h1><?php echo $this->escape($this->pageHeading); ?></h1>
		<?php endif; ?>
		<?php if ($headerText !== '') : ?>
			<div class="com-audioarchive-soundboard-intro">
				<?php echo $headerText; ?>
			</div>
		<?php endif; ?>
	</header>

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

	<div class="com-audioarchive-soundboard-toolbar" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_TOOLS'); ?>">
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

	<p class="com-audioarchive-soundboard-note"><?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_STORAGE_NOTE'); ?></p>
	<audio preload="none" data-audioarchive-soundboard-audio></audio>
</div>
