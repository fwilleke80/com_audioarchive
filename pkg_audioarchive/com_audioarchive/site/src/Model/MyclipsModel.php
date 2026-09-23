<?php
namespace Punga\Component\Audioarchive\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;

\defined('_JEXEC') or die;

/** @brief Paginated owner workspace; ownership is never a user-supplied filter. */
class MyclipsModel extends ListModel
{
	/** @brief Populate bounded owner-workspace filters without inheriting public Archive state. */
	protected function populateState($ordering = 'a.created', $direction = 'DESC')
	{
		$app = Factory::getApplication();
		if ((int) $app->getIdentity()->id <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_LOGIN_REQUIRED'), 403);
		}
		$input = $app->getInput();
		$this->setState('list.limit', 20);
		$this->setState('list.start', max(0, $input->getInt('limitstart')));
		$this->setState('filter.search', $input->getString('search', ''));
		$this->setState('filter.category', $input->getInt('category'));
		$this->setState('filter.state', $input->getString('state', ''));
		$this->setState('filter.visibility', $input->getCmd('visibility', ''));
		$this->setState('filter.processing', $input->getCmd('processing', ''));
		$this->setState('filter.order', $input->getCmd('order', 'newest'));
	}

	/** @brief Build SQL for only the current user's retained clips, including private and unpublished. */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$id = (int) Factory::getApplication()->getIdentity()->id;
		$query = $db->getQuery(true)->select('a.*, c.title AS category_title, v.title AS access_title, f.file_size, f.is_available, f.mime_type')
			->from($db->quoteName('#__audioarchive_clips', 'a'))
			->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id=a.catid')
			->leftJoin($db->quoteName('#__viewlevels', 'v') . ' ON v.id=a.access')
			->leftJoin($db->quoteName('#__audioarchive_files', 'f') . ' ON f.clip_id=a.id AND f.file_role=' . $db->quote('original'))
			->where('a.created_by=' . $id)->where($id > 0 ? '1=1' : '1=0');
		$search = trim((string) $this->getState('filter.search'));
		if ($search !== '')
		{
			$query->where('a.title LIKE ' . $db->quote('%' . $search . '%'));
		}
		$category = (int) $this->getState('filter.category');
		if ($category > 0)
		{
			$query->where('a.catid=' . $category);
		}
		$state = (string) $this->getState('filter.state');
		if (in_array($state, ['0', '1', '2', '-2'], true))
		{
			$query->where('a.state=' . (int) $state);
		}
		$visibility = $this->getState('filter.visibility');
		if ($visibility === 'registered')
		{
			$query->where("a.visibility_mode='normal'")->where('a.access=' . (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive')->get('frontend_registered_access', 2));
		}
		if (in_array($visibility, ['normal', 'public'], true))
		{
			$query->where("a.visibility_mode='normal'")->where('a.access=1');
		}
		if ($visibility === 'private')
		{
			$query->where('a.visibility_mode=' . $db->quote($visibility));
		}
		$processing = $this->getState('filter.processing');
		if (in_array($processing, ['available', 'missing', 'failed', 'pending', 'stale'], true))
		{
			$conditions = [];
			foreach (['metadata_status', 'preview_status', 'waveform_status', 'spectrogram_status', 'frequency_profile_status'] as $column)
			{
				$conditions[] = $processing === 'available'
					? 'a.' . $column . " IN ('available', 'not_required', 'disabled')"
					: 'a.' . $column . '=' . $db->quote($processing);
			}
			$query->where('(' . implode($processing === 'available' ? ' AND ' : ' OR ', $conditions) . ')');
		}
		$order = ['newest' => 'a.created DESC, a.id DESC', 'oldest' => 'a.created ASC, a.id ASC', 'title' => 'a.title ASC, a.id ASC', 'size' => 'f.file_size DESC, a.id DESC'];
		return $query->order($order[$this->getState('filter.order')] ?? $order['newest']);
	}

	/** @brief Return categories represented by the owner's own clips, even if access later changed. */
	public function getCategories(): array
	{
		$db = $this->getDatabase();
		return $db->setQuery('SELECT DISTINCT c.id, c.title FROM ' . $db->quoteName('#__categories') . ' c JOIN ' . $db->quoteName('#__audioarchive_clips') . ' a ON a.catid=c.id WHERE a.created_by=' . (int) Factory::getApplication()->getIdentity()->id . ' ORDER BY c.title')->loadObjectList() ?: [];
	}
}
