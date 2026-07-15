<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$renderAudioPreview = function (string $headingId): void
{
    if (
        $this->originalFile === null
        || (int) ($this->originalFile->is_available ?? 0) !== 1
        || $this->playbackUrl === ''
    )
    {
        return;
    }
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $playerId = $headingId . '-audio';
    $seekId = $playerId . '-seek';
    $volumeId = $playerId . '-volume';
    $title = trim((string) ($this->item->title ?? '')) ?: Text::_('COM_AUDIOARCHIVE_ADMIN_PREVIEW_TITLE');
    $mime = trim((string) ($this->originalFile->mime_type ?? '')) ?: 'application/octet-stream';
    $playLabel = Text::sprintf('COM_AUDIOARCHIVE_ADMIN_PLAYER_PLAY', $title);
    $pauseLabel = Text::sprintf('COM_AUDIOARCHIVE_ADMIN_PLAYER_PAUSE', $title);
    ?>
    <section class="audioarchive-admin-preview" aria-labelledby="<?php echo $escape($headingId); ?>">
        <div class="audioarchive-admin-preview-copy">
            <h3 id="<?php echo $escape($headingId); ?>" class="h5"><?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PREVIEW_TITLE'); ?></h3>
            <p class="text-muted"><?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PREVIEW_DESC'); ?></p>
        </div>

        <div class="audioarchive-custom-player" data-audioarchive-custom-player>
            <button
                type="button"
                class="audioarchive-custom-player-toggle"
                aria-controls="<?php echo $escape($playerId); ?>"
                aria-label="<?php echo $escape($playLabel); ?>"
                aria-pressed="false"
                title="<?php echo $escape($playLabel); ?>"
                data-audioarchive-custom-toggle
                data-play-label="<?php echo $escape($playLabel); ?>"
                data-pause-label="<?php echo $escape($pauseLabel); ?>"
            >
                <span data-audioarchive-icon-play aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>
                </span>
                <span data-audioarchive-icon-pause aria-hidden="true" hidden>
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M6.5 5h4v14h-4zm7 0h4v14h-4z"/></svg>
                </span>
            </button>

            <div class="audioarchive-custom-player-main">
                <label class="visually-hidden" for="<?php echo $escape($seekId); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_SEEK'); ?></label>
                <input
                    id="<?php echo $escape($seekId); ?>"
                    class="audioarchive-custom-player-seek"
                    type="range"
                    min="0"
                    max="1000"
                    step="1"
                    value="0"
                    disabled
                    data-audioarchive-custom-seek
                >
                <div class="audioarchive-custom-player-times" aria-hidden="true">
                    <span data-audioarchive-current-time>0:00</span>
                    <span data-audioarchive-duration>0:00</span>
                </div>
            </div>

            <div class="audioarchive-custom-player-volume-controls">
                <button
                    type="button"
                    class="audioarchive-custom-player-mute"
                    aria-label="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_MUTE')); ?>"
                    aria-pressed="false"
                    title="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_MUTE')); ?>"
                    data-audioarchive-custom-mute
                    data-mute-label="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_MUTE')); ?>"
                    data-unmute-label="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_UNMUTE')); ?>"
                >
                    <span data-audioarchive-icon-volume aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.5-.8v2.1c1.2.6 2 1.8 2 3.2s-.8 2.6-2 3.2v2.1c2.3-.7 4-2.8 4-5.3s-1.7-4.6-4-5.3z"/></svg>
                    </span>
                    <span data-audioarchive-icon-muted aria-hidden="true" hidden>
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.7 1.3-1.4 1.4 1.8 1.8-1.8 1.8 1.4 1.4 1.8-1.8 1.8 1.8 1.4-1.4-1.8-1.8 1.8-1.8-1.4-1.4-1.8 1.8z"/></svg>
                    </span>
                </button>
                <label class="visually-hidden" for="<?php echo $escape($volumeId); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_VOLUME'); ?></label>
                <input
                    id="<?php echo $escape($volumeId); ?>"
                    class="audioarchive-custom-player-volume"
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    value="1"
                    data-audioarchive-custom-volume
                >
            </div>

            <audio
                id="<?php echo $escape($playerId); ?>"
                preload="metadata"
                data-audioarchive-custom-audio
                data-clip-title="<?php echo $escape($title); ?>"
            >
                <source src="<?php echo $escape($this->playbackUrl); ?>" type="<?php echo $escape($mime); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_FALLBACK'); ?>
            </audio>
        </div>

        <noscript>
            <audio class="audioarchive-custom-player-noscript" controls preload="metadata">
                <source src="<?php echo $escape($this->playbackUrl); ?>" type="<?php echo $escape($mime); ?>">
                <?php echo Text::_('COM_AUDIOARCHIVE_ADMIN_PLAYER_FALLBACK'); ?>
            </audio>
        </noscript>
    </section>
    <?php
};
?>
<form action="<?php echo Route::_('index.php?option=com_audioarchive&view=clip&layout=edit&id=' . (int) $this->item->id); ?>" method="post" enctype="multipart/form-data" name="adminForm" id="clip-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'clipTab', ['active' => 'details', 'recall' => true]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'details', Text::_('COM_AUDIOARCHIVE_FIELDSET_DETAILS')); ?>
            <?php $renderAudioPreview('audioarchive-admin-preview-details-heading'); ?>
            <div class="row">
                <div class="col-lg-9">
                    <?php echo $this->form->renderField('description'); ?>
                </div>
                <div class="col-lg-3">
                    <?php echo $this->form->renderField('catid'); ?>
                    <?php echo $this->form->renderField('tags'); ?>
                    <?php echo $this->form->renderField('recorded_at'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'file', Text::_('COM_AUDIOARCHIVE_FIELDSET_FILE_INFORMATION')); ?>
            <?php if ($this->originalFile === null) : ?>
                <div class="alert alert-info"><?php echo Text::_('COM_AUDIOARCHIVE_UPLOAD_ORIGINAL_NOTICE'); ?></div>
                <?php if ($this->form->getField('audio_file') !== false) : ?>
                    <?php echo $this->form->renderField('audio_file'); ?>
                <?php endif; ?>
            <?php else : ?>
                <?php
                $durationMs = (int) $this->originalFile->duration_ms;
                $seconds = intdiv($durationMs, 1000);
                $duration = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
                $fileSize = HTMLHelper::_('number.bytes', (int) $this->originalFile->file_size);
                $technicalMetadata = json_decode((string) $this->item->technical_metadata, true);
                $technicalMetadata = is_array($technicalMetadata) ? $technicalMetadata : [];
                $sampleRate = max(0, (int) ($technicalMetadata['sample_rate'] ?? 0));
                $channels = max(0, (int) ($technicalMetadata['channels'] ?? 0));
                $bitrate = max(0, (int) ($technicalMetadata['bitrate'] ?? 0));
                ?>
                <?php if ((int) $this->originalFile->is_available === 1) : ?>
                    <div class="alert alert-success"><?php echo Text::_('COM_AUDIOARCHIVE_ORIGINAL_STORED'); ?></div>
                    <?php $renderAudioPreview('audioarchive-admin-preview-file-heading'); ?>
                <?php else : ?>
                    <div class="alert alert-danger">
                        <?php echo Text::_('COM_AUDIOARCHIVE_ORIGINAL_UNAVAILABLE'); ?>
                        <?php if (trim((string) $this->originalFile->processing_error) !== '') : ?>
                            <br><?php echo htmlspecialchars((string) $this->originalFile->processing_error, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <dl class="row">
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_ORIGINAL_FILENAME'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) $this->item->original_filename, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_FILE_FORMAT'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) $this->originalFile->container_format, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_AUDIO_CODEC'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) $this->originalFile->audio_codec, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_MIME_TYPE'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) $this->originalFile->mime_type, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_FILE_SIZE'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($fileSize, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_DURATION'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_SAMPLE_RATE'); ?></dt>
                    <dd class="col-sm-9"><?php echo $sampleRate > 0 ? number_format($sampleRate) . ' Hz' : Text::_('JNONE'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_CHANNELS'); ?></dt>
                    <dd class="col-sm-9"><?php echo $channels > 0 ? (int) $channels : Text::_('JNONE'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_BITRATE'); ?></dt>
                    <dd class="col-sm-9"><?php echo $bitrate > 0 ? number_format($bitrate / 1000, 1) . ' kbit/s' : Text::_('JNONE'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_AUDIOARCHIVE_FIELD_CHECKSUM'); ?></dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars((string) $this->originalFile->checksum_sha256, ENT_QUOTES, 'UTF-8'); ?></code></dd>
                </dl>

                <?php if ($this->canManageFiles && $this->form->getField('audio_file') !== false) : ?>
                    <hr>
                    <h3 class="h5"><?php echo Text::_('COM_AUDIOARCHIVE_REPLACE_ORIGINAL_TITLE'); ?></h3>
                    <div class="alert alert-warning">
                        <?php echo Text::_('COM_AUDIOARCHIVE_REPLACE_ORIGINAL_NOTICE'); ?>
                    </div>
                    <?php echo $this->form->renderField('audio_file'); ?>
                <?php endif; ?>

                <?php if ($this->canProcess) : ?>
                    <hr>
                    <h3 class="h5"><?php echo Text::_('COM_AUDIOARCHIVE_TECHNICAL_ACTIONS_TITLE'); ?></h3>
                    <p class="text-muted"><?php echo Text::_('COM_AUDIOARCHIVE_TECHNICAL_ACTIONS_NOTE'); ?></p>
                    <div class="btn-toolbar gap-2" role="toolbar">
                        <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('clip.verify');">
                            <span class="icon-check" aria-hidden="true"></span>
                            <?php echo Text::_('COM_AUDIOARCHIVE_ACTION_VERIFY_FILE'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('clip.reanalyse');">
                            <span class="icon-refresh" aria-hidden="true"></span>
                            <?php echo Text::_('COM_AUDIOARCHIVE_ACTION_REANALYSE'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('clip.generateWaveform');">
                            <span class="icon-chart" aria-hidden="true"></span>
                            <?php echo Text::_((string) $this->item->waveform_status === 'available'
                                ? 'COM_AUDIOARCHIVE_ACTION_REGENERATE_WAVEFORM'
                                : 'COM_AUDIOARCHIVE_ACTION_GENERATE_WAVEFORM'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <hr>
            <?php echo $this->form->renderField('uploaded_at'); ?>
            <?php echo $this->form->renderField('metadata_status'); ?>
            <?php echo $this->form->renderField('preview_status'); ?>
            <?php echo $this->form->renderField('waveform_status'); ?>
            <?php echo $this->form->renderField('play_count'); ?>
            <?php echo $this->form->renderField('download_count'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'clipTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
            <div class="row">
                <div class="col-lg-6">
                    <?php echo $this->form->renderFieldset('publishing'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->getInput('id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
