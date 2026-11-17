<?php
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** @brief Render only populated credits as escaped text; lyrics retain their line breaks. */
$credits = [];
foreach (['licence', 'creator', 'contributors', 'recording_location', 'genre_style', 'creation_year', 'lyrics'] as $field)
{
    $value = trim((string) ($this->item->$field ?? ''));
    if ($value !== '')
    {
        $credits[$field] = $value;
    }
}
if ($credits === [])
{
    return;
}
?>
<section class="com-audioarchive-info-card" aria-labelledby="audioarchive-credits-heading">
    <h2 id="audioarchive-credits-heading"><?php echo Text::_('COM_AUDIOARCHIVE_METADATA'); ?></h2>
    <dl>
        <?php foreach ($credits as $field => $value) : ?>
        <dt><?php echo Text::_('COM_AUDIOARCHIVE_METADATA_' . strtoupper($field)); ?></dt>
        <dd style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo $this->escape($value); ?></dd>
        <?php endforeach; ?>
    </dl>
</section>
