<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\ContaoManager\ApiCommand;

use Contao\ManagerBundle\Api\Application;
use Contao\ManagerBundle\Api\ManagerConfig;
use Contao\ManagerBundle\ContaoManager\ApiCommand\GetConfigCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GetConfigCommandTest extends TestCase
{
    /**
     * @var ManagerConfig|MockObject
     */
    private $config;

    /**
     * @var GetConfigCommand
     */
    private $command;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ManagerConfig::class);

        $application = $this->createMock(Application::class);

        $application
            ->method('getManagerConfig')
            ->willReturn($this->config)
        ;

        $this->command = new GetConfigCommand($application);
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf('Contao\ManagerBundle\ContaoManager\ApiCommand\GetConfigCommand', $this->command);
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('config:get', $this->command->getName());
    }

    public function testDumpsManagerConfigAsJson(): void
    {
        $this->config
            ->expects($this->once())
            ->method('all')
            ->willReturn(['foo' => 'bar'])
        ;

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertSame('{"foo":"bar"}', $commandTester->getDisplay());
        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
