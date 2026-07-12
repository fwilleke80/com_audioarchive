<?php

namespace Willeke\Component\Audioarchive\Administrator\Table;

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

        $existing = new self($this->getDatabase(), $this->getDispatcher());

        if ($existing->load(['alias' => $this->alias, 'catid' => (int) $this->catid]) && (int) $existing->id !== (int) $this->id)
        {
            $this->setError(Text::_('COM_AUDIOARCHIVE_ERROR_ALIAS_EXISTS'));
            return false;
        }

        return true;
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
