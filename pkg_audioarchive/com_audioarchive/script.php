<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

return new class () implements InstallerScriptInterface
{
    private const SCHEMA_VERSION = '0.1.1';

    private const CATEGORY_MENU_LINK = 'index.php?option=com_categories&view=categories&extension=com_audioarchive';

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
     * @return bool True when the database schema is ready.
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return $this->repairDatabaseSchema() && $this->repairAdministratorMenu();
    }

    /**
     * @brief Complete a component update.
     *
     * @param InstallerAdapter $adapter Joomla installer adapter.
     * @return bool True when the database schema is ready.
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return $this->repairDatabaseSchema() && $this->repairAdministratorMenu();
    }

    /**
     * @brief Leave archived audio files untouched during uninstallation.
     *
     * @param InstallerAdapter $adapter Joomla installer adapter.
     * @return bool Always true.
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
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
     * @return bool Always true after the install or update method succeeded.
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true))
        {
            return true;
        }

        return $this->repairAdministratorMenu();
    }

    /**
     * @brief Repair the administrator Categories menu URL used for active-path matching.
     *
     * Joomla marks the administrator menu tree active by matching the current URL
     * against menu-item URLs. The category list redirect includes the explicit
     * categories view before the extension argument, so our stored menu link must
     * use the same canonical form and argument order.
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
     * Joomla writes SQL NULL to both checkout fields when a table declares
     * support for nullable values. Existing development installations used a
     * non-nullable checked_out column, so every install and update repairs the
     * column definition idempotently.
     *
     * @param DatabaseInterface $database Joomla database connection.
     * @return void
     */
    private function repairCheckoutColumns(DatabaseInterface $database): void
    {
        $query = 'ALTER TABLE ' . $database->quoteName('#__audioarchive_clips')
            . ' MODIFY ' . $database->quoteName('checked_out') . ' int unsigned DEFAULT NULL,'
            . ' MODIFY ' . $database->quoteName('checked_out_time') . ' datetime DEFAULT NULL';

        $database->setQuery($query)->execute();
    }

    /**
     * @brief Register the Audio Archive clip content type with Joomla.
     *
     * @param DatabaseInterface $database Joomla database connection.
     * @return void
     */
    private function ensureContentType(DatabaseInterface $database): void
    {
        $query = $database->getQuery(true)
            ->select($database->quoteName('type_id'))
            ->from($database->quoteName('#__content_types'))
            ->where($database->quoteName('type_alias') . ' = ' . $database->quote('com_audioarchive.clip'));

        $existingId = (int) $database->setQuery($query)->loadResult();

        if ($existingId > 0)
        {
            return;
        }

        $tableDefinition = json_encode(
            [
                'special' => [
                    'dbtable' => '#__audioarchive_clips',
                    'key' => 'id',
                    'type' => 'Clip',
                    'prefix' => 'Willeke\\Component\\Audioarchive\\Administrator\\Table\\',
                ],
            ],
            JSON_THROW_ON_ERROR
        );

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
                $database->quote('com_audioarchive.clip'),
                $database->quote($tableDefinition),
                $database->quote(''),
                $database->quote('{}'),
                $database->quote(''),
                $database->quote('{}'),
            ]));

        $database->setQuery($query)->execute();
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
