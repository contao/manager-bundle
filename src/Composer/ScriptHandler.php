<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\ManagerBundle\Composer;

use Composer\Script\Event;
use Composer\Util\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Sets up the Contao Managed Edition.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class ScriptHandler
{
    /**
     * Runs all Composer tasks to initialize a Contao Managed Edition.
     *
     * @param Event $event
     */
    public static function initializeApplication(Event $event)
    {
        static::addAppDirectory();
        static::addWebEntryPoints($event);

        static::executeCommand('cache:clear', $event);
        static::executeCommand('assets:install --symlink --relative', $event);

        static::executeCommand('contao:install', $event);
        static::executeCommand('contao:symlinks', $event);
    }

    /**
     * Adds the app directory if it does not exist.
     */
    public static function addAppDirectory()
    {
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists(getcwd().'/app');
    }

    /**
     * Adds the web entry points.
     *
     * @param Event $event The event object
     *
     * @throws \RuntimeException
     */
    public static function addWebEntryPoints(Event $event)
    {
        static::executeCommand('contao:install-web-dir', $event);
    }

    /**
     * Executes a command.
     *
     * @param string $cmd
     * @param Event  $event
     *
     * @throws \RuntimeException
     */
    private static function executeCommand($cmd, Event $event)
    {
        $phpFinder = new PhpExecutableFinder();

        if (false === ($phpPath = $phpFinder->find())) {
            throw new \RuntimeException('The php executable could not be found.');
        }

        $process = new Process(
            sprintf(
                '%s %s%s %s%s --env=prod',
                escapeshellarg($phpPath),
                escapeshellarg(__DIR__.'/../../bin/contao-console'),
                $event->getIO()->isDecorated() ? ' --ansi' : '',
                $cmd,
                self::getVerbosityFlag($event)
            )
        );

        $process->run(
            function ($type, $buffer) use ($event) {
                $event->getIO()->write($buffer, false);
            }
        );

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('An error occurred while executing the "%s" command.', $cmd));
        }
    }

    /**
     * Returns the verbosity flag depending on the console IO verbosity.
     *
     * @param Event $event
     *
     * @return string
     */
    private static function getVerbosityFlag(Event $event)
    {
        $io = $event->getIO();

        switch (true) {
            case $io->isVerbose():
                return ' -v';

            case $io->isVeryVerbose():
                return ' -vv';

            case $io->isDebug():
                return ' -vvv';

            default:
                return '';
        }
    }
}
