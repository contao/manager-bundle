<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\Command;

use Contao\ManagerBundle\Command\InstallWebDirCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

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
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->tmpdir = sys_get_temp_dir().'/'.uniqid('InstallWebDirCommand_', true);
        $this->webFiles = Finder::create()->files()->in(__DIR__.'/../../src/Resources/web');

        $this->command = new InstallWebDirCommand($this->tmpdir);
        $this->command->setApplication($this->mockApplication());
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->filesystem->remove($this->tmpdir);
    }

    /**
     * Tests the command name.
     */
    public function testNameAndArguments()
    {
        $this->assertSame('contao:install-web-dir', $this->command->getName());
        $this->assertTrue($this->command->getDefinition()->hasArgument('target'));
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
        $commandTester->execute([]);

        foreach ($this->webFiles as $file) {
            $this->assertFileExists($this->tmpdir.'/web/'.$file->getRelativePathname());

            $expectedString = file_get_contents($file->getPathname());
            $expectedString = str_replace(['{root-dir}', '{vendor-dir}'], ['../app', '../vendor'], $expectedString);

            $this->assertStringEqualsFile($this->tmpdir.'/web/'.$file->getRelativePathname(), $expectedString);
        }
    }

    /**
     * Tests that the .htaccess file is not changed if it includes a rewrite rule.
     */
    public function testHtaccessIsNotChangedIfRewriteRuleExists()
    {
        $existingHtaccess = <<<'EOT'
<IfModule mod_headers.c>
  RewriteRule ^ %{ENV:BASE}/app.php [L]
</IfModule>
EOT;

        $this->filesystem->dumpFile($this->tmpdir.'/web/.htaccess', $existingHtaccess);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertStringEqualsFile($this->tmpdir.'/web/.htaccess', $existingHtaccess);
    }

    /**
     * Tests that the .htaccess file is changed if it does not include a rewrite rule.
     */
    public function testHtaccessIsChangedIfRewriteRuleDoesNotExists()
    {
        $existingHtaccess = <<<'EOT'
# Enable PHP 7.2
AddHandler application/x-httpd-php72 .php
EOT;

        $this->filesystem->dumpFile($this->tmpdir.'/web/.htaccess', $existingHtaccess);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertStringEqualsFile(
            $this->tmpdir.'/web/.htaccess',
            $existingHtaccess."\n\n".file_get_contents(__DIR__.'/../../src/Resources/web/.htaccess')
        );
    }

    /**
     * Tests that the install.php is removed from web directory.
     */
    public function testCommandRemovesInstallPhp()
    {
        $this->filesystem->dumpFile($this->tmpdir.'/web/install.php', 'foobar-content');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertFileNotExists($this->tmpdir.'/web/install.php');
    }

    /**
     * Tests that the app_dev.php is installed by default.
     */
    public function testInstallsAppDevByDefault()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertFileExists($this->tmpdir.'/web/app_dev.php');
    }

    /**
     * Tests that a custom target directory is used.
     */
    public function testUsesACustomTargetDirectory()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['target' => 'public']);

        $this->assertFileExists($this->tmpdir.'/public/app.php');
    }

    /**
     * Tests that the install.php is removed from web directory.
     */
    public function testNotInstallsAppDevOnProd()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--no-dev' => true]);

        $this->assertFileNotExists($this->tmpdir.'/web/app_dev.php');
    }

    /**
     * Tests setting the access key as argument.
     */
    public function testAccesskeyFromArgument()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--user' => 'foo', '--password' => 'bar']);

        $this->assertFileExists($this->tmpdir.'/.env');

        $env = (new Dotenv())->parse(file_get_contents($this->tmpdir.'/.env'), $this->tmpdir.'/.env');

        $this->assertArrayHasKey('APP_DEV_ACCESSKEY', $env);
        $this->assertTrue(password_verify('foo:bar', $env['APP_DEV_ACCESSKEY']));
    }

    /**
     * Tests setting the access key interactively.
     */
    public function testAccesskeyFromInput()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Questions with hidden input cannot be tested on Windows');
        }

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['foo', 'bar']);
        $commandTester->execute(['--password' => null]);

        $this->assertContains('Please enter a username:', $commandTester->getDisplay());
        $this->assertContains('Please enter a password:', $commandTester->getDisplay());

        $this->assertFileExists($this->tmpdir.'/.env');

        $env = (new Dotenv())->parse(file_get_contents($this->tmpdir.'/.env'), $this->tmpdir.'/.env');

        $this->assertArrayHasKey('APP_DEV_ACCESSKEY', $env);
        $this->assertTrue(password_verify('foo:bar', $env['APP_DEV_ACCESSKEY']));
    }

    /**
     * Tests setting the access key interactively with a given username.
     */
    public function testAccesskeyWithUserFromInput()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Questions with hidden input cannot be tested on Windows');
        }

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['bar']);
        $commandTester->execute(['--user' => 'foo']);

        $this->assertFileExists($this->tmpdir.'/.env');
        $this->assertNotContains('Please enter a username:', $commandTester->getDisplay());
        $this->assertContains('Please enter a password:', $commandTester->getDisplay());

        $env = (new Dotenv())->parse(file_get_contents($this->tmpdir.'/.env'), $this->tmpdir.'/.env');

        $this->assertArrayHasKey('APP_DEV_ACCESSKEY', $env);
        $this->assertTrue(password_verify('foo:bar', $env['APP_DEV_ACCESSKEY']));
    }

    /**
     * Tests setting the access key interactively without a username.
     */
    public function testAccesskeyWithoutUserFromInput()
    {
        QuestionHelper::disableStty();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have username and password');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['foo']);
        $commandTester->execute(['--password' => 'bar']);
    }

    /**
     * Tests that the access key is appended to the .env file.
     */
    public function testAccesskeyAppendToDotEnv()
    {
        $this->filesystem->dumpFile($this->tmpdir.'/.env', 'FOO=bar');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--user' => 'foo', '--password' => 'bar']);

        $this->assertFileExists($this->tmpdir.'/.env');

        $env = (new Dotenv())->parse(file_get_contents($this->tmpdir.'/.env'), $this->tmpdir.'/.env');

        $this->assertArrayHasKey('FOO', $env);
        $this->assertSame('bar', $env['FOO']);
        $this->assertArrayHasKey('APP_DEV_ACCESSKEY', $env);
        $this->assertTrue(password_verify('foo:bar', $env['APP_DEV_ACCESSKEY']));
    }

    /**
     * Mocks the application.
     *
     * @return Application
     */
    private function mockApplication()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->tmpdir);

        $kernel = $this->createMock(KernelInterface::class);

        $kernel
            ->method('getContainer')
            ->willReturn($container)
        ;

        $application = new Application($kernel);
        $application->setCatchExceptions(true);

        return $application;
    }
}
