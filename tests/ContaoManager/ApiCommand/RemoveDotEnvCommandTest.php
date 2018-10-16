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
use Contao\ManagerBundle\ContaoManager\ApiCommand\RemoveDotEnvCommand;
use Contao\TestCase\ContaoTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class RemoveDotEnvCommandTest extends ContaoTestCase
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $tempdir;

    /**
     * @var string
     */
    private $tempfile;

    /**
     * @var RemoveDotEnvCommand
     */
    private $command;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->tempdir = $this->getTempDir();
        $this->tempfile = $this->tempdir.'/.env';

        $application = $this->createMock(Application::class);
        $application
            ->method('getProjectDir')
            ->willReturn($this->tempdir)
        ;

        $this->command = new RemoveDotEnvCommand($application);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->filesystem->remove($this->tempdir);
    }

    public function testHasCorrectNameAndArguments(): void
    {
        $this->assertSame('dot-env:remove', $this->command->getName());
        $this->assertTrue($this->command->getDefinition()->hasArgument('key'));
        $this->assertTrue($this->command->getDefinition()->getArgument('key')->isRequired());
    }

    public function testRemovesKeyFromDotEnv(): void
    {
        $this->filesystem->dumpFile($this->tempfile, "BAR='FOO'\nFOO='BAR'\n");

        $tester = new CommandTester($this->command);
        $tester->execute(['key' => 'FOO']);

        $this->assertSame('', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($this->tempfile);
        $this->assertSame("BAR='FOO'\n", file_get_contents($this->tempfile));
    }

    public function testRemovesDotEnvIfLastKeyIsRemoved(): void
    {
        $this->filesystem->dumpFile($this->tempfile, "FOO='BAR'\n");

        $tester = new CommandTester($this->command);
        $tester->execute(['key' => 'FOO']);

        $this->assertSame('', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileNotExists($this->tempfile);
    }

    public function testIgnoresIfDotEnvDoesNotExist(): void
    {
        $this->assertFileNotExists($this->tempfile);

        $tester = new CommandTester($this->command);
        $tester->execute(['key' => 'FOO']);

        $this->assertSame('', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
    }
}
