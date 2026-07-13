<?php

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Willeke\Plugin\Finder\Audioarchive\Extension\Audioarchive;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface
{
	/**
	 * @brief Register the Smart Search plugin service.
	 *
	 * @param Container $container Joomla dependency-injection container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(Audioarchive::class, function (Container $container): Audioarchive
			{
				$plugin = new Audioarchive(
					(array) PluginHelper::getPlugin('finder', 'audioarchive')
				);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			})
		);
	}
};
