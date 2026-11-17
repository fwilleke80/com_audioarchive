<?php
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** @brief Cover management only; artwork placement in public players is deferred. */
$app = Factory::getApplication();
if ((int) $app->getIdentity()->id <= 0)
{
    return;
}
$kind = ($displayData['kind'] ?? 'clip') === 'playlist' ? 'playlist' : 'clip';
$id = (string) ($displayData['id'] ?? '');
if ($kind === 'clip' && (int) $id <= 0) $id = '';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$endpoint = Uri::root() . ($app->isClient('administrator') ? 'administrator/' : '') . 'index.php?option=com_audioarchive&task=cover.manage&format=json';
?>
<fieldset class="mt-4 mb-4" data-audioarchive-cover data-cover-kind="<?php echo $kind; ?>" data-cover-id="<?php echo $escape($id); ?>"
    data-cover-endpoint="<?php echo $escape($endpoint); ?>" data-cover-token="<?php echo $escape(Session::getFormToken()); ?>"
    data-cover-pending="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_PENDING')); ?>"
    data-cover-removing="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_REMOVING')); ?>"
    data-cover-empty="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_EMPTY')); ?>"
    data-cover-saved="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_SAVED')); ?>"
    data-cover-error="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_ERROR')); ?>"
    data-cover-first="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_SAVE_FIRST')); ?>">
    <legend><?php echo Text::_('COM_AUDIOARCHIVE_COVER'); ?></legend>
    <p><?php echo Text::_('COM_AUDIOARCHIVE_PLAYLIST_EDIT_HELP'); ?></p>
    <label><?php echo Text::_('COM_AUDIOARCHIVE_COVER_FILE'); ?><input class="form-control" type="file" accept="image/jpeg,image/png,image/webp" data-cover-file></label>
    <div class="mt-2">
        <button type="button" class="btn btn-outline-danger" data-cover-remove><?php echo Text::_('COM_AUDIOARCHIVE_COVER_REMOVE'); ?></button>
    </div>
    <button type="button" class="btn btn-outline-secondary" data-cover-undo hidden><?php echo Text::_('COM_AUDIOARCHIVE_COVER_UNDO'); ?></button>
    <div><img data-cover-preview hidden alt="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_PREVIEW')); ?>" style="max-width:240px;max-height:240px"></div>
    <p role="status" aria-live="polite" data-cover-status></p>
    <noscript><?php echo Text::_('COM_AUDIOARCHIVE_DOWNLOAD_REQUIRES_JS'); ?></noscript>
</fieldset>
