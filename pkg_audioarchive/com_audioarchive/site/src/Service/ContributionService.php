<?php
namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;

\defined('_JEXEC') or die;

/** @brief Authoritative frontend contribution policy shared by forms and mutations. */
final class ContributionService
{
	/** @brief Construct policy for the authenticated visitor. */
	public function __construct(private DatabaseInterface $db, private User $user)
	{
	}

	/** @brief Resolve an authorised Upload menu; submitted Itemid never grants permission. */
	public function settings(bool $upload): Registry
	{
		if ((int) $this->user->id <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_LOGIN_REQUIRED'), 403);
		}
		$params = clone ComponentHelper::getParams('com_audioarchive');
		if (!$params->get($upload ? 'frontend_upload_enabled' : 'frontend_editing_enabled', 1))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		if ($upload)
		{
			$app = Factory::getApplication();
			$menu = $app->getMenu()->getItem($app->getInput()->getInt('Itemid'));
			if (!$this->isMenuAllowed($menu, 'upload'))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_UPLOAD_MENU_REQUIRED'), 403);
			}
			// Global allow-lists remain authoritative; menu lists only narrow them.
			foreach (['upload_category_mode', 'upload_fixed_category', 'upload_allowed_categories', 'upload_allowed_access', 'upload_default_visibility', 'upload_choose_visibility', 'upload_default_access', 'upload_default_state', 'upload_choose_state', 'upload_redirect', 'upload_intro'] as $key)
			{
				$value = $menu->getParams()->get($key, null);
				if ($value !== null && $value !== '')
				{
					$params->set($key, $value);
				}
			}
		}
		if ($upload && $params->get('upload_default_visibility', 'normal') === 'normal')
		{
			$params->set('upload_default_visibility', 'public');
		}
		return $params;
	}

	/** @brief Check publication, menu access and language for contributor navigation. */
	private function isMenuAllowed(?object $menu, string $view): bool
	{
		return $menu !== null && (string) ($menu->component ?? '') === 'com_audioarchive'
			&& ($menu->query['view'] ?? '') === $view
			&& in_array((int) $menu->access, array_map('intval', $this->user->getAuthorisedViewLevels()), true)
			&& in_array((string) ($menu->language ?? '*'), ['*', Factory::getApplication()->getLanguage()->getTag()], true);
	}

	/** @brief Find an accessible menu route; upload requires a real menu policy. */
	public function route(string $view): string
	{
		foreach (Factory::getApplication()->getMenu()->getItems('component', 'com_audioarchive') ?: [] as $menu)
		{
			if ($this->isMenuAllowed($menu, $view))
			{
				return 'index.php?option=com_audioarchive&view=' . $view . '&Itemid=' . (int) $menu->id;
			}
		}
		return $view === 'myclips' ? 'index.php?option=com_audioarchive&view=myclips' : '';
	}

	/** @brief Return globally allowed access levels narrowed by optional menu policy. */
	public function accessLevels(Registry $params): array
	{
		$global = array_values(array_filter(array_map('intval', (array) $params->get('frontend_allowed_access', [1]))));
		$menu = array_values(array_filter(array_map('intval', (array) $params->get('upload_allowed_access', []))));
		$ids = $menu ? array_values(array_intersect($global, $menu)) : $global;
		return $ids ? ($this->db->setQuery('SELECT id, title FROM ' . $this->db->quoteName('#__viewlevels') . ' WHERE id IN (' . implode(',', $ids) . ') ORDER BY ordering')->loadObjectList() ?: []) : [];
	}

	/** @brief Return viewable published categories with create permission and menu restrictions. */
	public function categories(Registry $params, int $currentCategory = 0): array
	{
		$db = $this->db;
		$levels = implode(',', array_map('intval', $this->user->getAuthorisedViewLevels())) ?: '0';
		$query = $db->getQuery(true)->select('c.id, c.title')->from($db->quoteName('#__categories', 'c'))
			->where('c.extension=' . $db->quote('com_audioarchive'))->where('c.published=1')->where('c.access IN (' . $levels . ')')
			->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories') . ' p WHERE p.extension=c.extension AND p.lft<c.lft AND p.rgt>c.rgt AND (p.published<>1 OR p.access NOT IN (' . $levels . ')))')->order('c.lft');
		$global = array_filter(array_map('intval', (array) $params->get('frontend_upload_categories', [])));
		$menu = array_filter(array_map('intval', (array) $params->get('upload_allowed_categories', [])));
		$result = [];
		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$id = (int) $row->id;
			if ($id === $currentCategory || ((!$global || in_array($id, $global, true)) && (!$menu || in_array($id, $menu, true))
				&& ($params->get('upload_category_mode', 'choose') !== 'fixed' || $id === (int) $params->get('upload_fixed_category', 0))
				&& $this->user->authorise('core.create', 'com_audioarchive.category.' . $id)))
			{
				$result[] = $row;
			}
		}
		// An existing clip may retain its category after that category becomes hidden.
		// This exception never permits moving into a hidden category.
		if ($currentCategory > 0 && !in_array($currentCategory, array_map(static fn(object $row): int => (int) $row->id, $result), true))
		{
			$current = $db->setQuery('SELECT id, title FROM ' . $db->quoteName('#__categories') . ' WHERE id=' . $currentCategory . ' AND extension=' . $db->quote('com_audioarchive'))->loadObject();
			if ($current)
			{
				array_unshift($result, $current);
			}
		}
		return $result;
	}

	/** @brief Restrict submitted fields, ownership, categories, access, moderation and tags server-side. */
	public function sanitise(array $input, Registry $params, ?object $old = null): array
	{
		if ((int) $this->user->id <= 0 || ($old && !(new ClipAccessService($this->db, $this->user))->canEdit($old)))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		$data = array_intersect_key($input, array_flip(['title', 'alias', 'description', 'recorded_at', 'tags', 'com_fields', 'normalization_mode']));
		$data['id'] = $old ? (int) $old->id : 0;
		$data['created_by'] = $old ? (int) $old->created_by : (int) $this->user->id;
		$categories = array_map(static fn(object $row): int => (int) $row->id, $this->categories($params, (int) ($old->catid ?? 0)));
		$category = !$old && $params->get('upload_category_mode', 'choose') === 'fixed' ? (int) $params->get('upload_fixed_category', 0) : (int) ($input['catid'] ?? $old->catid ?? 0);
		if (!in_array($category, $categories, true))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_CATEGORY'), 403);
		}
		$data['catid'] = $category;
		$levels = array_map(static fn(object $row): int => (int) $row->id, $this->accessLevels($params));
		$visibility = (string) ($input['visibility_mode'] ?? $old->visibility_mode ?? $params->get('upload_default_visibility', 'normal'));
		if (!$old && !$params->get('upload_choose_visibility', 1))
		{
			$visibility = (string) $params->get('upload_default_visibility', 'normal');
		}
		if ($visibility === 'private' && !$params->get('allow_private_clips', 0))
		{
			$visibility = (string) ($old->visibility_mode ?? 'normal');
		}
		if (!in_array($visibility, ['normal', 'public', 'registered', 'private'], true))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_INVALID_VISIBILITY'));
		}
		// Public/Registered are frontend presets; persisted visibility and Joomla ACL remain independent.
		if ($visibility === 'public' || $visibility === 'registered')
		{
			$input['access'] = $visibility === 'public' ? 1 : (int) $params->get('frontend_registered_access', 2);
		}
		$data['visibility_mode'] = $visibility === 'private' ? 'private' : 'normal';
		$requestedAccess = (int) ($input['access'] ?? $old->access ?? (in_array((int) $params->get('upload_default_access', 0), $levels, true) ? (int) $params->get('upload_default_access') : ($levels[0] ?? 0)));
		if (!in_array($requestedAccess, $levels, true) && (!$old || $requestedAccess !== (int) $old->access))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_ACCESS'), 403);
		}
		$data['access'] = $requestedAccess;
		$asset = $old ? 'com_audioarchive.clip.' . (int) $old->id : 'com_audioarchive.category.' . $category;
		$mayPublish = $this->user->authorise('core.edit.state', $asset)
			&& (!$old || $category === (int) $old->catid || $this->user->authorise('core.edit.state', 'com_audioarchive.category.' . $category));
		$data['state'] = $old ? (int) $old->state : 0;
		if ($mayPublish)
		{
			$state = ($old || $params->get('upload_choose_state', 0)) ? ($input['state'] ?? $data['state']) : $params->get('upload_default_state', 0);
			$data['state'] = $old && in_array((int) $state, [-2, 0, 1, 2], true) ? (int) $state : ((int) $state === 1 ? 1 : 0);
		}
		if ($old && $category !== (int) $old->catid && !$mayPublish)
		{
			$data['state'] = 0;
		}
		if ($old)
		{
			foreach (['publish_up', 'publish_down'] as $key)
			{
				$data[$key] = $mayPublish ? ($input[$key] ?? $old->$key) : $old->$key;
			}
		}
		$tags = (array) ($input['tags'] ?? []);
		if (count($tags) > 100)
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_TAGS'));
		}
		$data['tags'] = [];
		$viewLevels = array_map('intval', $this->user->getAuthorisedViewLevels());
		foreach ($tags as $tag)
		{
			if (!ctype_digit((string) $tag) || (int) $tag <= 1)
			{
				throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_TAGS'));
			}
			$row = $this->db->setQuery('SELECT id, access, published FROM ' . $this->db->quoteName('#__tags') . ' WHERE id=' . (int) $tag)->loadObject();
			if (!$row || (int) $row->published !== 1 || !in_array((int) $row->access, $viewLevels, true))
			{
				throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_CONTRIBUTION_TAGS'));
			}
			$data['tags'][] = (int) $tag;
		}
		return $data;
	}

	/** @brief Populate safe form choices; server-side sanitisation remains authoritative. */
	public function configureForm(\Joomla\CMS\Form\Form $form, Registry $params, ?object $old = null): void
	{
		$categories = $this->categories($params, (int) ($old->catid ?? 0));
		$levels = $this->accessLevels($params);
		if ($old && !in_array((int) $old->access, array_map(static fn(object $row): int => (int) $row->id, $levels), true))
		{
			$row = $this->db->setQuery('SELECT id, title FROM ' . $this->db->quoteName('#__viewlevels') . ' WHERE id=' . (int) $old->access)->loadObject();
			if ($row)
			{
				$levels[] = $row;
			}
		}
		foreach (['catid' => $categories, 'access' => $levels] as $name => $rows)
		{
			$field = new \SimpleXMLElement('<field/>');
			$field->addAttribute('name', $name);
			$field->addAttribute('type', 'list');
			$field->addAttribute('label', $name === 'catid' ? 'JCATEGORY' : 'JFIELD_ACCESS_LABEL');
			$field->addAttribute('required', 'true');
			foreach ($rows as $row)
			{
				$option = $field->addChild('option', htmlspecialchars((string) $row->title, ENT_XML1, 'UTF-8'));
				$option->addAttribute('value', (string) $row->id);
			}
			$form->setField($field, null, true, $name === 'catid' ? 'details' : 'publishing');
		}
		if (!$old && $params->get('upload_category_mode', 'choose') === 'fixed')
		{
			$form->setFieldAttribute('catid', 'type', 'hidden');
			$form->setValue('catid', null, (int) $params->get('upload_fixed_category'));
		}
		if (count($levels) === 1)
		{
			$form->setFieldAttribute('access', 'type', 'hidden');
			$form->setValue('access', null, (int) $levels[0]->id);
		}
		$registeredAccess = (int) $params->get('frontend_registered_access', 2);
		$selected = (string) $form->getValue('visibility_mode', null, $old->visibility_mode ?? $params->get('upload_default_visibility', 'normal'));
		$currentAccess = (int) $form->getValue('access', null, $old->access ?? $params->get('upload_default_access', $levels[0]->id ?? 1));
		if ($selected === 'normal' || (!$old && $selected === 'private' && !$params->get('allow_private_clips', 0)))
		{
			$selected = $currentAccess === 1 ? 'public' : ($currentAccess === $registeredAccess ? 'registered' : 'normal');
		}
		$visibilityField = new \SimpleXMLElement('<field name="visibility_mode" type="list" label="COM_AUDIOARCHIVE_VISIBILITY"/>');
		$allowedAccess = array_map(static fn(object $row): int => (int) $row->id, $levels);
		$options = [];
		if (in_array(1, $allowedAccess, true))
		{
			$options['public'] = 'COM_AUDIOARCHIVE_VISIBILITY_NORMAL';
		}
		if (in_array($registeredAccess, $allowedAccess, true))
		{
			$options['registered'] = 'COM_AUDIOARCHIVE_VISIBILITY_REGISTERED';
		}
		if ($params->get('allow_private_clips', 0) || ($old && $old->visibility_mode === 'private'))
		{
			$options['private'] = 'COM_AUDIOARCHIVE_VISIBILITY_PRIVATE';
		}
		if ($selected === 'normal')
		{
			$options['normal'] = 'COM_AUDIOARCHIVE_VISIBILITY_CUSTOM';
		}
		foreach ($options as $value => $label)
		{
			$visibilityField->addChild('option', $label)->addAttribute('value', $value);
		}
		$form->setField($visibilityField, null, true, 'publishing');
		$form->setValue('visibility_mode', null, $selected);
		// Preserve existing custom ACL choices; ordinary presets need no second audience selector.
		if ($selected !== 'normal')
		{
			$form->setFieldAttribute('access', 'type', 'hidden');
		}
		if (!$old && !$params->get('upload_choose_visibility', 1))
		{
			$form->removeField('visibility_mode');
		}
		if (!$old && (!$params->get('upload_choose_state', 0) || !array_filter($categories, fn(object $row): bool => $this->user->authorise('core.edit.state', 'com_audioarchive.category.' . (int) $row->id))))
		{
			$form->removeField('state');
		}
		if ($old && !$this->user->authorise('core.edit.state', 'com_audioarchive.clip.' . (int) $old->id))
		{
			foreach (['state', 'publish_up', 'publish_down'] as $field)
			{
				$form->removeField($field);
			}
		}
	}
}
