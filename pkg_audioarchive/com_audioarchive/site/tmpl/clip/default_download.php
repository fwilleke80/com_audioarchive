<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if (!$this->canDownload || (int) $this->params->get('detail_show_download', 1) !== 1 || $this->downloadUrl === '')
{
	return;
}
?>
<div class="com-audioarchive-download">
    <?php echo \Joomla\CMS\Layout\LayoutHelper::render('interaction.download', [
        'clipId' => (int) $this->item->id, 'url' => $this->downloadUrl,
        'class' => 'btn btn-primary com-audioarchive-download-button',
    ], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
</div>
