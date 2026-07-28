<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$clipId = (int) ($displayData['clipId'] ?? 0);
$title = (string) ($displayData['title'] ?? '');
?>
<button
	type="button"
	class="btn btn-sm btn-outline-secondary com-audioarchive-soundboard-add"
	data-audioarchive-soundboard-add
	data-clip-id="<?php echo $clipId; ?>"
	data-clip-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
>
	<span aria-hidden="true">▦</span>
	<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_ADD'); ?>
</button>
