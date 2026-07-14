<?php

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Willeke\Plugin\Quickicon\Audioarchive\Extension\Audioarchive;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface
{
	/**
	 * @brief Register the Audio Archive Quick Icon plugin service.
	 *
	 * @param Container $container Joomla dependency-injection container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(Audioarchive::class, function (): Audioarchive
			{
				$plugin = new Audioarchive(
					(array) PluginHelper::getPlugin('quickicon', 'audioarchive')
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};
