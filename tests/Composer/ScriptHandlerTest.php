<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\Composer;

use Contao\ManagerBundle\Composer\ScriptHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandlerTest extends TestCase
{
    public function testInitializeApplicationMethodExists(): void
    {
        $this->assertTrue(method_exists(ScriptHandler::class, 'initializeApplication'));
    }

    public function testAddAppDirectory(): void
    {
        $filesystem = new Filesystem();
        $tempdir = sys_get_temp_dir().'/ScriptHandlerTest';

        if ($filesystem->exists($tempdir)) {
            $filesystem->remove($tempdir);
        }

        $filesystem->mkdir($tempdir);
        chdir($tempdir);

        ScriptHandler::addAppDirectory();

        $this->assertDirectoryExists($tempdir.'/app');

        $filesystem->remove($tempdir.'/app');
    }
}
