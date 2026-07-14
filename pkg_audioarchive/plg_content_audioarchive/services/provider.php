<?php

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Willeke\Plugin\Content\Audioarchive\Extension\Audioarchive;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface
{
	/**
	 * @brief Register the Audio Archive content plugin service.
	 *
	 * @param Container $container Joomla dependency-injection container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container): Audioarchive
			{
				$plugin = new Audioarchive(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('content', 'audioarchive')
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
