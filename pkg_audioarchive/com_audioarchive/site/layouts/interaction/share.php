<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$url = (string) ($displayData['url'] ?? '');
$title = (string) ($displayData['title'] ?? '');
$includePlayerState = !empty($displayData['includePlayerState']);
?>
<div
	class="com-audioarchive-share-menu"
	data-audioarchive-share-menu
	data-share-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
	data-share-clip-id="<?php echo (int) ($displayData['clipId'] ?? 0); ?>"
	data-share-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
	<?php if ($includePlayerState) : ?>data-share-player-state="1"<?php endif; ?>
	data-copy-failed-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_COPY_FAILED'), ENT_QUOTES, 'UTF-8'); ?>"
	data-share-copied-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED'), ENT_QUOTES, 'UTF-8'); ?>"
>
	<button
		type="button"
		class="btn btn-sm btn-outline-secondary com-audioarchive-share-toggle"
		data-audioarchive-share-toggle
		aria-haspopup="menu"
		aria-expanded="false"
	>
		<span class="icon-share-alt" aria-hidden="true"></span>
		<?php echo Text::_('COM_AUDIOARCHIVE_SHARE'); ?>
	</button>
	<div class="com-audioarchive-share-popover" data-audioarchive-share-popover role="menu" hidden>
		<button type="button" class="com-audioarchive-share-option" data-audioarchive-share-copy role="menuitem">
			<span class="icon-link" aria-hidden="true"></span>
			<span data-audioarchive-share-copy-text><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></span>
		</button>
		<button
			type="button"
			class="com-audioarchive-share-option"
			data-audioarchive-share-native
			role="menuitem"
			data-share-unavailable-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER_UNAVAILABLE'), ENT_QUOTES, 'UTF-8'); ?>"
		>
			<span class="icon-share-alt" aria-hidden="true"></span>
			<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER'); ?></span>
		</button>
	</div>
	<dialog class="com-audioarchive-copy-dialog" data-share-dialog aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?>">
		<label><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?><input type="text" readonly data-share-url-field class="form-control"></label>
		<label><input type="checkbox" data-share-start> <?php echo Text::_('COM_AUDIOARCHIVE_SHARE_START'); ?> <span data-share-time></span></label>
		<div><button type="button" class="btn btn-primary" data-share-confirm><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></button>
		<button type="button" class="btn btn-secondary" data-share-close><?php echo Text::_('JCLOSE'); ?></button></div>
		<p data-share-feedback role="status"></p>
	</dialog>
	<span class="visually-hidden" aria-live="polite" data-audioarchive-share-status></span>
</div>
