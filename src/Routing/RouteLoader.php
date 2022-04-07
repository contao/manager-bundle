<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Routing;

use Contao\ManagerPlugin\PluginLoader;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader implements RouteLoaderInterface
{
    /**
     * @internal Do not inherit from this class; decorate the "contao_manager.routing.route_loader" service instead
     */
    public function __construct(
        private LoaderInterface $loader,
        private PluginLoader $pluginLoader,
        private KernelInterface $kernel,
        private string $projectDir
    ) {
    }

    /**
     * Returns a route collection build from all plugin routes.
     */
    public function loadFromPlugins(): RouteCollection
    {
        $collection = array_reduce(
            $this->pluginLoader->getInstancesOf(PluginLoader::ROUTING_PLUGINS, true),
            function (RouteCollection $collection, RoutingPluginInterface $plugin): RouteCollection {
                $routes = $plugin->getRouteCollection($this->loader->getResolver(), $this->kernel);

                if ($routes instanceof RouteCollection) {
                    $collection->addCollection($routes);
                }

                return $collection;
            },
            new RouteCollection()
        );

        // Load the routing.yml file if it exists
        if ($configFile = $this->getConfigFile()) {
            $routes = $this->loader->getResolver()->resolve($configFile)->load($configFile);

            if ($routes instanceof RouteCollection) {
                $collection->addCollection($routes);
            }
        }

        // Make sure the Contao frontend routes are always loaded last
        foreach (['contao_frontend', 'contao_index', 'contao_root', 'contao_catch_all'] as $name) {
            if ($route = $collection->get($name)) {
                $collection->add($name, $route);
            }
        }

        return $collection;
    }

    private function getConfigFile(): ?string
    {
        foreach (['routes.yaml', 'routes.yml'] as $file) {
            $path = Path::join($this->projectDir, 'config', $file);

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
