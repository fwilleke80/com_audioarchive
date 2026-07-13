<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder\AfterDeleteEvent;
use Joomla\CMS\Event\Finder\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Willeke\Component\Audioarchive\Administrator\Service\AudioUploadService;

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

    /** @var array<string, mixed>|null */
    private ?array $lastPreparedUpload = null;

    /** @var array<string, mixed>|null */
    private ?array $pendingPreparedUpload = null;

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

        if (!$this->getCurrentUser()->authorise('audioarchive.managefiles', 'com_audioarchive'))
        {
            $form->removeField('audio_file');
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
                $requestedCategoryId = $app->getInput()->getInt(
                    'catid',
                    (int) $params->get('default_category', 0)
                );
                $data->catid = $this->getValidCategoryId($requestedCategoryId);
                $data->access = (int) $params->get('default_access', 1);
                $data->state = (int) $params->get('default_state', 0);
                $data->created_by = (int) $this->getCurrentUser()->id;
            }
        }

        if ((int) $this->getState('clip.id') === 0)
        {
            $currentUserId = (int) $this->getCurrentUser()->id;

            if (is_array($data) && empty($data['created_by']))
            {
                $data['created_by'] = $currentUserId;
            }
            elseif (is_object($data) && empty($data->created_by))
            {
                $data->created_by = $currentUserId;
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
     * @brief Save clip metadata and an optional original audio upload.
     *
     * @param array $data Submitted form data.
     *
     * @return bool
     */
    public function save($data)
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_audioarchive');
        $uploadService = new AudioUploadService($this->getDatabase(), $params, $this->getCurrentUser());
        $files = $app->getInput()->files->get('jform', [], 'array');
        $upload = is_array($files) && isset($files['audio_file']) && is_array($files['audio_file'])
            ? $files['audio_file']
            : null;
        $preparedUpload = $this->pendingPreparedUpload;
        $this->pendingPreparedUpload = null;
        $titleWasGenerated = false;
        $recordingDateWasGenerated = false;
        $task = $app->getInput()->getCmd('task');
        $existingClipId = $task === 'save2copy' ? 0 : (int) ($data['id'] ?? 0);
        $existingRecordedAt = null;

        if ($existingClipId > 0)
        {
            $existingTable = $this->getTable();

            if ($existingTable->load($existingClipId))
            {
                $existingRecordedAt = trim((string) $existingTable->recorded_at);
            }
        }

        if ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
        {
            if (!$this->getCurrentUser()->authorise('audioarchive.managefiles', 'com_audioarchive'))
            {
                $this->setError(Text::_('JERROR_ALERTNOAUTHOR'));
                return false;
            }

            try
            {
                $preparedUpload = $uploadService->prepare($upload, $existingClipId);
                $this->lastPreparedUpload = $preparedUpload;
            }
            catch (\Throwable $exception)
            {
                $this->setError($exception->getMessage());
                return false;
            }

        }

        if ($preparedUpload !== null)
        {
            $this->lastPreparedUpload = $preparedUpload;

            if (trim((string) ($data['title'] ?? '')) === '')
            {
                $data['title'] = $uploadService->generateTitle($preparedUpload);
                $titleWasGenerated = true;
            }

            if (trim((string) ($data['recorded_at'] ?? '')) === '')
            {
                $recordingDate = $uploadService->determineRecordingDate($preparedUpload);
                $data['recorded_at'] = $recordingDate['date'];
                $data['recorded_date_source'] = $recordingDate['source'];
                $recordingDateWasGenerated = true;
            }
        }

        if (array_key_exists('recorded_at', $data) && !$recordingDateWasGenerated)
        {
            $submittedRecordedAt = trim((string) ($data['recorded_at'] ?? ''));

            if ($existingClipId === 0 || $submittedRecordedAt !== (string) $existingRecordedAt)
            {
                $data['recorded_date_source'] = $submittedRecordedAt === '' ? null : 'manual';
            }
        }

        unset($data['audio_file']);

        if ($existingClipId === 0 && $titleWasGenerated)
        {
            [$data['title'], $data['alias']] = $this->generateNewTitle(
                (int) ($data['catid'] ?? 0),
                (string) ($data['alias'] ?? ''),
                (string) $data['title']
            );
        }

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

        if ($task === 'save2copy')
        {
            [$data['title'], $data['alias']] = $this->generateNewTitle(
                (int) $data['catid'],
                (string) ($data['alias'] ?? ''),
                (string) ($data['title'] ?? '')
            );
            $data['state'] = 0;
            $data['original_filename'] = '';
            $data['duration_ms'] = 0;
            $data['metadata_status'] = 'missing';
            $data['preview_status'] = 'not_required';
            $data['waveform_status'] = 'missing';
            $data['technical_metadata'] = '{}';
            $data['play_count'] = 0;
            $data['download_count'] = 0;
        }

        if (!parent::save($data))
        {
            return false;
        }

        if ($preparedUpload === null)
        {
            $this->notifyFinderAfterSave((int) $this->getState($this->getName() . '.id'));
            return true;
        }

        $clipId = (int) $this->getState($this->getName() . '.id');
        $table = $this->getTable();

        if ($clipId <= 0 || !$table->load($clipId))
        {
            $this->setError(Text::_('COM_AUDIOARCHIVE_ERROR_SAVED_CLIP_NOT_FOUND'));
            return false;
        }

        try
        {
            $existingOriginal = $uploadService->getOriginalFile($clipId);
            $resultWarnings = [];

            if ($existingOriginal === null)
            {
                $uploadService->storeForClip($clipId, (string) $table->uuid, $preparedUpload);
                $app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_UPLOAD_STORED_SUCCESS'), 'success');
            }
            else
            {
                $result = $uploadService->replaceForClip($clipId, (string) $table->uuid, $preparedUpload);
                $resultWarnings = (array) ($result['warnings'] ?? []);
                $app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_REPLACEMENT_STORED_SUCCESS'), 'success');
            }

            foreach (array_merge((array) ($preparedUpload['metadata']['warnings'] ?? []), $resultWarnings) as $warning)
            {
                $app->enqueueMessage((string) $warning, 'warning');
            }
        }
        catch (\Throwable $exception)
        {
            if ($existingClipId === 0 && $clipId > 0)
            {
                try
                {
                    $this->removeIncompleteClip($clipId);
                }
                catch (\Throwable $cleanupException)
                {
                    Factory::getApplication()->enqueueMessage(
                        Text::sprintf('COM_AUDIOARCHIVE_WARNING_INCOMPLETE_CLIP_CLEANUP_FAILED', $cleanupException->getMessage()),
                        'warning'
                    );
                }
            }

            $this->setError(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_AFTER_SAVE', $exception->getMessage()));
            return false;
        }

        $this->notifyFinderAfterSave($clipId);

        return true;
    }


    /**
     * @brief Return information from the most recent upload prepared by this model.
     *
     * @return array<string, mixed>|null Prepared upload information.
     */
    public function getLastPreparedUpload(): ?array
    {
        return $this->lastPreparedUpload;
    }

    /**
     * @brief Save a new clip from an already validated server-side source file.
     *
     * @param array<string, mixed> $data Clip metadata.
     * @param array<string, mixed> $prepared Prepared source-file data.
     *
     * @return bool True on success.
     */
    public function savePreparedFile(array $data, array $prepared): bool
    {
        $this->pendingPreparedUpload = $prepared;

        return $this->save($data);
    }

    /**
     * @brief Return the original file record attached to the current clip.
     *
     * @param int|null $clipId Optional clip identifier.
     *
     * @return object|null File row.
     */
    public function getOriginalFile(?int $clipId = null): ?object
    {
        $clipId ??= (int) $this->getState('clip.id');
        $service = new AudioUploadService(
            $this->getDatabase(),
            ComponentHelper::getParams('com_audioarchive'),
            $this->getCurrentUser()
        );

        return $service->getOriginalFile($clipId);
    }

    /**
     * @brief Reinspect the current clip's stored original.
     *
     * @param int $clipId Clip identifier.
     *
     * @return string[] Inspector warnings.
     */
    public function reanalyseOriginal(int $clipId): array
    {
        $service = new AudioUploadService(
            $this->getDatabase(),
            ComponentHelper::getParams('com_audioarchive'),
            $this->getCurrentUser()
        );

        try
        {
            $warnings = $service->reanalyseForClip($clipId);
        }
        catch (\Throwable $exception)
        {
            $this->notifyFinderAfterSave($clipId);
            throw $exception;
        }

        $this->notifyFinderAfterSave($clipId);

        return $warnings;
    }

    /**
     * @brief Verify the current clip's stored original.
     *
     * @param int $clipId Clip identifier.
     *
     * @return array{ok:bool,message:string} Verification result.
     */
    public function verifyOriginal(int $clipId): array
    {
        $service = new AudioUploadService(
            $this->getDatabase(),
            ComponentHelper::getParams('com_audioarchive'),
            $this->getCurrentUser()
        );

        $result = $service->verifyForClip($clipId);
        $this->notifyFinderAfterSave($clipId);

        return $result;
    }


	/**
	 * @brief Apply category and tag changes to selected clips.
	 *
	 * @param int[] $ids Clip identifiers.
	 * @param array<string, mixed> $batch Batch settings.
	 *
	 * @return bool
	 */
	public function batchUpdate(array $ids, array $batch): bool
	{
		$ids = array_values(array_unique(ArrayHelper::toInteger($ids)));
		$categoryId = (int) ($batch['category_id'] ?? 0);
		$applyTags = (int) ($batch['apply_tags'] ?? 0) === 1;
		$tagMode = ($batch['tag_mode'] ?? 'add') === 'replace' ? 'replace' : 'add';
		$selectedTags = array_values(array_unique(ArrayHelper::toInteger((array) ($batch['tags'] ?? []))));

		if (!$ids || ($categoryId <= 0 && !$applyTags))
		{
			$this->setError(Text::_('COM_AUDIOARCHIVE_BATCH_NOTHING_TO_APPLY'));
			return false;
		}

		if ($categoryId > 0 && !$this->getValidCategoryId($categoryId))
		{
			$this->setError(Text::_('COM_AUDIOARCHIVE_BATCH_INVALID_CATEGORY'));
			return false;
		}

		if ($categoryId > 0 && !$this->getCurrentUser()->authorise('core.create', 'com_audioarchive.category.' . $categoryId))
		{
			$this->setError(Text::_('COM_AUDIOARCHIVE_BATCH_CATEGORY_DENIED'));
			return false;
		}

		if ($applyTags && $selectedTags && !$this->validateBatchTags($selectedTags))
		{
			$this->setError(Text::_('COM_AUDIOARCHIVE_BATCH_INVALID_TAGS'));
			return false;
		}

		$db = $this->getDatabase();
		$db->transactionStart();
		try
		{
			foreach ($ids as $id)
			{
				$asset = 'com_audioarchive.clip.' . $id;
				if (!$this->getCurrentUser()->authorise('core.edit', $asset))
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_BATCH_EDIT_DENIED', $id));
				}

				$table = $this->getTable();
				if (!$table->load($id))
				{
					throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_BATCH_CLIP_NOT_FOUND', $id));
				}

				$tagHelper = new TagsHelper();
				$tagHelper->typeAlias = $this->typeAlias;
				$currentTagString = (string) $tagHelper->getTagIds($id, $this->typeAlias);
				$currentTags = $currentTagString === '' ? [] : ArrayHelper::toInteger(explode(',', $currentTagString));

				if ($categoryId > 0)
				{
					$table->catid = $categoryId;
				}

				$table->modified = Factory::getDate()->toSql();
				$table->modified_by = (int) $this->getCurrentUser()->id;
				$table->version = max(1, (int) $table->version + 1);
				$table->clearTagsHelper();
				if (!$table->check() || !$table->store(true))
				{
					throw new \RuntimeException($table->getError());
				}

				if ($categoryId > 0)
				{
					$this->updateUcmCategory($id, $categoryId);
				}

				if ($applyTags)
				{
					$finalTags = $tagMode === 'replace'
						? $selectedTags
						: array_values(array_unique(array_merge($currentTags, $selectedTags)));
					$tagHelper->preStoreProcess($table, $finalTags);
					if (!$tagHelper->postStore($table, $finalTags, true))
					{
						throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_BATCH_TAG_STORE_FAILED', $id));
					}
				}
			}
			$db->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$db->transactionRollback();
			$this->setError($exception->getMessage());
			return false;
		}

		$this->cleanCache();

		foreach ($ids as $id)
		{
			$this->notifyFinderAfterSave($id);
		}

		return true;
	}

	/**
	 * @brief Notify enabled Finder plugins that a clip must be reindexed.
	 *
	 * @param int $clipId Clip identifier.
	 *
	 * @return void
	 */
	private function notifyFinderAfterSave(int $clipId): void
	{
		if ($clipId <= 0)
		{
			return;
		}

		try
		{
			$table = $this->getTable();

			if (!$table->load($clipId))
			{
				return;
			}

			PluginHelper::importPlugin('finder');
			Factory::getApplication()->getDispatcher()->dispatch(
				'onFinderAfterSave',
				new AfterSaveEvent('onFinderAfterSave', [
					'context' => $this->typeAlias,
					'subject' => $table,
					'isNew' => false,
				])
			);
		}
		catch (\Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf(
					'COM_AUDIOARCHIVE_WARNING_SMART_SEARCH_REINDEX_FAILED',
					$clipId,
					$exception->getMessage()
				),
				'warning'
			);
		}
	}

	/**
	 * @brief Notify Finder plugins after compensating removal of an incomplete Clip.
	 *
	 * @param int $clipId Clip identifier.
	 *
	 * @return void
	 */
	private function notifyFinderAfterDelete(int $clipId): void
	{
		if ($clipId <= 0)
		{
			return;
		}

		try
		{
			PluginHelper::importPlugin('finder');
			Factory::getApplication()->getDispatcher()->dispatch(
				'onFinderAfterDelete',
				new AfterDeleteEvent('onFinderAfterDelete', [
					'context' => $this->typeAlias,
					'subject' => (object) ['id' => $clipId],
				])
			);
		}
		catch (\Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf(
					'COM_AUDIOARCHIVE_WARNING_SMART_SEARCH_REINDEX_FAILED',
					$clipId,
					$exception->getMessage()
				),
				'warning'
			);
		}
	}

	/**
	 * @brief Verify that all requested batch tag identifiers exist.
	 *
	 * @param int[] $tagIds Tag identifiers.
	 *
	 * @return bool
	 */
	private function validateBatchTags(array $tagIds): bool
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__tags'))
			->whereIn($db->quoteName('id'), $tagIds, ParameterType::INTEGER)
			->where($db->quoteName('id') . ' > 1')
			->where($db->quoteName('published') . ' = 1');
		return (int) $db->setQuery($query)->loadResult() === count($tagIds);
	}

	/**
	 * @brief Keep Joomla UCM category data aligned after a batch move.
	 *
	 * @param int $clipId Clip identifier.
	 * @param int $categoryId Category identifier.
	 *
	 * @return void
	 */
	private function updateUcmCategory(int $clipId, int $categoryId): void
	{
		$db = $this->getDatabase();
		$typeAlias = $this->typeAlias;
		$query = $db->getQuery(true)
			->update($db->quoteName('#__ucm_content'))
			->set($db->quoteName('core_catid') . ' = :categoryId')
			->where($db->quoteName('core_content_item_id') . ' = :clipId')
			->where($db->quoteName('core_type_alias') . ' = :typeAlias')
			->bind(':categoryId', $categoryId, ParameterType::INTEGER)
			->bind(':clipId', $clipId, ParameterType::INTEGER)
			->bind(':typeAlias', $typeAlias, ParameterType::STRING);
		$db->setQuery($query)->execute();
	}

	/**
	 * @brief Permanently delete clips and their recorded managed files.
     *
     * @param array $pks Clip identifiers.
     *
     * @return bool
     */
    public function delete(&$pks)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $pks))));

        if (!parent::delete($pks))
        {
            return false;
        }

        try
        {
            $service = new AudioUploadService(
                $this->getDatabase(),
                ComponentHelper::getParams('com_audioarchive'),
                $this->getCurrentUser()
            );

            foreach ($service->removeFilesForClips($ids) as $warning)
            {
                Factory::getApplication()->enqueueMessage($warning, 'warning');
            }
        }
        catch (\Throwable $exception)
        {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_AUDIOARCHIVE_WARNING_FILE_CLEANUP_FAILED', $exception->getMessage()),
                'warning'
            );
        }

        return true;
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
     * @brief Remove a newly created clip after its original file could not be attached.
     *
     * @param int $clipId Clip identifier.
     *
     * @return void
     */
    private function removeIncompleteClip(int $clipId): void
    {
        $table = $this->getTable();

        if ($clipId > 0 && $table->load($clipId))
        {
            $table->delete($clipId);
            $this->notifyFinderAfterDelete($clipId);
        }
    }

    /**
     * @brief Validate that a category belongs to the Audio Archive component.
     *
     * @param int $categoryId Proposed Joomla category identifier.
     *
     * @return int Valid category identifier, or zero when invalid.
     */
    private function getValidCategoryId(int $categoryId): int
    {
        if ($categoryId <= 0)
        {
            return 0;
        }

        $database = $this->getDatabase();
        $extension = 'com_audioarchive';
        $query = $database->getQuery(true)
            ->select($database->quoteName('id'))
            ->from($database->quoteName('#__categories'))
            ->where($database->quoteName('id') . ' = :categoryId')
            ->where($database->quoteName('extension') . ' = :extension')
            ->whereIn($database->quoteName('published'), [0, 1])
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING);

        return (int) $database->setQuery($query)->loadResult();
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
