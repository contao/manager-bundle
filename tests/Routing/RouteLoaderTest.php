<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\HttpKernel;

use Contao\ManagerBundle\Routing\RouteLoader;
use Contao\ManagerPlugin\PluginLoader;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the RouteLoader class.
 *
 * @author Yanick Witschi <https://github.com/toflar>
 */
class RouteLoaderTest extends TestCase
{
    /**
     * Tests the loadFromPlugins() method.
     */
    public function testLoadFromPlugins()
    {
        $loaderResolver = $this->createMock(LoaderResolverInterface::class);
        $loader = $this->createMock(LoaderInterface::class);

        $loader
            ->expects($this->exactly(2))
            ->method('getResolver')
            ->willReturn($loaderResolver)
        ;

        $plugin1 = $this->mockRoutePlugin('foo', '/foo/path');
        $plugin2 = $this->mockRoutePlugin('foo2', '/foo2/path2');

        $pluginLoader = $this->createMock(PluginLoader::class);

        $pluginLoader
            ->expects($this->once())
            ->method('getInstancesOf')
            ->with(PluginLoader::ROUTING_PLUGINS, true)
            ->willReturn([$plugin1, $plugin2])
        ;

        $routeLoader = new RouteLoader(
            $loader,
            $pluginLoader,
            $this->createMock(KernelInterface::class)
        );

        $collection = $routeLoader->loadFromPlugins();

        $this->assertCount(2, $collection);
        $this->assertNotNull($collection->get('foo'));
        $this->assertNotNull($collection->get('foo2'));
        $this->assertInstanceOf(Route::class, $collection->get('foo'));
        $this->assertInstanceOf(Route::class, $collection->get('foo2'));
    }

    /**
     * Tests that the catch all route is last.
     */
    public function testCatchAllIsLast()
    {
        $loaderResolver = $this->createMock(LoaderResolverInterface::class);
        $loader = $this->createMock(LoaderInterface::class);

        $loader
            ->expects($this->exactly(4))
            ->method('getResolver')
            ->willReturn($loaderResolver)
        ;

        $plugin1 = $this->mockRoutePlugin('foo', '/foo/path');
        $plugin2 = $this->mockRoutePlugin('contao_catch_all', '/foo2/path2');
        $plugin3 = $this->mockRoutePlugin('foo3', '/foo3/path3');
        $plugin4 = $this->mockRoutePlugin('foo4', '/foo4/path4');

        $pluginLoader = $this->createMock(PluginLoader::class);

        $pluginLoader
            ->expects($this->once())
            ->method('getInstancesOf')
            ->with(PluginLoader::ROUTING_PLUGINS, true)
            ->willReturn([$plugin1, $plugin2, $plugin3, $plugin4])
        ;

        $routeLoader = new RouteLoader(
            $loader,
            $pluginLoader,
            $this->createMock(KernelInterface::class)
        );

        $routes = $routeLoader->loadFromPlugins()->all();

        $this->assertCount(4, $routes);
        $this->assertArrayHasKey('contao_catch_all', $routes);
        $this->assertSame(3, array_search('contao_catch_all', array_keys($routes), true));
    }

    /**
     * Mocks a route plugin.
     *
     * @param string $routeName
     * @param string $routePath
     *
     * @return RoutingPluginInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function mockRoutePlugin($routeName, $routePath)
    {
        $collection = new RouteCollection();
        $collection->add($routeName, new Route($routePath));

        $plugin = $this->createMock(RoutingPluginInterface::class);

        $plugin
            ->expects($this->atLeastOnce())
            ->method('getRouteCollection')
            ->willReturn($collection)
        ;

        return $plugin;
    }
}
