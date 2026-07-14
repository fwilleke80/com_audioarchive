<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

return new class () implements InstallerScriptInterface
{
	private const SCHEMA_VERSION = '0.2.0';

	private const CATEGORY_MENU_LINK = 'index.php?option=com_categories&view=categories&extension=com_audioarchive';

	private const CONTENT_TYPE_ALIAS = 'com_audioarchive.clip';

	private const DEFAULT_CATEGORY_ALIAS = 'uncategorised';

	private const DEFAULT_CATEGORY_SEEDED_PARAM = '_uncategorised_seeded';

	/** @var string[] */
	private const REQUIRED_TABLES = [
		'audioarchive_clips',
		'audioarchive_files',
		'audioarchive_waveforms',
		'audioarchive_jobs',
	];

	/**
	 * @brief Complete an initial component installation.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 * @return bool True when installation repairs and defaults are ready.
	 */
	public function install(InstallerAdapter $adapter): bool
	{
		return $this->repairDatabaseSchema()
			&& $this->repairAdministratorMenu();
	}

	/**
	 * @brief Complete a component update.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 * @return bool True when update repairs and defaults are ready.
	 */
	public function update(InstallerAdapter $adapter): bool
	{
		return $this->repairDatabaseSchema()
			&& $this->repairAdministratorMenu();
	}

	/**
	 * @brief Remove component data and optionally remove managed media files.
	 *
	 * Joomla itself removes the component files, administrator menu, component
	 * asset and categories after this method returns. This method removes the
	 * component-owned tables and Joomla integration rows. Global tag records are
	 * deliberately retained; only Audio Archive tag associations are removed.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 * @return bool True when database cleanup completed.
	 */
	public function uninstall(InstallerAdapter $adapter): bool
	{
		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$params = $this->loadComponentParameters($database);
			$mediaEntries = $this->collectManagedMediaEntries($database, $params);

			$this->removeJoomlaIntegrationData($database);
			$this->dropComponentTables($database);

			Factory::getApplication()->enqueueMessage(
				Text::_('COM_AUDIOARCHIVE_UNINSTALL_DATABASE_REMOVED'),
				'success'
			);

			if ((int) $params->get('remove_media_on_uninstall', 0) === 1)
			{
				$result = $this->removeManagedMediaFiles($mediaEntries);

				if ($result['failed'] > 0)
				{
					Factory::getApplication()->enqueueMessage(
						Text::sprintf(
							'COM_AUDIOARCHIVE_UNINSTALL_MEDIA_PARTIAL',
							$result['removed'],
							$result['failed']
						),
						'warning'
					);
				}
				else
				{
					Factory::getApplication()->enqueueMessage(
						Text::sprintf('COM_AUDIOARCHIVE_UNINSTALL_MEDIA_REMOVED', $result['removed']),
						'success'
					);
				}
			}
			else
			{
				$roots = array_values(array_filter(array_unique(array_column($mediaEntries, 'root'))));
				$rootList = $roots ? implode(', ', $roots) : Text::_('COM_AUDIOARCHIVE_UNINSTALL_MEDIA_NO_PATHS');

				Factory::getApplication()->enqueueMessage(
					Text::sprintf('COM_AUDIOARCHIVE_UNINSTALL_MEDIA_PRESERVED', $rootList),
					'notice'
				);
			}

			return true;
		}
		catch (Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_AUDIOARCHIVE_UNINSTALL_FAILED', $exception->getMessage()),
				'error'
			);

			return false;
		}
	}

	/**
	 * @brief Validate the environment before installation begins.
	 *
	 * @param string $type Installation operation type.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 * @return bool Always true for the current development milestone.
	 */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Finish an installation operation.
	 *
	 * @param string $type Installation operation type.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 * @return bool True when the administrator menu is valid.
	 */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if (!in_array($type, ['install', 'update', 'discover_install'], true))
		{
			return true;
		}

		return $this->ensureDefaultCategory()
			&& $this->repairAdministratorMenu();
	}

	/**
	 * @brief Create the initial Audio Archive Uncategorised category once.
	 *
	 * The internal seed flag prevents later component updates from recreating the
	 * category after an administrator intentionally deletes it. Existing valid
	 * default-category selections are preserved.
	 *
	 * @return bool True when the category seed operation completed.
	 */
	private function ensureDefaultCategory(): bool
	{
		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$params = $this->loadComponentParameters($database);

			if ((int) $params->get(self::DEFAULT_CATEGORY_SEEDED_PARAM, 0) === 1)
			{
				return true;
			}

			$categoryId = $this->findUncategorisedCategoryId($database);
			$created = false;

			if ($categoryId <= 0)
			{
				$categoryId = $this->createUncategorisedCategory($database);
				$created = true;
			}

			$defaultCategoryId = (int) $params->get('default_category', 0);

			if (!$this->isUsableAudioArchiveCategory($database, $defaultCategoryId))
			{
				$params->set('default_category', $categoryId);
			}

			$params->set(self::DEFAULT_CATEGORY_SEEDED_PARAM, 1);
			$this->storeComponentParameters($database, $params);

			if ($created)
			{
				Factory::getApplication()->enqueueMessage(
					Text::_('COM_AUDIOARCHIVE_INSTALL_DEFAULT_CATEGORY_CREATED'),
					'success'
				);
			}

			return true;
		}
		catch (Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_AUDIOARCHIVE_INSTALL_DEFAULT_CATEGORY_FAILED', $exception->getMessage()),
				'error'
			);

			return false;
		}
	}

	/**
	 * @brief Find an existing non-trashed Uncategorised category.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return int Category identifier, or zero when none exists.
	 */
	private function findUncategorisedCategoryId(DatabaseInterface $database): int
	{
		$query = $database->getQuery(true)
			->select($database->quoteName('id'))
			->from($database->quoteName('#__categories'))
			->where($database->quoteName('extension') . ' = ' . $database->quote('com_audioarchive'))
			->where($database->quoteName('published') . ' <> -2')
			->extendWhere(
				'AND',
				[
					$database->quoteName('alias') . ' = ' . $database->quote(self::DEFAULT_CATEGORY_ALIAS),
					$database->quoteName('title') . ' = ' . $database->quote(Text::_('COM_AUDIOARCHIVE_CATEGORY_UNCATEGORISED_TITLE')),
				],
				'OR'
			)
			->order($database->quoteName('id') . ' ASC');

		return (int) $database->setQuery($query, 0, 1)->loadResult();
	}

	/**
	 * @brief Create a top-level Audio Archive Uncategorised category.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return int Identifier of the new category.
	 */
	private function createUncategorisedCategory(DatabaseInterface $database): int
	{
		$alias = self::DEFAULT_CATEGORY_ALIAS;

		if ($this->categoryAliasExists($database, $alias))
		{
			$alias = 'uncategorised-audioarchive';
		}

		$category = new Category($database);
		$identity = Factory::getApplication()->getIdentity();

		if ($identity !== null)
		{
			$category->setCurrentUser($identity);
		}

		$category->setLocation(1, 'last-child');

		$data = [
			'parent_id' => 1,
			'extension' => 'com_audioarchive',
			'title' => Text::_('COM_AUDIOARCHIVE_CATEGORY_UNCATEGORISED_TITLE'),
			'alias' => $alias,
			'path' => $alias,
			'description' => '',
			'published' => 1,
			'access' => 1,
			'language' => '*',
			'params' => [],
			'metadata' => [],
		];

		if (!$category->bind($data) || !$category->check() || !$category->store())
		{
			$error = $category->getError();
			throw new RuntimeException($error ?: 'The default category could not be stored.');
		}

		return (int) $category->id;
	}

	/**
	 * @brief Determine whether a category alias already exists for Audio Archive.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param string $alias Alias to check.
	 * @return bool True when the alias exists in any publication state.
	 */
	private function categoryAliasExists(DatabaseInterface $database, string $alias): bool
	{
		$query = $database->getQuery(true)
			->select('COUNT(*)')
			->from($database->quoteName('#__categories'))
			->where($database->quoteName('extension') . ' = ' . $database->quote('com_audioarchive'))
			->where($database->quoteName('alias') . ' = ' . $database->quote($alias));

		return (int) $database->setQuery($query)->loadResult() > 0;
	}

	/**
	 * @brief Validate a configured default category.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param int $categoryId Category identifier.
	 * @return bool True for a non-trashed Audio Archive category.
	 */
	private function isUsableAudioArchiveCategory(DatabaseInterface $database, int $categoryId): bool
	{
		if ($categoryId <= 0)
		{
			return false;
		}

		$query = $database->getQuery(true)
			->select('COUNT(*)')
			->from($database->quoteName('#__categories'))
			->where($database->quoteName('id') . ' = ' . $categoryId)
			->where($database->quoteName('extension') . ' = ' . $database->quote('com_audioarchive'))
			->where($database->quoteName('published') . ' <> -2');

		return (int) $database->setQuery($query)->loadResult() > 0;
	}

	/**
	 * @brief Repair the administrator Categories menu URL used for active-path matching.
	 *
	 * @return bool True when the menu item is valid or does not exist yet.
	 */
	private function repairAdministratorMenu(): bool
	{
		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $database->getQuery(true)
				->update($database->quoteName('#__menu'))
				->set($database->quoteName('link') . ' = ' . $database->quote(self::CATEGORY_MENU_LINK))
				->where($database->quoteName('client_id') . ' = 1')
				->where($database->quoteName('menutype') . ' = ' . $database->quote('main'))
				->where($database->quoteName('title') . ' = ' . $database->quote('COM_AUDIOARCHIVE_SUBMENU_CATEGORIES'));

			$database->setQuery($query)->execute();

			return true;
		}
		catch (Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_AUDIOARCHIVE_INSTALL_MENU_FAILED', $exception->getMessage()),
				'warning'
			);

			return false;
		}
	}

	/**
	 * @brief Ensure all Audio Archive tables and the Joomla content type exist.
	 *
	 * @return bool True when the schema is complete.
	 */
	private function repairDatabaseSchema(): bool
	{
		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$schemaWasComplete = $this->hasRequiredTables($database);

			if (!$schemaWasComplete)
			{
				$this->executeSchemaFile($database);
			}

			$this->repairCheckoutColumns($database);
			$this->ensureFileRoleUniqueIndex($database);
			$this->ensureContentType($database);
			$this->recordSchemaVersion($database);

			if (!$this->hasRequiredTables($database))
			{
				throw new RuntimeException('One or more required database tables are still missing.');
			}

			if (!$schemaWasComplete)
			{
				Factory::getApplication()->enqueueMessage(
					Text::_('COM_AUDIOARCHIVE_INSTALL_SCHEMA_REPAIRED'),
					'success'
				);
			}

			return true;
		}
		catch (Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_AUDIOARCHIVE_INSTALL_SCHEMA_FAILED', $exception->getMessage()),
				'error'
			);

			return false;
		}
	}


	/**
	 * @brief Ensure that each clip has at most one file for a given role.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function ensureFileRoleUniqueIndex(DatabaseInterface $database): void
	{
		if (!$this->tableExists($database, 'audioarchive_files'))
		{
			return;
		}

		$table = $database->replacePrefix('#__audioarchive_files');
		$query = 'SHOW INDEX FROM ' . $database->quoteName($table)
			. ' WHERE ' . $database->quoteName('Key_name')
			. ' = ' . $database->quote('idx_audioarchive_file_clip_role');

		if ($database->setQuery($query)->loadObject() !== null)
		{
			return;
		}

		$query = 'ALTER TABLE ' . $database->quoteName($table)
			. ' ADD UNIQUE KEY ' . $database->quoteName('idx_audioarchive_file_clip_role')
			. ' (' . $database->quoteName('clip_id') . ', ' . $database->quoteName('file_role') . ')';
		$database->setQuery($query)->execute();
	}

	/**
	 * @brief Determine whether every required Audio Archive table exists.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return bool True when all required tables exist.
	 */
	private function hasRequiredTables(DatabaseInterface $database): bool
	{
		$tableNames = array_map('strtolower', $database->getTableList());
		$prefix = strtolower($database->getPrefix());

		foreach (self::REQUIRED_TABLES as $tableName)
		{
			if (!in_array($prefix . strtolower($tableName), $tableNames, true))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @brief Determine whether a database table exists.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param string $tableName Table name without the Joomla prefix placeholder.
	 * @return bool True when the table exists.
	 */
	private function tableExists(DatabaseInterface $database, string $tableName): bool
	{
		$tableNames = array_map('strtolower', $database->getTableList());
		$physicalName = strtolower($database->getPrefix() . $tableName);

		return in_array($physicalName, $tableNames, true);
	}

	/**
	 * @brief Execute the idempotent component schema file.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function executeSchemaFile(DatabaseInterface $database): void
	{
		$schemaPath = JPATH_ADMINISTRATOR
			. '/components/com_audioarchive/sql/install.mysql.utf8mb4.sql';

		if (!is_file($schemaPath) || !is_readable($schemaPath))
		{
			throw new RuntimeException('The Audio Archive schema file is missing or unreadable.');
		}

		$sql = file_get_contents($schemaPath);

		if ($sql === false)
		{
			throw new RuntimeException('The Audio Archive schema file could not be read.');
		}

		foreach (Installer::splitSql($sql) as $query)
		{
			$query = trim($query);

			if ($query === '')
			{
				continue;
			}

			$database->setQuery($query)->execute();
		}
	}

	/**
	 * @brief Align checkout columns with Joomla's nullable check-in semantics.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function repairCheckoutColumns(DatabaseInterface $database): void
	{
		$queries = [
			'ALTER TABLE ' . $database->quoteName('#__audioarchive_clips')
				. ' MODIFY ' . $database->quoteName('checked_out') . ' int unsigned DEFAULT NULL',
			'ALTER TABLE ' . $database->quoteName('#__audioarchive_clips')
				. ' MODIFY ' . $database->quoteName('checked_out_time') . ' datetime DEFAULT NULL',
		];

		foreach ($queries as $query)
		{
			$database->setQuery($query)->execute();
		}
	}

	/**
	 * @brief Register or repair the Audio Archive clip content type with Joomla.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function ensureContentType(DatabaseInterface $database): void
	{
		$tableDefinition = json_encode(
			[
				'special' => [
					'dbtable' => '#__audioarchive_clips',
					'key' => 'id',
					'type' => 'ClipTable',
					'prefix' => 'Willeke\\Component\\Audioarchive\\Administrator\\Table\\',
					'config' => 'array()',
				],
				'common' => [
					'dbtable' => '#__ucm_content',
					'key' => 'ucm_id',
					'type' => 'Corecontent',
					'prefix' => 'Joomla\\CMS\\Table\\',
					'config' => 'array()',
				],
			],
			JSON_THROW_ON_ERROR
		);

		$fieldMappings = json_encode(
			[
				'common' => [
					'core_content_item_id' => 'id',
					'core_title' => 'title',
					'core_state' => 'state',
					'core_alias' => 'alias',
					'core_created_user_id' => 'created_by',
					'core_created_by_alias' => 'null',
					'core_created_time' => 'created',
					'core_modified_time' => 'modified',
					'core_body' => 'description',
					'core_hits' => 'play_count',
					'core_publish_up' => 'publish_up',
					'core_publish_down' => 'publish_down',
					'core_access' => 'access',
					'core_params' => 'params',
					'core_featured' => 'null',
					'core_metadata' => 'null',
					'core_language' => 'language',
					'core_images' => 'null',
					'core_urls' => 'null',
					'core_version' => 'version',
					'core_ordering' => 'ordering',
					'core_metakey' => 'null',
					'core_metadesc' => 'null',
					'core_catid' => 'catid',
					'asset_id' => 'asset_id',
					'note' => 'null',
				],
				'special' => new stdClass(),
			],
			JSON_THROW_ON_ERROR
		);

		$query = $database->getQuery(true)
			->select($database->quoteName('type_id'))
			->from($database->quoteName('#__content_types'))
			->where($database->quoteName('type_alias') . ' = ' . $database->quote(self::CONTENT_TYPE_ALIAS));

		$existingId = (int) $database->setQuery($query)->loadResult();

		if ($existingId > 0)
		{
			$query = $database->getQuery(true)
				->update($database->quoteName('#__content_types'))
				->set([
					$database->quoteName('type_title') . ' = ' . $database->quote('Audio Archive Clip'),
					$database->quoteName('table') . ' = ' . $database->quote($tableDefinition),
					$database->quoteName('rules') . ' = ' . $database->quote(''),
					$database->quoteName('field_mappings') . ' = ' . $database->quote($fieldMappings),
				])
				->where($database->quoteName('type_id') . ' = ' . $existingId);
		}
		else
		{
			$query = $database->getQuery(true)
				->insert($database->quoteName('#__content_types'))
				->columns([
					$database->quoteName('type_title'),
					$database->quoteName('type_alias'),
					$database->quoteName('table'),
					$database->quoteName('rules'),
					$database->quoteName('field_mappings'),
					$database->quoteName('router'),
					$database->quoteName('content_history_options'),
				])
				->values(implode(', ', [
					$database->quote('Audio Archive Clip'),
					$database->quote(self::CONTENT_TYPE_ALIAS),
					$database->quote($tableDefinition),
					$database->quote(''),
					$database->quote($fieldMappings),
					$database->quote(''),
					$database->quote('{}'),
				]));
		}

		$database->setQuery($query)->execute();
	}

	/**
	 * @brief Load the component configuration from Joomla's extension row.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return Registry Component parameters.
	 */
	private function loadComponentParameters(DatabaseInterface $database): Registry
	{
		$query = $database->getQuery(true)
			->select($database->quoteName('params'))
			->from($database->quoteName('#__extensions'))
			->where($database->quoteName('type') . ' = ' . $database->quote('component'))
			->where($database->quoteName('element') . ' = ' . $database->quote('com_audioarchive'));

		$params = $database->setQuery($query)->loadResult();

		return new Registry(is_string($params) ? $params : '{}');
	}

	/**
	 * @brief Store component configuration in Joomla's extension row.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @return void
	 */
	private function storeComponentParameters(DatabaseInterface $database, Registry $params): void
	{
		$query = $database->getQuery(true)
			->update($database->quoteName('#__extensions'))
			->set($database->quoteName('params') . ' = ' . $database->quote($params->toString()))
			->where($database->quoteName('type') . ' = ' . $database->quote('component'))
			->where($database->quoteName('element') . ' = ' . $database->quote('com_audioarchive'));

		$database->setQuery($query)->execute();
	}

	/**
	 * @brief Collect managed media records before component tables are removed.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @return array<int, array{root:string, key:string}> Managed entries.
	 */
	private function collectManagedMediaEntries(DatabaseInterface $database, Registry $params): array
	{
		$roots = [
			'original' => $this->resolveConfiguredPath((string) $params->get('original_directory', 'audioarchive/originals')),
			'preview' => $this->resolveConfiguredPath((string) $params->get('preview_directory', 'audioarchive/previews')),
			'waveform' => $this->resolveConfiguredPath((string) $params->get('waveform_directory', 'audioarchive/waveforms')),
		];
		$entries = [];

		if ($this->tableExists($database, 'audioarchive_files'))
		{
			$query = $database->getQuery(true)
				->select([
					$database->quoteName('file_role'),
					$database->quoteName('storage_key'),
				])
				->from($database->quoteName('#__audioarchive_files'))
				->where($database->quoteName('storage_key') . ' <> ' . $database->quote(''));

			foreach ($database->setQuery($query)->loadAssocList() as $row)
			{
				$role = (string) $row['file_role'];

				if (!isset($roots[$role]) || $roots[$role] === null)
				{
					continue;
				}

				$entries[] = [
					'root' => $roots[$role],
					'key' => (string) $row['storage_key'],
				];
			}
		}

		if ($this->tableExists($database, 'audioarchive_waveforms') && $roots['waveform'] !== null)
		{
			$query = $database->getQuery(true)
				->select($database->quoteName('storage_key'))
				->from($database->quoteName('#__audioarchive_waveforms'))
				->where($database->quoteName('storage_key') . ' <> ' . $database->quote(''));

			foreach ($database->setQuery($query)->loadColumn() as $storageKey)
			{
				$entries[] = [
					'root' => $roots['waveform'],
					'key' => (string) $storageKey,
				];
			}
		}

		foreach ($roots as $root)
		{
			if ($root !== null)
			{
				$entries[] = [
					'root' => $root,
					'key' => '',
				];
			}
		}

		return $entries;
	}

	/**
	 * @brief Remove Audio Archive rows from Joomla integration tables.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function removeJoomlaIntegrationData(DatabaseInterface $database): void
	{
		$query = $database->getQuery(true)
			->select($database->quoteName('type_id'))
			->from($database->quoteName('#__content_types'))
			->where($database->quoteName('type_alias') . ' = ' . $database->quote(self::CONTENT_TYPE_ALIAS));
		$typeId = (int) $database->setQuery($query)->loadResult();

		$query = $database->getQuery(true)
			->delete($database->quoteName('#__contentitem_tag_map'))
			->where($database->quoteName('type_alias') . ' = ' . $database->quote(self::CONTENT_TYPE_ALIAS));
		$database->setQuery($query)->execute();

		$query = $database->getQuery(true)
			->delete($database->quoteName('#__ucm_content'))
			->where($database->quoteName('core_type_alias') . ' = ' . $database->quote(self::CONTENT_TYPE_ALIAS));
		$database->setQuery($query)->execute();

		if ($typeId > 0)
		{
			$query = $database->getQuery(true)
				->delete($database->quoteName('#__ucm_base'))
				->where($database->quoteName('ucm_type_id') . ' = ' . $typeId);
			$database->setQuery($query)->execute();
		}

		$query = $database->getQuery(true)
			->delete($database->quoteName('#__history'))
			->where($database->quoteName('item_id') . ' LIKE ' . $database->quote(self::CONTENT_TYPE_ALIAS . '.%'));
		$database->setQuery($query)->execute();

		$query = $database->getQuery(true)
			->delete($database->quoteName('#__content_types'))
			->where($database->quoteName('type_alias') . ' = ' . $database->quote(self::CONTENT_TYPE_ALIAS));
		$database->setQuery($query)->execute();
	}

	/**
	 * @brief Drop all component-owned database tables.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function dropComponentTables(DatabaseInterface $database): void
	{
		foreach (['audioarchive_jobs', 'audioarchive_waveforms', 'audioarchive_files', 'audioarchive_clips'] as $tableName)
		{
			$database->setQuery(
				'DROP TABLE IF EXISTS ' . $database->quoteName('#__' . $tableName)
			)->execute();
		}
	}

	/**
	 * @brief Remove known managed media files and empty storage directories.
	 *
	 * Only database-recorded files beneath their configured storage roots are
	 * removed. Symlinks, absolute storage keys and paths escaping their root are
	 * rejected. Untracked files are never deleted.
	 *
	 * @param array<int, array{root:string, key:string}> $entries Managed media entries.
	 * @return array{removed:int, failed:int} Removal counts.
	 */
	private function removeManagedMediaFiles(array $entries): array
	{
		$removed = 0;
		$failed = 0;
		$roots = [];
		$targets = [];

		foreach ($entries as $entry)
		{
			$root = $entry['root'];
			$roots[$root] = true;

			if ($entry['key'] === '')
			{
				continue;
			}

			$target = $this->resolveManagedTarget($root, $entry['key']);

			if ($target === null)
			{
				$failed++;
				continue;
			}

			$targets[$target] = true;
		}

		foreach (array_keys($targets) as $target)
		{
			if (!file_exists($target) && !is_link($target))
			{
				continue;
			}

			if (is_link($target) || !is_file($target) || !@unlink($target))
			{
				$failed++;
				continue;
			}

			$removed++;
		}

		foreach (array_keys($roots) as $root)
		{
			$this->removeEmptyDirectories($root);
		}

		return [
			'removed' => $removed,
			'failed' => $failed,
		];
	}

	/**
	 * @brief Resolve a configured storage path.
	 *
	 * Relative paths are resolved beneath the Joomla root. Absolute paths are
	 * accepted to support storage outside the public web root.
	 *
	 * @param string $configuredPath Configured path.
	 * @return string|null Safe absolute path, or null when unsafe.
	 */
	private function resolveConfiguredPath(string $configuredPath): ?string
	{
		$configuredPath = trim($configuredPath);

		if ($configuredPath === '' || str_contains($configuredPath, "\0"))
		{
			return null;
		}

		$isAbsolute = preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $configuredPath) === 1;
		$path = Path::clean($isAbsolute ? $configuredPath : JPATH_ROOT . '/' . $configuredPath);
		$path = rtrim($path, '/\\');

		if ($path === '' || !$this->isSafeStorageRoot($path))
		{
			return null;
		}

		return $path;
	}

	/**
	 * @brief Reject filesystem roots and Joomla application roots as storage roots.
	 *
	 * @param string $path Absolute storage root.
	 * @return bool True when the root is safe for constrained cleanup.
	 */
	private function isSafeStorageRoot(string $path): bool
	{
		$normalised = $this->normalisePathForComparison($path);
		$joomlaRoot = $this->normalisePathForComparison(JPATH_ROOT);

		if (
			$normalised === ''
			|| preg_match('~^(?:[a-z]:)?/$~', $normalised) === 1
			|| preg_match('~^[a-z]:$~', $normalised) === 1
			|| is_link($path)
		)
		{
			return false;
		}

		if ($normalised === $joomlaRoot || str_starts_with($joomlaRoot . '/', $normalised . '/'))
		{
			return false;
		}

		$protectedPaths = [
			JPATH_ADMINISTRATOR,
			JPATH_LIBRARIES,
			JPATH_PLUGINS,
			JPATH_ROOT . '/components',
			JPATH_ROOT . '/modules',
			JPATH_ROOT . '/templates',
			JPATH_ADMINISTRATOR . '/components',
			JPATH_ADMINISTRATOR . '/modules',
			JPATH_ADMINISTRATOR . '/templates',
		];

		foreach ($protectedPaths as $protectedPath)
		{
			$protected = $this->normalisePathForComparison($protectedPath);

			if ($normalised === $protected || str_starts_with($normalised, $protected . '/'))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @brief Resolve a database storage key beneath its configured root.
	 *
	 * @param string $root Configured storage root.
	 * @param string $storageKey Relative storage key.
	 * @return string|null Absolute target path, or null when invalid.
	 */
	private function resolveManagedTarget(string $root, string $storageKey): ?string
	{
		$storageKey = trim($storageKey);

		if (
			$storageKey === ''
			|| str_contains($storageKey, "\0")
			|| preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $storageKey) === 1
		)
		{
			return null;
		}

		$target = Path::clean($root . '/' . $storageKey);
		$rootComparison = $this->normalisePathForComparison($root);
		$targetComparison = $this->normalisePathForComparison($target);

		if (!str_starts_with($targetComparison, $rootComparison . '/'))
		{
			return null;
		}

		if (file_exists($target) || is_link($target))
		{
			$realRoot = realpath($root);
			$realTarget = realpath($target);

			if ($realRoot === false || $realTarget === false)
			{
				return null;
			}

			$realRootComparison = $this->normalisePathForComparison($realRoot);
			$realTargetComparison = $this->normalisePathForComparison($realTarget);

			if (!str_starts_with($realTargetComparison, $realRootComparison . '/'))
			{
				return null;
			}
		}

		return $target;
	}

	/**
	 * @brief Remove empty directories beneath a storage root.
	 *
	 * Non-empty directories, symlinks and directories outside the configured root
	 * are left untouched.
	 *
	 * @param string $root Storage root.
	 * @return void
	 */
	private function removeEmptyDirectories(string $root): void
	{
		if (!is_dir($root) || is_link($root) || !$this->isSafeStorageRoot($root))
		{
			return;
		}

		try
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($iterator as $item)
			{
				if ($item->isDir() && !$item->isLink())
				{
					@rmdir($item->getPathname());
				}
			}

			@rmdir($root);
		}
		catch (UnexpectedValueException)
		{
			return;
		}
	}

	/**
	 * @brief Normalise a path for containment comparisons.
	 *
	 * @param string $path Filesystem path.
	 * @return string Normalised path using forward slashes.
	 */
	private function normalisePathForComparison(string $path): string
	{
		$normalised = str_replace('\\', '/', Path::clean($path));
		$normalised = rtrim($normalised, '/');

		if (DIRECTORY_SEPARATOR === '\\')
		{
			$normalised = strtolower($normalised);
		}

		return $normalised;
	}

	/**
	 * @brief Record the baseline schema version for future Joomla updates.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @return void
	 */
	private function recordSchemaVersion(DatabaseInterface $database): void
	{
		$query = $database->getQuery(true)
			->select($database->quoteName('extension_id'))
			->from($database->quoteName('#__extensions'))
			->where($database->quoteName('type') . ' = ' . $database->quote('component'))
			->where($database->quoteName('element') . ' = ' . $database->quote('com_audioarchive'));

		$extensionId = (int) $database->setQuery($query)->loadResult();

		if ($extensionId <= 0)
		{
			return;
		}

		$query = $database->getQuery(true)
			->select($database->quoteName('extension_id'))
			->from($database->quoteName('#__schemas'))
			->where($database->quoteName('extension_id') . ' = ' . $extensionId);

		$existingId = (int) $database->setQuery($query)->loadResult();

		if ($existingId > 0)
		{
			$query = $database->getQuery(true)
				->update($database->quoteName('#__schemas'))
				->set($database->quoteName('version_id') . ' = ' . $database->quote(self::SCHEMA_VERSION))
				->where($database->quoteName('extension_id') . ' = ' . $extensionId);
		}
		else
		{
			$query = $database->getQuery(true)
				->insert($database->quoteName('#__schemas'))
				->columns([
					$database->quoteName('extension_id'),
					$database->quoteName('version_id'),
				])
				->values($extensionId . ', ' . $database->quote(self::SCHEMA_VERSION));
		}

		$database->setQuery($query)->execute();
	}
};
