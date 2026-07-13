<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('allow_original_downloads', 1) !== 1 || (int) $this->params->get('detail_show_download', 1) !== 1)
{
	return;
}
?>
<div class="com-audioarchive-download">
	<a class="btn btn-primary com-audioarchive-download-button" href="<?php echo $this->downloadUrl; ?>">
		<span aria-hidden="true">↓</span>
		<span><?php echo Text::_('COM_AUDIOARCHIVE_DOWNLOAD_ORIGINAL'); ?></span>
	</a>
</div>
