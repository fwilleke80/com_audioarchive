<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

return new class () implements InstallerScriptInterface
{
	/** @var bool Whether the Finder plugin existed before this package operation. */
	private bool $finderPluginExisted = false;

	/**
	 * @brief Complete package installation.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function install(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Complete package update.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function update(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Complete package removal.
	 *
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function uninstall(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Remember whether the Smart Search plugin was already installed.
	 *
	 * @param string $type Installer operation.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		if (!in_array($type, ['install', 'update', 'discover_install'], true))
		{
			return true;
		}

		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$this->finderPluginExisted = $this->findFinderPluginId($database) > 0;

		return true;
	}

	/**
	 * @brief Enable the new Finder plugin on its first installation.
	 *
	 * Existing installations retain the administrator's enabled/disabled choice
	 * during later package updates.
	 *
	 * @param string $type Installer operation.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if (
			!in_array($type, ['install', 'update', 'discover_install'], true)
			|| $this->finderPluginExisted
		)
		{
			return true;
		}

		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$pluginId = $this->findFinderPluginId($database);

		if ($pluginId <= 0)
		{
			return true;
		}

		$query = $database->getQuery(true)
			->update($database->quoteName('#__extensions'))
			->set($database->quoteName('enabled') . ' = 1')
			->where($database->quoteName('extension_id') . ' = :pluginId')
			->bind(':pluginId', $pluginId, ParameterType::INTEGER);
		$database->setQuery($query)->execute();

		return true;
	}

	/**
	 * @brief Find the installed Audio Archive Finder plugin.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 *
	 * @return int Extension identifier or zero.
	 */
	private function findFinderPluginId(DatabaseInterface $database): int
	{
		$type = 'plugin';
		$folder = 'finder';
		$element = 'audioarchive';
		$query = $database->getQuery(true)
			->select($database->quoteName('extension_id'))
			->from($database->quoteName('#__extensions'))
			->where($database->quoteName('type') . ' = :type')
			->where($database->quoteName('folder') . ' = :folder')
			->where($database->quoteName('element') . ' = :element')
			->bind(':type', $type, ParameterType::STRING)
			->bind(':folder', $folder, ParameterType::STRING)
			->bind(':element', $element, ParameterType::STRING);

		return (int) $database->setQuery($query, 0, 1)->loadResult();
	}
};
