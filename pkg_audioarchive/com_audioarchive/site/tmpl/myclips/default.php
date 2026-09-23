<?php
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;
\defined('_JEXEC') or die;
$input = Factory::getApplication()->getInput();
$states = ['1' => 'JPUBLISHED', '0' => 'JUNPUBLISHED', '2' => 'JARCHIVED', '-2' => 'JTRASHED'];
?>
<div class="com-audioarchive">
<div class="d-flex align-items-center justify-content-between gap-3 mb-3"><h1><?php echo Text::_('COM_AUDIOARCHIVE_MY_CLIPS'); ?></h1>
<?php if ($this->uploadUrl) : ?><a class="btn btn-primary" href="<?php echo Route::_($this->uploadUrl); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FRONTEND_UPLOAD'); ?></a><?php endif; ?></div>
<?php if ($this->params->get('myclips_show_quota', 1)) : ?>
<?php echo LayoutHelper::render('contribution.quota', ['usage' => $this->usage, 'quota' => $this->quota, 'params' => $this->params], JPATH_SITE . '/components/com_audioarchive/layouts'); ?>
<?php endif; ?>
<form method="get" action="<?php echo Route::_('index.php?option=com_audioarchive&view=myclips&Itemid=' . $input->getInt('Itemid')); ?>" class="row g-2 mb-3">
<input type="hidden" name="option" value="com_audioarchive"><input type="hidden" name="view" value="myclips"><input type="hidden" name="Itemid" value="<?php echo $input->getInt('Itemid'); ?>">
<div class="col"><label for="my-search"><?php echo Text::_('JSEARCH_FILTER'); ?></label><input id="my-search" class="form-control" name="search" value="<?php echo $this->escape($input->getString('search')); ?>"></div>
<?php
$categories = [];
foreach ($this->categories as $category)
{
	$categories[(string) $category->id] = (string) $category->title;
}
$filters = [
'category' => ['JCATEGORY', $categories],
'state' => ['JSTATUS', $states],
'visibility' => ['COM_AUDIOARCHIVE_VISIBILITY', ['normal' => 'COM_AUDIOARCHIVE_VISIBILITY_NORMAL', 'private' => 'COM_AUDIOARCHIVE_VISIBILITY_PRIVATE']],
'processing' => ['COM_AUDIOARCHIVE_PROCESSING', ['available'=>'COM_AUDIOARCHIVE_PROCESSING_AVAILABLE','missing'=>'COM_AUDIOARCHIVE_PROCESSING_MISSING','pending'=>'COM_AUDIOARCHIVE_PROCESSING_PENDING','failed'=>'COM_AUDIOARCHIVE_PROCESSING_FAILED','stale'=>'COM_AUDIOARCHIVE_PROCESSING_STALE']],
'order' => ['JGLOBAL_SORT_BY', ['newest'=>'COM_AUDIOARCHIVE_SORT_UPLOAD_DESC','oldest'=>'COM_AUDIOARCHIVE_SORT_UPLOAD_ASC','title'=>'JGLOBAL_TITLE','size'=>'COM_AUDIOARCHIVE_FRONTEND_FILE_SIZE']]];
?>
<?php foreach ($filters as $name => [$label, $options]) : ?>
<div class="col"><label for="my-<?php echo $name; ?>"><?php echo Text::_($label); ?></label><select class="form-select" id="my-<?php echo $name; ?>" name="<?php echo $name; ?>">
<?php if ($name !== 'order') : ?><option value=""><?php echo Text::_('JALL'); ?></option><?php endif; ?>
<?php foreach ($options as $value => $text) : ?><option value="<?php echo $this->escape((string) $value); ?>" <?php echo $input->getString($name, $name === 'order' ? 'newest' : '') === (string) $value ? 'selected' : ''; ?>><?php echo $this->escape($name === 'category' ? $text : Text::_($text)); ?></option><?php endforeach; ?>
</select></div>
<?php endforeach; ?>
<div class="col-auto align-self-end"><button class="btn btn-secondary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button></div></form>
<?php if (!$this->items) : ?><div class="alert alert-info"><?php echo Text::_('COM_AUDIOARCHIVE_MY_CLIPS_EMPTY'); ?></div><?php else : ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr>
<?php foreach (['JGLOBAL_TITLE','JCATEGORY','JSTATUS','COM_AUDIOARCHIVE_VISIBILITY','JFIELD_ACCESS_LABEL','COM_AUDIOARCHIVE_FIELD_DURATION','COM_AUDIOARCHIVE_FIELD_UPLOAD_DATE','COM_AUDIOARCHIVE_FRONTEND_FILE_SIZE','COM_AUDIOARCHIVE_PROCESSING','JACTIONS'] as $label) : ?><th scope="col"><?php echo Text::_($label); ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($this->items as $item) : ?>
<tr><th scope="row"><?php if ($item->canView) : ?><a href="<?php echo Route::_(RouteHelper::getClipRoute((int) $item->id)); ?>"><?php echo $this->escape($item->title); ?></a><?php else : ?><?php echo $this->escape($item->title); ?><?php endif; ?></th>
<td><?php echo $this->escape($item->category_title ?? ''); ?></td><td><?php echo Text::_($states[(string) $item->state] ?? 'JUNPUBLISHED'); ?></td>
<td><?php echo Text::_('COM_AUDIOARCHIVE_VISIBILITY_' . strtoupper($item->visibility_mode)); ?></td><td><?php echo $this->escape($item->access_title ?? ''); ?></td>
<td><?php echo gmdate((int) $item->duration_ms >= 3600000 ? 'H:i:s' : 'i:s', (int) floor($item->duration_ms / 1000)); ?></td><td><?php echo HTMLHelper::_('date', $item->uploaded_at, Text::_('DATE_FORMAT_LC4')); ?></td>
<td><?php echo number_format((int) $item->file_size / 1048576, 2); ?> MB</td>
<td><?php foreach (['metadata_status','preview_status','waveform_status','spectrogram_status','frequency_profile_status'] as $status) : ?><div class="small"><?php echo Text::_('COM_AUDIOARCHIVE_STATUS_' . strtoupper($status)) . ': ' . $this->escape(Text::_('COM_AUDIOARCHIVE_PROCESSING_' . strtoupper($item->$status))); ?></div><?php endforeach; ?></td>
<td><div class="d-flex flex-wrap gap-2">
<?php if ($item->canEdit) : ?><a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_audioarchive&task=edit.edit&id=' . (int) $item->id . '&return=' . rawurlencode(base64_encode(Uri::getInstance()->toString()))); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a><?php endif; ?>
<?php if ($item->canTrash || $item->canDelete) : ?>
<form method="post" action="<?php echo Route::_('index.php?option=com_audioarchive&task=myclips.' . ($item->canDelete ? 'delete' : 'trash')); ?>">
<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
<?php if ($item->canDelete) : ?><label class="small d-block"><input type="checkbox" required name="confirm_delete" value="1"> <?php echo Text::_('COM_AUDIOARCHIVE_CONFIRM_PERMANENT_DELETE'); ?></label><?php endif; ?>
<button class="btn btn-sm btn-outline-danger" type="submit"><?php echo Text::_($item->canDelete ? 'JACTION_DELETE' : 'JTRASH'); ?></button><?php echo HTMLHelper::_('form.token'); ?></form>
<?php endif; ?></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php echo $this->pagination->getPagesLinks(); ?>
<?php endif; ?></div>
