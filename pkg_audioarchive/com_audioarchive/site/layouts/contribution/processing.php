<?php
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
\defined('_JEXEC') or die;
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$ids = array_values(array_map('intval', $displayData['ids'] ?? []));
if ($ids === [])
{
	return;
}
$return = (string) ($displayData['return'] ?? '');
?>
<div class="alert alert-info" data-audioarchive-upload-analysis
	data-ids="<?php echo $escape(json_encode($ids)); ?>"
	data-endpoint="<?php echo $escape(Route::_('index.php?option=com_audioarchive&task=upload.processAnalysis&format=json', false)); ?>"
	data-token="<?php echo $escape(Session::getFormToken()); ?>"
	data-return="<?php echo $escape($return); ?>"
	data-error="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_ERROR')); ?>"
	data-failed="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_FAILED')); ?>"
	data-complete="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_COMPLETE')); ?>">
	<p role="status" aria-live="polite" data-processing-status><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_WORKING'); ?></p>
	<button type="button" class="btn btn-sm btn-outline-primary" data-processing-retry hidden><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_RETRY'); ?></button>
	<?php if ($return !== '') : ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $escape($return); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_CONTINUE'); ?></a><?php endif; ?>
	<noscript><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_NOSCRIPT'); ?></noscript>
</div>
