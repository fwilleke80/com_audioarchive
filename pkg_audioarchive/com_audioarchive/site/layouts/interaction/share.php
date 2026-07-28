<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$url = (string) ($displayData['url'] ?? '');
$title = (string) ($displayData['title'] ?? '');
?>
<div
	class="com-audioarchive-share-menu"
	data-audioarchive-share-menu
	data-share-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
	data-share-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
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
	<span class="visually-hidden" aria-live="polite" data-audioarchive-share-status></span>
</div>
