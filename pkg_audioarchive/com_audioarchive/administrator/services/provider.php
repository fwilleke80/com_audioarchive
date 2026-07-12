<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_audioarchive
 *
 * @copyright   Copyright (C) 2026 Frank Willeke.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Willeke\Component\Audioarchive\Administrator\Extension\AudioarchiveComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface
{
    /**
     * @brief Register the component services.
     *
     * @param Container $container Joomla dependency-injection container.
     *
     * @return void
     */
    public function register(Container $container)
    {
        $container->registerServiceProvider(new CategoryFactory('\\Willeke\\Component\\Audioarchive'));
        $container->registerServiceProvider(new MVCFactory('\\Willeke\\Component\\Audioarchive'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Willeke\\Component\\Audioarchive'));
        $container->registerServiceProvider(new RouterFactory('\\Willeke\\Component\\Audioarchive'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface
            {
                $component = new AudioarchiveComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
