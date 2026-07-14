<?php

use Joomla\CMS\Layout\FileLayout;

\defined('_JEXEC') or die;

$layout = new FileLayout(
	'tagdirectory.default',
	JPATH_SITE . '/components/com_audioarchive/layouts'
);
$moduleClass = trim(
	'com-audioarchive mod-audioarchive-tags '
	. htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8')
);
?>
<div class="<?php echo $moduleClass; ?>">
	<?php
	echo $layout->render([
		'items' => $items,
		'presentation' => (string) $params->get('tag_directory_presentation', 'list'),
		'show_descriptions' => (int) $params->get('tag_directory_show_descriptions', 1) === 1,
		'show_counts' => (int) $params->get('tag_directory_show_counts', 0) === 1,
	]);
	?>
</div>
