<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;
?>
<div class="com-audioarchive com-audioarchive-archive">
	<?php if ((int) $this->params->get('show_page_heading', 1) === 1) : ?>
		<h1><?php echo $this->escape($this->pageHeading); ?></h1>
	<?php endif; ?>

	<?php foreach ($this->filterErrors as $errorKey) : ?>
		<div class="alert alert-warning" role="alert"><?php echo Text::_($errorKey); ?></div>
	<?php endforeach; ?>

	<?php echo $this->loadTemplate('filters'); ?>
	<?php echo $this->loadTemplate('active_filters'); ?>

	<?php if ((int) $this->params->get('archive_show_result_count', 1) === 1) : ?>
		<p class="com-audioarchive-result-count" aria-live="polite">
			<?php echo Text::plural('COM_AUDIOARCHIVE_RESULTS_COUNT', (int) $this->pagination->total); ?>
		</p>
	<?php endif; ?>

	<?php echo $this->loadTemplate('table'); ?>
	<?php echo $this->loadTemplate('pagination'); ?>
</div>
