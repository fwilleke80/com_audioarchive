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
	 * @brief Complete plugin installation.
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
	 * @brief Complete plugin update.
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
	 * @brief Complete plugin removal.
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
	 * @brief Prepare the plugin operation.
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
	 * @brief Enable the plugin after its first installation.
	 *
	 * Updates preserve the administrator's chosen plugin state.
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
			$type = 'plugin';
			$folder = 'quickicon';
			$element = 'audioarchive';
			$query = $database->getQuery(true)
				->update($database->quoteName('#__extensions'))
				->set($database->quoteName('enabled') . ' = 1')
				->where($database->quoteName('type') . ' = :type')
				->where($database->quoteName('folder') . ' = :folder')
				->where($database->quoteName('element') . ' = :element')
				->bind(':type', $type, ParameterType::STRING)
				->bind(':folder', $folder, ParameterType::STRING)
				->bind(':element', $element, ParameterType::STRING);
			$database->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// A convenience activation must never make installation fail.
		}

		return true;
	}
};
