<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('allow_original_downloads', 1) !== 1 || (int) $this->params->get('detail_show_download', 1) !== 1)
{
	return;
}
?>
<p class="com-audioarchive-download">
	<a class="btn btn-primary" href="<?php echo $this->downloadUrl; ?>">
		<?php echo Text::_('COM_AUDIOARCHIVE_DOWNLOAD_ORIGINAL'); ?>
	</a>
</p>
