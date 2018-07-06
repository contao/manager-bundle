<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\ContaoManager;

use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;

final class ExtensionPlugin implements ExtensionPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container)
    {
        switch ($extensionName) {
            case 'doctrine':
                return $this->fixDoctrineConfig($extensionConfigs, $container);

            default:
                return $extensionConfigs;
        }
    }

    /**
     * Adds the database server version to the Doctrine DBAL configuration.
     *
     * @param array            $extensionConfigs
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function fixDoctrineConfig(array $extensionConfigs, ContainerBuilder $container)
    {
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

        // If there are no DB credentials yet (install tool), we have to set
        // the server version to prevent a DBAL exception (see #1422)
        try {
            $connection = DriverManager::getConnection($params);
            $connection->connect();
            $connection->close();
        } catch (DriverException $e) {
            $extensionConfigs[] = [
                'dbal' => [
                    'connections' => [
                        'default' => [
                            'server_version' => '5.5',
                        ],
                    ],
                ],
            ];
        }

        return $extensionConfigs;
    }
}
