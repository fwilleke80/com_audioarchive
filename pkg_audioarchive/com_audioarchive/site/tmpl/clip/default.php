<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$hasDescription = trim((string) $this->item->description) !== '';
?>
<article
	class="com-audioarchive com-audioarchive-clip"
	data-audioarchive-status-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PLAYING')); ?>"
	data-audioarchive-status-paused="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PAUSED')); ?>"
	data-audioarchive-status-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_ERROR')); ?>"
>
	<div class="visually-hidden" aria-live="polite" aria-atomic="true" data-audioarchive-status></div>

	<a class="com-audioarchive-back-link" href="<?php echo $this->archiveUrl; ?>">← <?php echo Text::_('COM_AUDIOARCHIVE_BACK_TO_ARCHIVE'); ?></a>

	<header class="com-audioarchive-clip-header">
		<?php if ((int) $this->params->get('detail_show_category', 1) === 1 && trim((string) $this->item->category_title) !== '') : ?>
			<p class="com-audioarchive-clip-kicker"><?php echo $this->escape((string) $this->item->category_title); ?></p>
		<?php endif; ?>
		<h1><?php echo $this->escape($this->item->title); ?></h1>
	</header>

	<?php echo $this->loadTemplate('player'); ?>

	<div class="com-audioarchive-clip-layout <?php echo $hasDescription ? 'has-description' : 'no-description'; ?>">
		<?php if ($hasDescription) : ?>
			<div class="com-audioarchive-clip-main">
				<section class="com-audioarchive-description" aria-labelledby="audioarchive-description-heading">
					<h2 id="audioarchive-description-heading"><?php echo Text::_('COM_AUDIOARCHIVE_DESCRIPTION_HEADING'); ?></h2>
					<div class="com-audioarchive-prose">
						<?php echo HTMLHelper::_('content.prepare', (string) $this->item->description, '', 'com_audioarchive.clip'); ?>
					</div>
				</section>
			</div>
		<?php endif; ?>

		<aside class="com-audioarchive-clip-sidebar" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_CLIP_INFORMATION'); ?>">
			<?php echo $this->loadTemplate('metadata'); ?>
			<?php echo $this->loadTemplate('tags'); ?>
			<?php echo $this->loadTemplate('download'); ?>
		</aside>
	</div>
</article>
