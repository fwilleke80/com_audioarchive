<?php
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
\defined('_JEXEC') or die;
?>
<div class="com-audioarchive">
	<h1><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_TITLE'); ?></h1>
	<?php echo LayoutHelper::render('contribution.processing', ['ids' => [(int) $this->processingGrant['clip']], 'return' => $this->processingGrant['return']], JPATH_SITE . '/components/com_audioarchive/layouts'); ?>
</div>
