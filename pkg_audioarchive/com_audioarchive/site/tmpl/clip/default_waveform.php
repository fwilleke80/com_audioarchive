<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ($this->waveformUrl === '')
{
	return;
}
?>
<section class="com-audioarchive-waveform-section" aria-labelledby="audioarchive-waveform-heading">
	<h2 id="audioarchive-waveform-heading" class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_WAVEFORM_HEADING'); ?></h2>
	<div
		class="com-audioarchive-waveform"
		data-audioarchive-waveform
		data-waveform-url="<?php echo $this->escape($this->waveformUrl); ?>"
	>
		<canvas aria-hidden="true"></canvas>
		<p class="com-audioarchive-waveform-status" data-audioarchive-waveform-status>
			<?php echo Text::_('COM_AUDIOARCHIVE_WAVEFORM_LOADING'); ?>
		</p>
	</div>
</section>
