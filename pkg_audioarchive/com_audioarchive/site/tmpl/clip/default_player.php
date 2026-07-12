<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$mime = trim((string) $this->item->mime_type) ?: 'application/octet-stream';
?>
<div class="com-audioarchive-detail-player">
	<audio controls preload="metadata">
		<source src="<?php echo $this->streamUrl; ?>" type="<?php echo $this->escape($mime); ?>">
		<?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'); ?>
	</audio>
</div>
