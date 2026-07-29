<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

$showShare = (int) $this->params->get('detail_show_share', 1) === 1;
$showSoundboard = (int) $this->params->get('enable_soundboard', 1) === 1;
$showPlaylists = (int) $this->params->get('enable_playlists', 1) === 1;

if (!$showShare && !$showSoundboard && !$showPlaylists)
{
	return;
}
?>
<section class="com-audioarchive-info-card com-audioarchive-detail-actions" aria-labelledby="audioarchive-actions-heading">
	<h2 id="audioarchive-actions-heading"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_ACTIONS'); ?></h2>
	<div class="com-audioarchive-detail-actions-toolbar" role="group" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_ACTIONS'); ?>">
		<?php if ($showShare) : ?>
			<?php echo LayoutHelper::render('interaction.share', ['url' => $this->shareUrl, 'title' => (string) $this->item->title], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
		<?php endif; ?>

		<?php if ($showSoundboard) : ?>
			<?php echo LayoutHelper::render('interaction.soundboard_add', ['clipId' => (int) $this->item->id, 'title' => (string) $this->item->title], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
		<?php endif; ?>

		<?php if ($showPlaylists) : ?>
			<?php echo LayoutHelper::render('interaction.playlist_add', ['clipId' => (int) $this->item->id, 'clipUuid' => (string) ($this->item->uuid ?? ''), 'title' => (string) $this->item->title], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
		<?php endif; ?>
	</div>
</section>
