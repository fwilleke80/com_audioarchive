<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * @brief Administrator list model for clips.
 */
class ClipsModel extends ListModel
{
    /**
     * @brief Construct the list model.
     *
     * @param array $config Model configuration.
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'state', 'a.state',
                'catid', 'a.catid', 'category_title',
                'access', 'a.access', 'access_level',
                'duration_ms', 'a.duration_ms',
                'recorded_at', 'a.recorded_at',
                'uploaded_at', 'a.uploaded_at',
                'play_count', 'a.play_count',
                'download_count', 'a.download_count',
            ];
        }

        parent::__construct($config);
    }

    /**
     * @brief Build the clip-list query.
     *
     * @return QueryInterface
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'a.*',
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('vl.title', 'access_level'),
                $db->quoteName('u.name', 'editor'),
            ])
            ->from($db->quoteName('#__audioarchive_clips', 'a'))
            ->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->leftJoin($db->quoteName('#__viewlevels', 'vl') . ' ON vl.id = a.access')
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.checked_out');

        $state = $this->getState('filter.state');

        if ($state !== '')
        {
            $state = (int) $state;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER);
        }
        else
        {
            $query->where($db->quoteName('a.state') . ' >= 0');
        }

        $categoryId = (int) $this->getState('filter.category_id');

        if ($categoryId > 0)
        {
            $query->where($db->quoteName('a.catid') . ' = :catid')
                ->bind(':catid', $categoryId, ParameterType::INTEGER);
        }

        $access = (int) $this->getState('filter.access');

        if ($access > 0)
        {
            $query->where($db->quoteName('a.access') . ' = :access')
                ->bind(':access', $access, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '')
        {
            if (stripos($search, 'id:') === 0)
            {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
            }
            else
            {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :searchTitle'
                    . ' OR ' . $db->quoteName('a.alias') . ' LIKE :searchAlias'
                    . ' OR ' . $db->quoteName('a.original_filename') . ' LIKE :searchFilename)'
                )
                    ->bind(':searchTitle', $search)
                    ->bind(':searchAlias', $search)
                    ->bind(':searchFilename', $search);
            }
        }

        $ordering = $this->state->get('list.ordering', 'a.uploaded_at');
        $direction = strtoupper($this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $query->order($db->escape($ordering) . ' ' . $direction);

        return $query;
    }

    /**
     * @brief Populate filters and ordering from the request.
     *
     * @param string|null $ordering Default ordering.
     * @param string|null $direction Default direction.
     *
     * @return void
     */
    protected function populateState($ordering = 'a.uploaded_at', $direction = 'DESC')
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
        $this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', ''));
        $this->setState('filter.category_id', $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id', 0, 'int'));
        $this->setState('filter.access', $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', 0, 'int'));
        parent::populateState($ordering, $direction);
    }
}
