<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

return new class () implements InstallerScriptInterface
{
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
	 * @brief Prepare the package operation.
	 *
	 * @param string $type Installer operation.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Enable the Finder plugin after a fresh package installation.
	 *
	 * Updating an existing package must not alter the administrator's plugin
	 * state and must not perform package-level database work.
	 *
	 * @param string $type Installer operation.
	 * @param InstallerAdapter $adapter Joomla installer adapter.
	 *
	 * @return bool
	 */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($type !== 'install')
		{
			return true;
		}

		try
		{
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
		}
		catch (\Throwable)
		{
			// Plugin activation is convenient but must never make installation fail.
		}

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
