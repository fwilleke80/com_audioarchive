<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$totalSeconds = (int) floor((int) $this->item->duration_ms / 1000);
$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
$fileSize = (int) $this->item->file_size;
$units = ['B', 'KiB', 'MiB', 'GiB'];
$unitIndex = 0;
$sizeValue = (float) $fileSize;
while ($sizeValue >= 1024 && $unitIndex < count($units) - 1)
{
	$sizeValue /= 1024;
	$unitIndex++;
}
$formattedSize = $fileSize > 0
	? number_format($sizeValue, $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex]
	: '—';
$rows = [];

if ((int) $this->params->get('detail_show_duration', 1) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_DURATION'), '<time datetime="PT' . $totalSeconds . 'S">' . $duration . '</time>'];
}
if ((int) $this->params->get('detail_show_recorded', 1) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_RECORDING_DATE'), $this->item->recorded_at ? HTMLHelper::_('date', $this->item->recorded_at, Text::_('DATE_FORMAT_LC3')) : '—'];
}
if ((int) $this->params->get('detail_show_uploaded', 1) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_UPLOAD_DATE'), HTMLHelper::_('date', $this->item->uploaded_at, Text::_('DATE_FORMAT_LC3'))];
}
if ((int) $this->params->get('detail_show_original_filename', 0) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_ORIGINAL_FILENAME'), $this->escape((string) $this->item->original_filename)];
}
if ((int) $this->params->get('detail_show_format', 0) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_CONTAINER'), $this->escape((string) $this->item->container_format)];
}
if ((int) $this->params->get('detail_show_codec', 0) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_CODEC'), $this->escape((string) $this->item->audio_codec)];
}
if ((int) $this->params->get('detail_show_file_size', 0) === 1)
{
	$rows[] = [Text::_('COM_AUDIOARCHIVE_FIELD_FILE_SIZE'), $formattedSize];
}
?>
<?php if ($rows) : ?>
	<section class="com-audioarchive-info-card" aria-labelledby="audioarchive-details-heading">
		<h2 id="audioarchive-details-heading"><?php echo Text::_('COM_AUDIOARCHIVE_DETAILS_HEADING'); ?></h2>
		<dl class="com-audioarchive-metadata">
			<?php foreach ($rows as [$label, $value]) : ?>
				<div>
					<dt><?php echo $label; ?></dt>
					<dd><?php echo $value; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>
<?php endif; ?>
