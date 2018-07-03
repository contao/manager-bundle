<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\Api;

use Contao\ManagerBundle\Api\Application;
use Contao\ManagerBundle\Api\ManagerConfig;
use Contao\ManagerBundle\ContaoManager\ApiCommand\GetConfigCommand;
use Contao\ManagerBundle\ContaoManagerBundle;
use Contao\ManagerPlugin\Api\ApiPluginInterface;
use Contao\ManagerPlugin\PluginLoader;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testInstantiation(): void
    {
        $this->assertInstanceOf('Contao\ManagerBundle\Api\Application', new Application(sys_get_temp_dir()));
    }

    public function testReturnsCorrectApplicationNameAndVersion()
    {
        $application = new Application(sys_get_temp_dir());

        $this->assertSame('contao-api', $application->getName());
        $this->assertSame(Application::VERSION, $application->getVersion());
    }

    public function testReturnsProjectDir()
    {
        $application = new Application('/foo/bar');

        $this->assertSame('/foo/bar', $application->getProjectDir());
    }

    public function testReturnsNewInstanceOfPluginLoader()
    {
        $application = new Application(sys_get_temp_dir());

        $this->assertInstanceOf(PluginLoader::class, $application->getPluginLoader());
    }

    public function testReturnsConfiguredPluginLoader()
    {
        $application = new Application(sys_get_temp_dir());
        $pluginLoader = $this->createMock(PluginLoader::class);

        $application->setPluginLoader($pluginLoader);

        $this->assertSame($pluginLoader, $application->getPluginLoader());
    }

    public function testSetsDisabledPackagesInPluginLoader(): void
    {
        $config = $this->createMock(ManagerConfig::class);

        $config
            ->expects($this->once())
            ->method('all')
            ->willReturn([
                'contao_manager' => [
                     'disabled_packages' => ['foo/bar'],
                ],
            ])
        ;

        $application = new Application(sys_get_temp_dir());
        $application->setManagerConfig($config);

        $pluginLoader = $application->getPluginLoader();

        $this->assertSame(['foo/bar'], $pluginLoader->getDisabledPackages());
    }

    public function testReturnsNewInstanceOfManagerConfigWithProjectDir()
    {
        $application = new Application(__DIR__.'/../Fixtures/Api');
        $managerConfig = $application->getManagerConfig();

        $this->assertInstanceOf(ManagerConfig::class, $managerConfig);
        $this->assertSame(['foo' => 'bar'], $managerConfig->all());
    }

    public function testReturnsConfiguredManagerConfig()
    {
        $application = new Application(sys_get_temp_dir());
        $managerConfig = $this->createMock(ManagerConfig::class);

        $application->setManagerConfig($managerConfig);

        $this->assertSame($managerConfig, $application->getManagerConfig());
    }

    public function testGetsCommandsFromPlugins()
    {
        $plugin = $this->createMock(ApiPluginInterface::class);

        $plugin
            ->expects($this->once())
            ->method('getApiCommands')
            ->willReturn([GetConfigCommand::class])
        ;

        $pluginLoader = $this->createMock(PluginLoader::class);

        $pluginLoader
            ->expects($this->once())
            ->method('getInstancesOf')
            ->with(ApiPluginInterface::class)
            ->willReturn([$plugin])
        ;

        $application = new Application(sys_get_temp_dir());
        $application->setPluginLoader($pluginLoader);

        $commands = $application->all();

        $this->assertCount(4, $commands);
        $this->assertArrayHasKey('config:get', $commands);
    }

    public function testThrowsExceptionIfPluginReturnsInvalidCommand()
    {
        $plugin = $this->createMock(ApiPluginInterface::class);

        $plugin
            ->expects($this->once())
            ->method('getApiCommands')
            ->willReturn([ContaoManagerBundle::class])
        ;

        $pluginLoader = $this->createMock(PluginLoader::class);

        $pluginLoader
            ->expects($this->once())
            ->method('getInstancesOf')
            ->with(ApiPluginInterface::class)
            ->willReturn([$plugin])
        ;

        $application = new Application(sys_get_temp_dir());
        $application->setPluginLoader($pluginLoader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Contao\ManagerBundle\ContaoManagerBundle" is not a console command.');

        $application->all();
    }
}
