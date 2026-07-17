<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('detail_show_navigation', 1) !== 1
	|| ($this->previousClip === null && $this->nextClip === null))
{
	return;
}
?>
<nav class="com-audioarchive-clip-navigation" aria-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_CLIP_NAVIGATION_LABEL')); ?>">
	<?php if ($this->previousClip !== null && $this->previousUrl !== '') : ?>
		<a class="com-audioarchive-clip-navigation-link is-previous" href="<?php echo $this->escape($this->previousUrl); ?>" rel="prev">
			<span class="com-audioarchive-clip-navigation-direction">← <?php echo Text::_('COM_AUDIOARCHIVE_PREVIOUS_CLIP'); ?></span>
			<span class="com-audioarchive-clip-navigation-title"><?php echo $this->escape((string) $this->previousClip->title); ?></span>
		</a>
	<?php endif; ?>

	<?php if ($this->nextClip !== null && $this->nextUrl !== '') : ?>
		<a class="com-audioarchive-clip-navigation-link is-next" href="<?php echo $this->escape($this->nextUrl); ?>" rel="next">
			<span class="com-audioarchive-clip-navigation-direction"><?php echo Text::_('COM_AUDIOARCHIVE_NEXT_CLIP'); ?> →</span>
			<span class="com-audioarchive-clip-navigation-title"><?php echo $this->escape((string) $this->nextClip->title); ?></span>
		</a>
	<?php endif; ?>
</nav>
