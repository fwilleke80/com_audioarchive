<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

HTMLHelper::_('behavior.multiselect');
$user = $this->getCurrentUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_audioarchive&view=clips'); ?>" method="post" name="adminForm" id="adminForm">
    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

    <?php if (empty($this->items)) : ?>
        <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
    <?php else : ?>
        <table class="table" id="clipList">
            <caption class="visually-hidden"><?php echo Text::_('COM_AUDIOARCHIVE_CLIPS_TITLE'); ?></caption>
            <thead>
                <tr>
                    <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                    <th class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_AUDIOARCHIVE_FIELD_DURATION', 'a.duration_ms', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_AUDIOARCHIVE_FIELD_RECORDING_DATE', 'a.recorded_at', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_AUDIOARCHIVE_FIELD_UPLOAD_DATE', 'a.uploaded_at', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo Text::_('COM_AUDIOARCHIVE_COLUMN_TAGS'); ?></th>
                    <th scope="col" class="w-5"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $assetName = 'com_audioarchive.clip.' . (int) $item->id;
                    $canEdit = $user->authorise('core.edit', $assetName);
                    $canChange = $user->authorise('core.edit.state', $assetName);
                    $durationSeconds = (int) floor((int) $item->duration_ms / 1000);
                    $durationFormat = $durationSeconds >= 3600 ? 'H:i:s' : 'i:s';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                        <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'clips.', $canChange, 'cb'); ?></td>
                        <th scope="row">
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . (int) $item->id); ?>">
                                    <?php echo $this->escape($item->title); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $this->escape($item->title); ?>
                            <?php endif; ?>
                        </th>
                        <td><?php echo $this->escape($item->category_title); ?></td>
                        <td><?php echo gmdate($durationFormat, $durationSeconds); ?></td>
                        <td><?php echo $item->recorded_at ? HTMLHelper::_('date', $item->recorded_at, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?></td>
                        <td><?php echo HTMLHelper::_('date', $item->uploaded_at, Text::_('DATE_FORMAT_LC4')); ?></td>
                        <td><?php echo $this->escape($item->access_level); ?></td>
                        <td><?php echo $item->tags ? $this->escape(implode(', ', array_map(static fn($tag) => $tag->title, $item->tags))) : '&mdash;'; ?></td>
                        <td><?php echo (int) $item->id; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>

    <?php echo $this->loadTemplate('batch'); ?>

    <?php echo $this->filterForm->renderControlFields(); ?>
</form>
