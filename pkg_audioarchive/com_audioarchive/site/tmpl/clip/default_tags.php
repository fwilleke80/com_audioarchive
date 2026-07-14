<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('detail_show_tags', 1) !== 1 || !$this->item->tags)
{
	return;
}
?>
<section class="com-audioarchive-info-card com-audioarchive-detail-tags" aria-labelledby="audioarchive-tags-heading">
	<h2 id="audioarchive-tags-heading"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></h2>
	<ul class="com-audioarchive-tag-list com-audioarchive-tag-list--linked">
		<?php foreach ($this->item->tags as $tag) : ?>
			<?php $tagDescription = trim((string) ($tag->description_text ?? '')); ?>
			<li>
				<a href="<?php echo $this->getTagUrl($tag); ?>"<?php if ($tagDescription !== '') : ?> title="<?php echo $this->escape($tagDescription); ?>"<?php endif; ?>>
					<?php echo $this->escape((string) $tag->title); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
