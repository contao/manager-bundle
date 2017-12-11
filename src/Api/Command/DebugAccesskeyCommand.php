<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\ManagerBundle\Api\Command;

use Contao\ManagerBundle\Api\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DebugAccesskeyCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('debug:access-key')
            ->setDescription('Sets or removes the debug access key.')
            ->addArgument('value', InputArgument::OPTIONAL, 'The access key to write, or empty to remove it.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $application = $this->getApplication();

        if (!$application instanceof Application) {
            throw new \RuntimeException('The application has not been set');
        }

        $this->updateDotEnv($application->getProjectDir(), 'APP_DEV_ACCESSKEY', $input->getArgument('value'));
    }

    /**
     * Appends value to the .env file, removing a line with the given key.
     *
     * @param string      $projectDir
     * @param string      $key
     * @param string|null $value
     */
    private function updateDotEnv(string $projectDir, string $key, ?string $value): void
    {
        $fs = new Filesystem();

        $path = $projectDir.'/.env';
        $exists = $fs->exists($path);
        $content = '';

        if ($exists) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if (false === $lines) {
                throw new \RuntimeException(sprintf('Could not read "%s" file.', $path));
            }

            foreach ($lines as $line) {
                if (0 === strpos($line, $key.'=')) {
                    continue;
                }

                $content .= $line."\n";
            }
        }

        if (null !== $value) {
            $content .= $key.'='.escapeshellarg($value)."\n";
        }

        if (empty($content)) {
            if ($exists) {
                $fs->remove($path);
            }

            return;
        }

        $fs->dumpFile($path, $content);
    }
}
