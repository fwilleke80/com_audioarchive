<?php
namespace Punga\Component\Audioarchive\Administrator\View\Quotas;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Administrator quota rules and user overrides. */
class HtmlView extends BaseHtmlView
{
	/** @var array Group rules. */
	public array $rules = [];
	/** @var array Joomla groups. */
	public array $groups = [];

	/** @brief Display quota management to component option administrators. */
	public function display($tpl = null)
	{
		if (!Factory::getApplication()->getIdentity()->authorise('core.options', 'com_audioarchive'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$this->groups = $db->setQuery('SELECT id, title FROM ' . $db->quoteName('#__usergroups') . ' ORDER BY lft')->loadObjectList();
		$this->rules = $db->setQuery('SELECT q.*, g.title FROM ' . $db->quoteName('#__audioarchive_group_quotas') . ' q LEFT JOIN ' . $db->quoteName('#__usergroups') . ' g ON g.id=q.group_id ORDER BY g.lft')->loadObjectList();
		ToolbarHelper::title(Text::_('COM_AUDIOARCHIVE_QUOTAS'), 'users');
		ToolbarHelper::preferences('com_audioarchive');
		parent::display($tpl);
	}
}
