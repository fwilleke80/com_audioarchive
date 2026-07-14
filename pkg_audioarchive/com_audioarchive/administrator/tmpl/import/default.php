<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

foreach ([
	'COM_AUDIOARCHIVE_IMPORT_STATUS_DISCOVERED',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_ANALYSING',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_READY',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_INELIGIBLE',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_IMPORTING',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_COMPLETE',
	'COM_AUDIOARCHIVE_IMPORT_STATUS_FAILED',
	'COM_AUDIOARCHIVE_IMPORT_ACTION_EDIT',
	'COM_AUDIOARCHIVE_IMPORT_ACTION_RETRY',
	'COM_AUDIOARCHIVE_IMPORT_START',
	'COM_AUDIOARCHIVE_IMPORT_CATEGORY_COLUMN',
	'COM_AUDIOARCHIVE_IMPORT_NO_RESPONSE',
	'COM_AUDIOARCHIVE_IMPORT_NETWORK_ERROR',
	'COM_AUDIOARCHIVE_IMPORT_SCAN_SUMMARY',
	'COM_AUDIOARCHIVE_IMPORT_RUN_SUMMARY',
	'COM_AUDIOARCHIVE_IMPORT_DUPLICATE_LABEL',
	'COM_AUDIOARCHIVE_DUPLICATE_EDIT_LINK',
	'COM_AUDIOARCHIVE_IMPORT_SOURCE_REMOVED',
	'COM_AUDIOARCHIVE_IMPORT_SOURCE_PRESERVED',
	'COM_AUDIOARCHIVE_IMPORT_CATEGORY_WILL_CREATE',
	'COM_AUDIOARCHIVE_REPLACEMENT_STATUS_REPLACING',
	'COM_AUDIOARCHIVE_REPLACEMENT_START',
	'COM_AUDIOARCHIVE_REPLACEMENT_TARGET_COLUMN',
	'COM_AUDIOARCHIVE_REPLACEMENT_CURRENT_MEDIA',
	'COM_AUDIOARCHIVE_REPLACEMENT_NEW_MEDIA',
	'COM_AUDIOARCHIVE_REPLACEMENT_AMBIGUOUS_LIST',
	'COM_AUDIOARCHIVE_REPLACEMENT_PREVIOUS_RETAINED',
	'COM_AUDIOARCHIVE_REPLACEMENT_PREVIOUS_DELETED',
] as $key)
{
	Text::script($key);
}

$scanEndpoint = Route::_('index.php?option=com_audioarchive&task=import.scan&format=json', false);
$inspectEndpoint = Route::_('index.php?option=com_audioarchive&task=import.inspect&format=json', false);
$importEndpoint = Route::_('index.php?option=com_audioarchive&task=import.importFile&format=json', false);
$replacementEndpoint = Route::_('index.php?option=com_audioarchive&task=import.replaceFile&format=json', false);
$tokenName = Session::getFormToken();
?>
<form
	id="audioarchive-directory-import-form"
	class="form-validate"
	data-scan-endpoint="<?php echo htmlspecialchars($scanEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
	data-inspect-endpoint="<?php echo htmlspecialchars($inspectEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
	data-import-endpoint="<?php echo htmlspecialchars($importEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
	data-replacement-endpoint="<?php echo htmlspecialchars($replacementEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
	data-token-name="<?php echo htmlspecialchars($tokenName, ENT_QUOTES, 'UTF-8'); ?>"
>
	<div class="alert alert-info" id="audioarchive-import-info">
		<h2 class="h5 alert-heading"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_INBOX_TITLE'); ?></h2>
		<p class="mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_INBOX_DESC'); ?></p>
	</div>
	<div class="alert alert-warning d-none" id="audioarchive-replacement-info">
		<h2 class="h5 alert-heading"><?php echo Text::_('COM_AUDIOARCHIVE_REPLACEMENT_INBOX_TITLE'); ?></h2>
		<p class="mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_REPLACEMENT_INBOX_DESC'); ?></p>
	</div>

	<div class="row g-4 mb-4">
		<div class="col-12 col-xl-5">
			<div class="card h-100">
				<div class="card-header">
					<h2 class="h4 mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_SCAN_OPTIONS_TITLE'); ?></h2>
				</div>
				<div class="card-body">
					<?php echo $this->form->renderFieldset('scan'); ?>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-7" id="audioarchive-import-metadata-card">
			<div class="card h-100">
				<div class="card-header">
					<h2 class="h4 mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_METADATA_TITLE'); ?></h2>
				</div>
				<div class="card-body">
					<p class="text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_METADATA_DESC'); ?></p>
					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<?php echo $this->form->renderField('category_mode'); ?>
							<?php echo $this->form->renderField('catid'); ?>
							<?php echo $this->form->renderField('create_missing_categories'); ?>
						</div>
						<div class="col-12 col-lg-6">
							<?php echo $this->form->renderField('tags'); ?>
							<?php echo $this->form->renderField('access'); ?>
							<?php echo $this->form->renderField('state'); ?>
							<?php echo $this->form->renderField('recorded_at'); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="card-header d-flex flex-wrap align-items-center gap-2">
			<h2 class="h4 mb-0 me-auto"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_FILES_TITLE'); ?></h2>
			<button type="button" class="btn btn-outline-primary" id="audioarchive-import-scan">
				<span class="icon-search" aria-hidden="true"></span>
				<?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_SCAN'); ?>
			</button>
			<button type="button" class="btn btn-outline-secondary" id="audioarchive-import-select" disabled>
				<?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_SELECT_ELIGIBLE'); ?>
			</button>
			<button type="button" class="btn btn-outline-secondary" id="audioarchive-import-deselect" disabled>
				<?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_DESELECT_ALL'); ?>
			</button>
			<button type="button" class="btn btn-primary" id="audioarchive-import-start" disabled>
				<span class="icon-play" aria-hidden="true"></span>
				<span id="audioarchive-import-start-label"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_START'); ?></span>
			</button>
			<button type="button" class="btn btn-outline-danger d-none" id="audioarchive-import-stop">
				<?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_STOP'); ?>
			</button>
		</div>
		<div class="card-body border-bottom">
			<div id="audioarchive-import-summary" class="text-body-secondary" aria-live="polite"></div>
		</div>
		<div id="audioarchive-import-empty" class="card-body text-center text-body-secondary">
			<?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_EMPTY'); ?>
		</div>
		<div id="audioarchive-import-table-wrapper" class="table-responsive d-none">
			<table class="table align-middle mb-0">
				<thead>
					<tr>
						<th scope="col" class="text-center"><?php echo Text::_('JGLOBAL_CHECK_ALL'); ?></th>
						<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_PATH'); ?></th>
						<th scope="col" id="audioarchive-import-context-heading"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_CATEGORY_COLUMN'); ?></th>
						<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_METADATA'); ?></th>
						<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_STATUS'); ?></th>
						<th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_RESULT'); ?></th>
						<th scope="col" class="text-end"><?php echo Text::_('COM_AUDIOARCHIVE_IMPORT_ACTIONS'); ?></th>
					</tr>
				</thead>
				<tbody id="audioarchive-import-files"></tbody>
			</table>
		</div>
	</div>

	<?php echo HTMLHelper::_('form.token'); ?>
</form>
