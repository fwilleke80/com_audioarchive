<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

$showShare = (int) $this->params->get('detail_show_share', 1) === 1;
$showSoundboard = (int) $this->params->get('enable_soundboard', 1) === 1;

if (!$showShare && !$showSoundboard)
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
			<?php if ($this->soundboardUrl !== '') : ?>
				<a class="btn btn-sm btn-outline-secondary com-audioarchive-soundboard-open" href="<?php echo $this->escape($this->soundboardUrl); ?>">
					<span class="icon-grid-2" aria-hidden="true"></span>
					<?php echo Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_OPEN'); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
