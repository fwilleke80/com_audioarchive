<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;
?>
<article class="com-audioarchive com-audioarchive-clip">
	<header class="com-audioarchive-clip-header">
		<h1><?php echo $this->escape($this->item->title); ?></h1>
	</header>

	<?php echo $this->loadTemplate('player'); ?>

	<?php if (trim((string) $this->item->description) !== '') : ?>
		<div class="com-audioarchive-description">
			<?php echo HTMLHelper::_('content.prepare', (string) $this->item->description, '', 'com_audioarchive.clip'); ?>
		</div>
	<?php endif; ?>

	<?php echo $this->loadTemplate('metadata'); ?>
	<?php echo $this->loadTemplate('tags'); ?>
	<?php echo $this->loadTemplate('download'); ?>

	<p class="com-audioarchive-back-link">
		<a href="<?php echo $this->archiveUrl; ?>">← <?php echo Text::_('COM_AUDIOARCHIVE_BACK_TO_ARCHIVE'); ?></a>
	</p>
</article>
