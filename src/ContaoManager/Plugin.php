<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\ManagerBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder as PluginContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Dependency\DependentPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, ConfigPluginInterface, RoutingPluginInterface, ExtensionPluginInterface, DependentPluginInterface
{
    /**
     * @var string|null
     */
    private static $autoloadModules = null;

    /**
     * {@inheritdoc}
     */
    public function getPackageDependencies()
    {
        return ['contao/core-bundle'];
    }

    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        $configs = $parser->parse(__DIR__.'/../Resources/contao-manager/bundles.json');

        if (null !== static::$autoloadModules && file_exists(static::$autoloadModules)) {
            /** @var Finder $modules */
            $modules = (new Finder())
                ->directories()
                ->depth(0)
                ->in(static::$autoloadModules)
            ;

            foreach ($modules as $module) {
                if (file_exists($module->getPathname().'/.skip')) {
                    continue;
                }

                $configs = array_merge($configs, $parser->parse($module->getFilename(), 'ini'));
            }
        }

        return $configs;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig): void
    {
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/framework.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/security.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/contao.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/twig.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/doctrine.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/swiftmailer.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/monolog.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/lexik_maintenance.yml');
        $loader->load('@ContaoManagerBundle/Resources/contao-manager/nelmio_security.yml');

        $loader->load(
            function (ContainerBuilder $container) use ($loader): void {
                if ('dev' === $container->getParameter('kernel.environment')) {
                    $loader->load('@ContaoManagerBundle/Resources/contao-manager/web_profiler.yml');
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        if ('dev' !== $kernel->getEnvironment()) {
            return null;
        }

        $collections = [];

        $files = [
            '_wdt' => '@WebProfilerBundle/Resources/config/routing/wdt.xml',
            '_profiler' => '@WebProfilerBundle/Resources/config/routing/profiler.xml',
        ];

        foreach ($files as $prefix => $file) {
            /** @var RouteCollection $collection */
            $collection = $resolver->resolve($file)->load($file);
            $collection->addPrefix($prefix);

            $collections[] = $collection;
        }

        $collection = array_reduce(
            $collections,
            function (RouteCollection $carry, RouteCollection $item): RouteCollection {
                $carry->addCollection($item);

                return $carry;
            },
            new RouteCollection()
        );

        // Redirect the deprecated install.php file
        $collection->add('contao_install_redirect', new Route('/install.php', [
            '_scope' => 'backend',
            '_controller' => 'FrameworkBundle:Redirect:redirect',
            'route' => 'contao_install',
            'permanent' => true,
        ]));

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensionConfig($extensionName, array $extensionConfigs, PluginContainerBuilder $container): array
    {
        if ('doctrine' !== $extensionName) {
            return $extensionConfigs;
        }

        $params = [];

        foreach ($extensionConfigs as $extensionConfig) {
            if (isset($extensionConfig['dbal']['connections']['default'])) {
                $params = array_merge($params, $extensionConfig['dbal']['connections']['default']);
            }
        }

        $parameterBag = $container->getParameterBag();

        foreach ($params as $key => $value) {
            $params[$key] = $parameterBag->resolveValue($value);
        }

        try {
            $connection = DriverManager::getConnection($params);
            $connection->connect();
            $connection->close();
        } catch (DriverException $e) {
            $extensionConfigs[] = [
                'dbal' => [
                    'connections' => [
                        'default' => [
                            'server_version' => '5.1',
                        ],
                    ],
                ],
            ];
        }

        return $extensionConfigs;
    }

    /**
     * Sets the path to enable autoloading of legacy Contao modules.
     *
     * @param string $modulePath
     */
    public static function autoloadModules(string $modulePath): void
    {
        static::$autoloadModules = $modulePath;
    }
}
