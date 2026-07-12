<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Administrator model for one clip.
 */
class ClipModel extends AdminModel
{

    /** @var string */
    protected $text_prefix = 'COM_AUDIOARCHIVE';

    /** @var string */
    public $typeAlias = 'com_audioarchive.clip';

    /**
     * @brief Return the edit form.
     *
     * @param array $data Form data.
     * @param bool $loadData Load model data.
     *
     * @return Form|false
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_audioarchive.clip',
            'clip',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (!$form)
        {
            return false;
        }

        if (!$this->canEditState((object) $data))
        {
            foreach (['state', 'access', 'publish_up', 'publish_down'] as $field)
            {
                $form->setFieldAttribute($field, 'disabled', 'true');
                $form->setFieldAttribute($field, 'filter', 'unset');
            }
        }

        if (!$this->getCurrentUser()->authorise('core.manage', 'com_users'))
        {
            $form->setFieldAttribute('created_by', 'disabled', 'true');
            $form->setFieldAttribute('created_by', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * @brief Load data into the edit form.
     *
     * @return mixed
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_audioarchive.edit.clip.data', []);

        if (empty($data))
        {
            $data = $this->getItem();

            if ((int) $this->getState('clip.id') === 0)
            {
                $params = ComponentHelper::getParams('com_audioarchive');
                $data->catid = $app->getInput()->getInt('catid', (int) $params->get('default_category', 0));
                $data->access = (int) $params->get('default_access', 1);
                $data->state = (int) $params->get('default_state', 0);
            }
        }

        $this->preprocessData('com_audioarchive.clip', $data);

        return $data;
    }

    /**
     * @brief Load one clip and its selected tag identifiers.
     *
     * @param int|null $pk Primary key.
     *
     * @return mixed
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && !empty($item->id))
        {
            $item->tags = new TagsHelper();
            $item->tags->getTagIds((int) $item->id, $this->typeAlias);
        }

        if ($item && isset($item->params))
        {
            $item->params = (new Registry((string) $item->params))->toArray();
        }

        return $item;
    }

    /**
     * @brief Save clip metadata while protecting managed technical fields.
     *
     * @param array $data Submitted form data.
     *
     * @return bool
     */
    public function save($data)
    {
        foreach ([
            'uuid',
            'original_filename',
            'duration_ms',
            'uploaded_at',
            'metadata_status',
            'preview_status',
            'waveform_status',
            'technical_metadata',
            'play_count',
            'download_count',
        ] as $managedField)
        {
            unset($data[$managedField]);
        }

        if (Factory::getApplication()->getInput()->getCmd('task') === 'save2copy')
        {
            $original = $this->getTable();
            $original->load((int) ($data['id'] ?? 0));
            [$data['title'], $data['alias']] = $this->generateNewTitle(
                (int) $data['catid'],
                (string) ($data['alias'] ?? ''),
                (string) ($data['title'] ?? '')
            );
            $data['state'] = 0;
        }

        return parent::save($data);
    }

    /**
     * @brief Prepare database values before saving.
     *
     * @param Table $table Clip table.
     *
     * @return void
     */
    protected function prepareTable($table)
    {
        $now = Factory::getDate()->toSql();
        $user = $this->getCurrentUser();

        if (empty($table->id))
        {
            $table->uuid = self::createUuid();
            $table->created = $now;
            $table->created_by = (int) $user->id;
            $table->uploaded_at = $now;
            $table->technical_metadata = '{}';
            $table->params = '{}';
            $table->version = 1;
        }
        else
        {
            $table->modified = $now;
            $table->modified_by = (int) $user->id;
            $table->version = max(1, (int) $table->version + 1);
        }
    }

    /**
     * @brief Check whether a clip may be deleted.
     *
     * @param object $record Clip record.
     *
     * @return bool
     */
    protected function canDelete($record)
    {
        if (empty($record->id) || (int) $record->state !== -2)
        {
            return false;
        }

        return $this->getCurrentUser()->authorise('core.delete', 'com_audioarchive.clip.' . (int) $record->id);
    }

    /**
     * @brief Check whether the publication state may be edited.
     *
     * @param object $record Clip record.
     *
     * @return bool
     */
    protected function canEditState($record)
    {
        $user = $this->getCurrentUser();

        if (!empty($record->id))
        {
            return $user->authorise('core.edit.state', 'com_audioarchive.clip.' . (int) $record->id);
        }

        if (!empty($record->catid))
        {
            return $user->authorise('core.edit.state', 'com_audioarchive.category.' . (int) $record->catid);
        }

        return parent::canEditState($record);
    }

    /**
     * @brief Create an RFC 4122 version-4 UUID.
     *
     * @return string
     */
    private static function createUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
