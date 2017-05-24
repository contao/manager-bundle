<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\ManagerBundle\Tests\Command;

use Contao\ManagerBundle\Command\InstallWebDirCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Tests the InstallWebDirCommand class.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 * @author Yanick Witschi <https://github.com/toflar>
 */
class InstallWebDirCommandTest extends TestCase
{
    /**
     * @var InstallWebDirCommand
     */
    private $command;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $tmpdir;

    /**
     * @var Finder
     */
    private $webFiles;

    /**
     * @var array
     */
    private $optionalFiles;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->command = new InstallWebDirCommand();
        $this->command->setApplication($this->getApplication());

        $this->filesystem = new Filesystem();
        $this->tmpdir = sys_get_temp_dir().'/'.uniqid('InstallWebDirCommand_', false);
        $this->webFiles = Finder::create()->files()->ignoreDotFiles(false)->in(__DIR__.'/../../src/Resources/web');

        $ref = new \ReflectionClass(InstallWebDirCommand::class);
        $prop = $ref->getProperty('optionalFiles');
        $prop->setAccessible(true);
        $this->optionalFiles = $prop->getValue($this->command);
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        (new Filesystem())->remove($this->tmpdir);
    }

    /**
     * Tests the object instantiation.
     */
    public function testInstantiation()
    {
        $this->assertInstanceOf('Contao\ManagerBundle\Command\InstallWebDirCommand', $this->command);
    }

    /**
     * Tests the command name.
     */
    public function testNameAndArguments()
    {
        $this->assertSame('contao:install-web-dir', $this->command->getName());
        $this->assertTrue($this->command->getDefinition()->hasArgument('path'));
    }

    /**
     * Tests the command.
     */
    public function testCommandRegular()
    {
        foreach ($this->webFiles as $file) {
            $this->assertFileNotExists($this->tmpdir.'/web/'.$file->getFilename());
        }

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir]);

        foreach ($this->webFiles as $file) {
            $this->assertFileExists($this->tmpdir.'/web/'.$file->getRelativePathname());

            $expectedString = file_get_contents($file->getPathname());
            $expectedString = str_replace(['{root-dir}', '{vendor-dir}'], ['../app', '../vendor'], $expectedString);

            $this->assertStringEqualsFile($this->tmpdir.'/web/'.$file->getRelativePathname(), $expectedString);
        }
    }

    /**
     * Tests that the command does not override optional optional files.
     */
    public function testCommandDoesNotOverrideOptionals()
    {
        foreach ($this->webFiles as $file) {
            $this->filesystem->dumpFile($this->tmpdir.'/web/'.$file->getRelativePathname(), 'foobar-content');
        }

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir]);

        foreach ($this->webFiles as $file) {
            if (in_array($file->getRelativePathname(), $this->optionalFiles, true)) {
                $this->assertStringEqualsFile($this->tmpdir.'/web/'.$file->getFilename(), 'foobar-content');
            } else {
                $this->assertStringNotEqualsFile($this->tmpdir.'/web/'.$file->getFilename(), 'foobar-content');
            }
        }
    }

    /**
     * Tests that the install.php is removed from web directory.
     */
    public function testCommandRemovesInstallPhp()
    {
        $this->filesystem->dumpFile($this->tmpdir.'/web/install.php', 'foobar-content');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir]);

        $this->assertFileNotExists($this->tmpdir.'/web/install.php');
    }

    /**
     * Tests that the install.php is removed from web directory.
     */
    public function testInstallsAppDevByDefault()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir]);

        $this->assertFileExists($this->tmpdir.'/web/app_dev.php');
    }

    /**
     * Tests that the install.php is removed from web directory.
     */
    public function testNotInstallsAppDevOnProd()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir, '--no-dev' => true]);

        $this->assertFileNotExists($this->tmpdir.'/web/app_dev.php');
    }

    public function testAccesskeyFromArgument()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir, '--user' => 'foo', '--password' => 'bar']);

        $this->assertFileExists($this->tmpdir.'/.env');
        $this->assertContains(hash('sha512', 'foo:bar'), file_get_contents($this->tmpdir.'/.env'));
    }

    public function testAccesskeyFromInput()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['foo', 'bar']);
        $commandTester->execute(['path' => $this->tmpdir, '--password' => true]);

        $this->assertContains('Please enter a username:', $commandTester->getDisplay());
        $this->assertContains('Please enter a password:', $commandTester->getDisplay());

        $this->assertFileExists($this->tmpdir.'/.env');
        $this->assertContains(hash('sha512', 'foo:bar'), file_get_contents($this->tmpdir.'/.env'));
    }

    public function testAccesskeyWithUserFromInput()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['bar']);
        $commandTester->execute(['path' => $this->tmpdir, '--user' => 'foo', '--password' => true]);

        $this->assertNotContains('Please enter a username:', $commandTester->getDisplay());
        $this->assertContains('Please enter a password:', $commandTester->getDisplay());

        $this->assertFileExists($this->tmpdir.'/.env');
        $this->assertContains(hash('sha512', 'foo:bar'), file_get_contents($this->tmpdir.'/.env'));
    }

    public function testAccesskeyWithoutUserFromInput()
    {
        QuestionHelper::disableStty();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have username and password');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['foo']);
        $commandTester->execute(['path' => $this->tmpdir, '--password' => 'bar']);
    }

    public function testAccesskeyAppendToDotEnv()
    {
        $this->filesystem->dumpFile($this->tmpdir.'/.env', 'FOO=bar');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['path' => $this->tmpdir, '--user' => 'foo', '--password' => 'bar']);

        $content = "FOO=bar\nAPP_DEV_ACCESSKEY=".hash('sha512', 'foo:bar')."\n";

        $this->assertFileExists($this->tmpdir.'/.env');
        $this->assertStringEqualsFile($this->tmpdir.'/.env', $content);
    }

    /**
     * Returns the application object.
     *
     * @return Application
     */
    private function getApplication()
    {
        $application = new Application();
        $application->setCatchExceptions(true);

        return $application;
    }
}
