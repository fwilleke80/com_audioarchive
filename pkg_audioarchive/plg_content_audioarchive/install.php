<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

return new class () implements InstallerScriptInterface
{
	/** @brief Complete plugin installation. */
	public function install(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/** @brief Complete plugin update. */
	public function update(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/** @brief Complete plugin removal. */
	public function uninstall(InstallerAdapter $adapter): bool
	{
		return true;
	}

	/** @brief Prepare the plugin operation. */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		return true;
	}

	/**
	 * @brief Enable the plugin when it is installed for the first time.
	 *
	 * Package updates preserve the administrator's state after the plugin has
	 * already existed.
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
			$extensionType = 'plugin';
			$folder = 'content';
			$element = 'audioarchive';
			$query = $database->getQuery(true)
				->update($database->quoteName('#__extensions'))
				->set($database->quoteName('enabled') . ' = 1')
				->where($database->quoteName('type') . ' = :extensionType')
				->where($database->quoteName('folder') . ' = :folder')
				->where($database->quoteName('element') . ' = :element')
				->bind(':extensionType', $extensionType, ParameterType::STRING)
				->bind(':folder', $folder, ParameterType::STRING)
				->bind(':element', $element, ParameterType::STRING);
			$database->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// Convenience activation must never make extension installation fail.
		}

		return true;
	}
};
