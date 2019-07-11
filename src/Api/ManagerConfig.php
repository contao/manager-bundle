<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Api;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ManagerConfig
{
    /**
     * @var string
     */
    private $configFile;

    /**
     * @var Filesystem|null
     */
    private $filesystem;

    /**
     * @var array
     */
    private $config;

    public function __construct(string $projectDir, Filesystem $filesystem = null)
    {
        if (false !== ($realpath = realpath($projectDir))) {
            $projectDir = (string) $realpath;
        }

        $this->configFile = $projectDir.'/config/contao-manager.yml';
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * @return mixed[]
     */
    public function all(): array
    {
        if (null === $this->config) {
            $this->read();
        }

        return $this->config;
    }

    /**
     * @return mixed[]
     */
    public function read(): array
    {
        if (!is_file($this->configFile)) {
            $this->config = [];
        } else {
            $this->config = Yaml::parse(file_get_contents($this->configFile));
        }

        return $this->config;
    }

    public function write(array $config): void
    {
        $this->config = $config;

        $this->filesystem->dumpFile($this->configFile, Yaml::dump($config));
    }
}
