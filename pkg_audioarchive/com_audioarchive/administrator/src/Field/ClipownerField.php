<?php
namespace Punga\Component\Audioarchive\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** @brief Render readable account labels and retain filters for deleted owners. */
class ClipownerField extends ListField
{
	protected $type = 'Clipowner';

	/** @brief Combine ordinary accounts with orphaned clip-owner identifiers. */
	protected function getOptions()
	{
		$db = $this->getDatabase();
		$rows = $db->setQuery('SELECT id, name, username FROM ' . $db->quoteName('#__users') . ' ORDER BY name, username')->loadObjectList();
		$options = parent::getOptions();
		foreach ($rows as $row)
		{
			$options[] = HTMLHelper::_('select.option', (int) $row->id, $row->name . ' (' . $row->username . ')');
		}
		$orphans = $db->setQuery('SELECT DISTINCT c.created_by FROM ' . $db->quoteName('#__audioarchive_clips') . ' c LEFT JOIN '
			. $db->quoteName('#__users') . ' u ON u.id=c.created_by WHERE c.created_by>0 AND u.id IS NULL ORDER BY c.created_by')->loadColumn();
		foreach ($orphans as $id)
		{
			$options[] = HTMLHelper::_('select.option', (int) $id, Text::sprintf('COM_AUDIOARCHIVE_OWNER_DELETED', (int) $id));
		}
		return $options;
	}
}
