<?php

use Joomla\CMS\Layout\FileLayout;

\defined('_JEXEC') or die;

$layout = new FileLayout(
	'tagdirectory.default',
	JPATH_SITE . '/components/com_audioarchive/layouts'
);
?>
<div class="com-audioarchive com-audioarchive-tagdirectory">
	<header class="com-audioarchive-page-header">
		<?php if ((int) $this->params->get('show_page_heading', 1) === 1) : ?>
			<h1><?php echo $this->escape($this->pageHeading); ?></h1>
		<?php endif; ?>
	</header>

	<?php $introText = trim((string) $this->params->get('tag_directory_intro_text', '')); ?>
	<?php if ($introText !== '') : ?>
		<div class="com-audioarchive-intro">
			<?php echo $introText; ?>
		</div>
	<?php endif; ?>

	<?php
	echo $layout->render([
		'items' => $this->items,
		'presentation' => (string) $this->params->get('tag_directory_presentation', 'cards'),
		'show_descriptions' => (int) $this->params->get('tag_directory_show_descriptions', 1) === 1,
		'show_counts' => (int) $this->params->get('tag_directory_show_counts', 0) === 1,
	]);
	?>
</div>
