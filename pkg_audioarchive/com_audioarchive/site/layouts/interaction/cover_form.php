<?php
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Pending cover selection inside the authorised clip form; no immediate server writes. */
$id = (int) ($displayData['id'] ?? 0);
$db = Factory::getContainer()->get(DatabaseInterface::class);
$cover = $id > 0 ? $db->setQuery("SELECT width, height FROM #__audioarchive_covers WHERE entity_type='clip' AND entity_id=" . $id)->loadObject() : null;
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$status = $cover ? Text::_('COM_AUDIOARCHIVE_COVER_SAVED') . ' (' . $cover->width . ' × ' . $cover->height . ')' : Text::_('COM_AUDIOARCHIVE_COVER_EMPTY');
?>
<fieldset class="mt-4 mb-4" data-cover-form
    data-cover-initial="<?php echo $escape($status); ?>"
    data-cover-pending="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_PENDING')); ?>"
    data-cover-removing="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_REMOVING')); ?>">
    <legend><?php echo Text::_('COM_AUDIOARCHIVE_COVER'); ?></legend>
    <p><?php echo Text::_('COM_AUDIOARCHIVE_COVER_FORM_HELP'); ?></p>
    <label><?php echo Text::_('COM_AUDIOARCHIVE_COVER_FILE'); ?><input class="form-control" type="file" name="clip_cover" accept="image/jpeg,image/png,image/webp" data-cover-pending-file></label>
    <div class="mt-2"><label><input type="checkbox" name="clip_cover_remove" value="1" data-cover-pending-remove> <?php echo Text::_('COM_AUDIOARCHIVE_COVER_REMOVE'); ?></label></div>
    <button type="button" class="btn btn-outline-secondary mt-2" data-cover-undo hidden><?php echo Text::_('COM_AUDIOARCHIVE_COVER_UNDO'); ?></button>
    <div><img data-cover-local-preview hidden alt="<?php echo $escape(Text::_('COM_AUDIOARCHIVE_COVER_PREVIEW')); ?>" style="max-width:240px;max-height:240px;margin-top:1rem"></div>
    <p role="status" aria-live="polite" data-cover-status><?php echo $escape($status); ?></p>
</fieldset>
<script type="module" src="<?php echo $escape(Uri::root() . 'media/com_audioarchive/js/cover-form.js?v=0.15.5'); ?>"></script>
