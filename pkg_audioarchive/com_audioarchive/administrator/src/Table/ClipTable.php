<?php

namespace Punga\Component\Audioarchive\Administrator\Table;

use Joomla\CMS\Access\Rules;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\User\CurrentUserTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Active-record table for audio clips.
 */
class ClipTable extends Table implements TaggableTableInterface, CurrentUserInterface
{
    use TaggableTableTrait;
    use CurrentUserTrait;

    /** @var bool */
    protected $_supportNullValue = true;

    /**
     * @brief Construct the clip table.
     *
     * @param DatabaseInterface $db Database connection.
     * @param DispatcherInterface|null $dispatcher Event dispatcher.
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_audioarchive.clip';
        parent::__construct('#__audioarchive_clips', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * @brief Return the Joomla content-type alias used for tags and UCM features.
     *
     * @return string Content-type alias.
     */
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }

    /**
     * @brief Bind form data to the table.
     *
     * @param array|object $src Source data.
     * @param array|string $ignore Ignored fields.
     *
     * @return bool
     */
    public function bind($src, $ignore = '')
    {
        $array = (array) $src;

        if (isset($array['params']) && is_array($array['params']))
        {
            $array['params'] = (string) new Registry($array['params']);
        }

        if (isset($array['rules']) && is_array($array['rules']))
        {
            $this->setRules(new Rules($array['rules']));
        }

        return parent::bind($array, $ignore);
    }

    /**
     * @brief Validate and normalise a clip before storage.
     *
     * @return bool
     */
    public function check()
    {
        if (!in_array($this->normalization_mode ?? 'inherit', ['inherit', 'enabled', 'disabled'], true))
        {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_AUDIOARCHIVE_NORMALIZATION_INVALID'));
            return false;
        }
        if (!parent::check())
        {
            return false;
        }

        $this->title = trim((string) $this->title);

        if ($this->title === '')
        {
            $this->setError(Text::_('COM_AUDIOARCHIVE_ERROR_TITLE_REQUIRED'));
            return false;
        }

        if (trim((string) $this->alias) === '')
        {
            $this->alias = $this->title;
        }

        $this->alias = ApplicationHelper::stringURLSafe((string) $this->alias, (string) $this->language);

        if ($this->alias === '')
        {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        if ((int) $this->catid <= 0)
        {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));
            return false;
        }

        foreach ([
            'recorded_at',
            'publish_up',
            'publish_down',
            'modified',
            'checked_out_time',
        ] as $field)
        {
            if ($this->$field === '')
            {
                $this->$field = null;
            }
        }

        if ($this->publish_up !== null && $this->publish_down !== null && $this->publish_down < $this->publish_up)
        {
            [$this->publish_up, $this->publish_down] = [$this->publish_down, $this->publish_up];
        }

        $existing = new self($this->getDatabase(), $this->getDispatcher());

        if ($existing->load(['alias' => $this->alias]) && (int) $existing->id !== (int) $this->id)
        {
            $this->setError(Text::_('COM_AUDIOARCHIVE_ERROR_ALIAS_EXISTS'));
            return false;
        }

        return true;
    }

    /**
     * @brief Store the clip while allowing nullable columns to be cleared.
     *
     * @param bool $updateNulls Whether null-valued columns should be updated.
     *
     * @return bool
     */
    public function store($updateNulls = true)
    {
        
		$db = $this->getDatabase();
		$actor = $this->getCurrentUser();
		$old = !empty($this->id) ? $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__audioarchive_clips'))->where('id = ' . (int) $this->id))->loadObject() : null;
		$oldOwner = $old ? (int) $old->created_by : (int) $actor->id;
		$newOwner = isset($this->created_by) ? (int) $this->created_by : $oldOwner;
		if ($newOwner !== $oldOwner && (!$actor->authorise('audioarchive.change.owner', 'com_audioarchive') || !Factory::getApplication()->isClient('administrator')))
		{
			$this->setError(Text::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}
		if ($newOwner > 0 && $newOwner !== $oldOwner && !(int) $db->setQuery('SELECT id FROM ' . $db->quoteName('#__users') . ' WHERE id = ' . $newOwner)->loadResult())
		{
			$this->setError(Text::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}
		$this->created_by = $newOwner;
		if (!in_array($this->visibility_mode ?? 'normal', ['normal', 'private'], true))
		{
			$this->setError(Text::_('COM_AUDIOARCHIVE_INVALID_VISIBILITY'));
			return false;
		}
		$access = new \Punga\Component\Audioarchive\Administrator\Service\ClipAccessService($db, $actor);
		if ($old && !$access->canAccessPrivate($old))
		{
			$this->setError(Text::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}

		$quota = new \Punga\Component\Audioarchive\Administrator\Service\UserQuotaService($db, \Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive'), $actor);
		try
		{
			return $quota->withOwnerLocks([$oldOwner, $newOwner], function () use ($quota, $db, $old, $oldOwner, $newOwner, $updateNulls): bool
			{
				if ($old)
				{
					$current = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__audioarchive_clips') . ' WHERE id=' . (int) $this->id)->loadObject();
					if (!$current || !(new \Punga\Component\Audioarchive\Administrator\Service\ClipAccessService($db, $this->getCurrentUser()))->canAccessPrivate($current))
					{
						throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
					}
				}
				// Detect concurrent reassignment instead of checking the wrong owner's quota.
				if ($old && (int) $db->setQuery('SELECT created_by FROM ' . $db->quoteName('#__audioarchive_clips') . ' WHERE id = ' . (int) $this->id)->loadResult() !== $oldOwner)
				{
					throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_QUOTA_BUSY'));
				}
				$bytes = $old && $oldOwner !== $newOwner ? (int) $db->setQuery('SELECT COALESCE(SUM(file_size), 0) FROM ' . $db->quoteName('#__audioarchive_files') . ' WHERE file_role = ' . $db->quote('original') . ' AND clip_id = ' . (int) $this->id)->loadResult() : 0;
				$confirmed = Factory::getApplication()->isClient('administrator') && Factory::getApplication()->getInput()->post->getInt('quota_override_confirm', 0) === 1;
				$quota->assertIncrease($newOwner, $bytes, !$old || $oldOwner !== $newOwner ? 1 : 0, $confirmed);
				if ($old && $oldOwner !== $newOwner)
				{
					$this->modified_by = (int) $this->getCurrentUser()->id;
				}
				return parent::store($updateNulls);
			});
		}
		catch (\Throwable $exception)
		{
			$this->setError($exception->getMessage());
			return false;
		}
    }

    /**
     * @brief Return the ACL asset name.
     *
     * @return string
     */
    protected function _getAssetName()
    {
        return 'com_audioarchive.clip.' . (int) $this->id;
    }

    /**
     * @brief Return the title used for the ACL asset.
     *
     * @return string
     */
    protected function _getAssetTitle()
    {
        return (string) $this->title;
    }

    /**
     * @brief Find the parent category asset.
     *
     * @param Table|null $table Optional table instance.
     * @param int|null $id Optional item id.
     *
     * @return int
     */
    protected function _getAssetParentId(?Table $table = null, $id = null)
    {
        if ((int) $this->catid > 0)
        {
            $categoryId = (int) $this->catid;
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('asset_id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = :catid')
                ->bind(':catid', $categoryId, ParameterType::INTEGER);

            $assetId = (int) $db->setQuery($query)->loadResult();

            if ($assetId > 0)
            {
                return $assetId;
            }
        }

        return parent::_getAssetParentId($table, $id);
    }
}
