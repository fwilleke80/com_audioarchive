<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$mime = trim((string) $this->item->mime_type) ?: 'application/octet-stream';
?>
<section class="com-audioarchive-detail-player" aria-labelledby="audioarchive-player-heading">
	<div class="com-audioarchive-player-symbol" aria-hidden="true">♪</div>
	<div class="com-audioarchive-player-content">
		<h2 id="audioarchive-player-heading" class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_HEADING'); ?></h2>
		<p class="com-audioarchive-player-title"><?php echo Text::_('COM_AUDIOARCHIVE_LISTEN'); ?></p>
		<audio controls preload="metadata" data-audioarchive-native-player data-clip-id="<?php echo (int) $this->item->id; ?>" data-clip-title="<?php echo $this->escape((string) $this->item->title); ?>">
			<source src="<?php echo $this->streamUrl; ?>" type="<?php echo $this->escape($mime); ?>">
			<?php echo Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'); ?>
		</audio>
	</div>
</section>
