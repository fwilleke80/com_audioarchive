<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

$hasDescription = trim((string) $this->item->description) !== '';
?>
<article
	class="com-audioarchive com-audioarchive-clip"
	data-audioarchive-clip-return
	data-audioarchive-return-label-template="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_BACK_TO_MENU_ITEM')); ?>"
	data-audioarchive-status-playing="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PLAYING')); ?>"
	data-audioarchive-status-paused="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_PAUSED')); ?>"
	data-audioarchive-status-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PLAYER_STATUS_ERROR')); ?>"
	data-audioarchive-soundboard-pad-count="<?php echo max(4, min(36, (int) $this->params->get('soundboard_pad_count', 12))); ?>"
	data-audioarchive-soundboard-full-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_FULL')); ?>"
	data-audioarchive-share-copied-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED')); ?>"
	<?php if ($this->playCountUrl !== '') : ?>
		data-audioarchive-play-count-url="<?php echo $this->escape($this->playCountUrl); ?>"
		data-audioarchive-token-name="<?php echo $this->escape($this->playCountToken); ?>"
	<?php endif; ?>
	<?php if ($this->ratingUrl !== '') : ?>
		data-audioarchive-rating-endpoint="<?php echo $this->escape($this->ratingUrl); ?>"
		data-audioarchive-rating-token="<?php echo $this->escape($this->ratingToken); ?>"
		data-audioarchive-rating-success="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_RATING_SAVED')); ?>"
		data-audioarchive-rating-error="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_RATING_ERROR')); ?>"
	<?php endif; ?>
>
	<div class="visually-hidden" aria-live="polite" aria-atomic="true" data-audioarchive-status></div>

	<div class="com-audioarchive-clip-actions">
		<a class="com-audioarchive-back-link" data-audioarchive-return-link href="<?php echo $this->escape($this->backUrl); ?>">
			<span aria-hidden="true">←</span>
			<span data-audioarchive-return-label><?php echo $this->escape($this->backLabel); ?></span>
		</a>

		<?php if ($this->canEdit && $this->editUrl !== '') : ?>
			<a class="btn btn-sm btn-outline-secondary com-audioarchive-edit-link" href="<?php echo $this->escape($this->editUrl); ?>">
				<span class="icon-edit" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_EDIT_CLIP'); ?>
			</a>
		<?php endif; ?>
	</div>

	<header class="com-audioarchive-clip-header">
		<?php if ((int) $this->params->get('detail_show_category', 1) === 1 && trim((string) $this->item->category_title) !== '') : ?>
			<p class="com-audioarchive-clip-kicker"><?php echo $this->escape((string) $this->item->category_title); ?></p>
		<?php endif; ?>
		<h1><?php echo $this->escape($this->item->title); ?></h1>
	</header>

	<?php echo $this->loadTemplate('player'); ?>

	<?php if ((int) $this->params->get('detail_show_rating', 1) === 1 && (int) $this->params->get('enable_ratings', 1) === 1) : ?>
		<div class="com-audioarchive-detail-rating">
			<?php echo LayoutHelper::render('interaction.rating', ['clipId' => (int) $this->item->id, 'title' => (string) $this->item->title, 'up' => (int) ($this->item->rating_up ?? 0), 'down' => (int) ($this->item->rating_down ?? 0), 'canVote' => $this->canRate], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
		</div>
	<?php endif; ?>


	<div class="com-audioarchive-clip-layout <?php echo $hasDescription ? 'has-description' : 'no-description'; ?>">
		<?php if ($hasDescription) : ?>
			<div class="com-audioarchive-clip-main">
				<section class="com-audioarchive-description com-audioarchive-info-card" aria-labelledby="audioarchive-description-heading">
					<h2 id="audioarchive-description-heading"><?php echo Text::_('COM_AUDIOARCHIVE_DESCRIPTION_HEADING'); ?></h2>
					<div class="com-audioarchive-prose">
						<?php echo HTMLHelper::_('content.prepare', (string) $this->item->description, '', 'com_audioarchive.clip'); ?>
					</div>
				</section>
			</div>
		<?php endif; ?>

		<aside class="com-audioarchive-clip-sidebar" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_CLIP_INFORMATION'); ?>">
			<?php echo $this->loadTemplate('actions'); ?>
			<?php echo $this->loadTemplate('metadata'); ?>
			<?php echo $this->loadTemplate('tags'); ?>
			<?php echo $this->loadTemplate('download'); ?>
		</aside>
	</div>

	<?php echo $this->loadTemplate('navigation'); ?>
</article>
