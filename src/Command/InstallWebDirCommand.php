<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Command;

use Contao\CoreBundle\Command\AbstractLockedCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Installs the web entry points for Contao Managed Edition.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class InstallWebDirCommand extends AbstractLockedCommand
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * Files that should not be copied if they exist in the web directory.
     *
     * @var array
     */
    private $optionalFiles = [
        '.htaccess',
    ];

    /**
     * Files that should not be copied on no-dev option.
     *
     * @var array
     */
    private $devFiles = [
        'app_dev.php',
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('contao:install-web-dir')
            ->setDescription('Generates entry points in /web directory.')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'The installation root directory',
                getcwd()
            )
            ->addOption(
                'no-dev',
                null,
                InputOption::VALUE_NONE,
                'Do not copy the app_dev.php entry point to the web folder'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Set the username for the app_dev.php entry point',
                false
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Set the password for the app_dev.php entry point',
                false
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $user = $input->getOption('user');
        $password = $input->getOption('password');

        if ((false !== $user || false !== $password) && true === $input->getOption('no-dev')) {
            throw new \InvalidArgumentException('Cannot set a password in no-dev mode!');
        }

        // Return if both username and password are set or both are not set
        if (($user && $password) || (false === $user && false === $password)) {
            return;
        }

        // A password is given on the command line but no user
        if (false === $user && $password) {
            throw new \InvalidArgumentException('Must have username and password');
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (false === $user) {
            $input->setOption(
                'user',
                $helper->ask($input, $output, new Question('Please enter a username:'))
            );
        }

        $input->setOption(
            'password',
            $helper->ask($input, $output, (new Question('Please enter a password:'))->setHidden(true))
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeLocked(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->io = new SymfonyStyle($input, $output);

        $projectDir = $input->getArgument('path');
        $webDir = rtrim($projectDir, '/').'/web';

        $this->addFiles($webDir, !$input->getOption('no-dev'));
        $this->removeInstallPhp($webDir);
        $this->storeAppDevAccesskey($input, $projectDir);

        return 0;
    }

    /**
     * Adds files from Resources/web to the application's web directory.
     *
     * @param string $webDir
     * @param bool   $dev
     */
    private function addFiles($webDir, $dev = true)
    {
        /** @var Finder $finder */
        $finder = Finder::create()->files()->ignoreDotFiles(false)->in(__DIR__.'/../Resources/web');

        foreach ($finder as $file) {
            if (
                \in_array($file->getRelativePathname(), $this->optionalFiles, true)
                && $this->fs->exists($webDir.'/'.$file->getRelativePathname())
            ) {
                continue;
            }

            if (!$dev && \in_array($file->getRelativePathname(), $this->devFiles, true)) {
                continue;
            }

            $this->fs->copy($file->getPathname(), $webDir.'/'.$file->getRelativePathname(), true);
            $this->io->text(sprintf('Added/updated the <comment>web/%s</comment> file.', $file->getFilename()));
        }
    }

    /**
     * Removes the install.php entry point leftover from Contao <4.4.
     *
     * @param string $webDir
     */
    private function removeInstallPhp($webDir)
    {
        if (!file_exists($webDir.'/install.php')) {
            return;
        }

        $this->fs->remove($webDir.'/install.php');
        $this->io->text('Deleted the <comment>web/install.php</comment> file.');
    }

    /**
     * Stores username and password in .env file in the project directory.
     *
     * @param InputInterface $input
     * @param string         $projectDir
     */
    private function storeAppDevAccesskey(InputInterface $input, $projectDir)
    {
        $user = $input->getOption('user');
        $password = $input->getOption('password');

        if (false === $password && false === $user) {
            return;
        }

        if (($user || $password) && true === $input->getOption('no-dev')) {
            throw new \InvalidArgumentException('Cannot set a password in no-dev mode!');
        }

        if (!$user || !$password) {
            throw new \InvalidArgumentException('Must have username and password to set the access key.');
        }

        $accessKey = password_hash(
            $input->getOption('user').':'.$input->getOption('password'),
            PASSWORD_DEFAULT
        );

        $this->addToDotEnv($projectDir, 'APP_DEV_ACCESSKEY', $accessKey);
    }

    /**
     * Appends value to the .env file, removing a line with the given key.
     *
     * @param string $projectDir
     * @param string $key
     * @param string $value
     */
    private function addToDotEnv($projectDir, $key, $value)
    {
        $fs = new Filesystem();

        $path = $projectDir.'/.env';
        $content = '';

        if ($fs->exists($path)) {
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

        // Escape the $ character as escapeshellarg() will use double quotes on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $value = addcslashes($value, '$');
        }

        $fs->dumpFile($path, $content.$key.'='.escapeshellarg($value)."\n");
    }
}
