<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

foreach ([
    'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_PENDING',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_UPLOADING',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_COMPLETE',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_FAILED',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_CANCELLED',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_RETRY',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_CANCEL',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_REMOVE',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_EDIT',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_NO_RESPONSE',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_NETWORK_ERROR',
    'COM_AUDIOARCHIVE_BULK_UPLOAD_SUMMARY',
    'COM_AUDIOARCHIVE_DUPLICATE_EDIT_LINK',
    'COM_AUDIOARCHIVE_WARNING_DUPLICATE_ALLOWED',
] as $key)
{
    Text::script($key);
}

$endpoint = Route::_('index.php?option=com_audioarchive&task=upload.upload&format=json', false);
$tokenName = Session::getFormToken();
?>
<form
    id="audioarchive-bulk-upload-form"
    class="form-validate"
    data-upload-endpoint="<?php echo htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8'); ?>"
    data-token-name="<?php echo htmlspecialchars($tokenName, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h4 mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_METADATA_TITLE'); ?></h2>
        </div>
        <div class="card-body">
            <p class="text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_METADATA_DESC'); ?></p>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <?php echo $this->form->renderField('catid'); ?>
                    <?php echo $this->form->renderField('tags'); ?>
                </div>
                <div class="col-12 col-lg-6">
                    <?php echo $this->form->renderField('access'); ?>
                    <?php echo $this->form->renderField('state'); ?>
                    <?php echo $this->form->renderField('recorded_at'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h4 mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_FILES_TITLE'); ?></h2>
        </div>
        <div class="card-body">
            <p class="text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_FILES_DESC'); ?></p>
            <input
                type="file"
                id="audioarchive-bulk-upload-files"
                class="visually-hidden"
                multiple
                accept="audio/*,.m4a,.mp4,.aac,.mp3,.ogg,.oga,.opus,.wav,.flac,.webm"
            >
            <div
                id="audioarchive-bulk-upload-dropzone"
                class="com-audioarchive-upload-dropzone"
                role="button"
                tabindex="0"
                aria-controls="audioarchive-bulk-upload-files"
            >
                <span class="icon-upload" aria-hidden="true"></span>
                <strong><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_DROP_TITLE'); ?></strong>
                <span><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_DROP_DESC'); ?></span>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary" id="audioarchive-bulk-upload-select">
                    <span class="icon-folder-open" aria-hidden="true"></span>
                    <?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_SELECT_FILES'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="audioarchive-bulk-upload-start" disabled>
                    <span class="icon-play" aria-hidden="true"></span>
                    <?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_START'); ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="audioarchive-bulk-upload-clear" disabled>
                    <?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_CLEAR_FINISHED'); ?>
                </button>
                <span id="audioarchive-bulk-upload-summary" class="ms-lg-auto text-body-secondary" aria-live="polite"></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="h4 mb-0"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_QUEUE_TITLE'); ?></h2>
        </div>
        <div class="card-body p-0">
            <div id="audioarchive-bulk-upload-empty" class="p-4 text-center text-body-secondary">
                <?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_QUEUE_EMPTY'); ?>
            </div>
            <div id="audioarchive-bulk-upload-table-wrapper" class="table-responsive d-none">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_FILE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_PROGRESS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_RESULT'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('JGRID_HEADING_ACTIONS'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="audioarchive-bulk-upload-queue"></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php echo HTMLHelper::_('form.token'); ?>
</form>
