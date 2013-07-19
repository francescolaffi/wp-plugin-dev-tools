<?php

namespace WPPluginDevTools\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WPPluginDevTools\Config\YamlPluginLoader;

/**
 * Class Command
 * @package WPPluginDevTools\Command
 */
class Command extends BaseCommand
{

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Command[]
     */
    protected $subCommands = array();

    public function __construct($name = null)
    {
        parent::__construct($name);

        foreach ($this->subCommands as $cmd) {
            $this->getDefinition()->addOptions($cmd->getDefinition()->getOptions());
        }
    }


    protected function configure()
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'plugin path', '.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->path) {
            $realpath = realpath($input->getArgument('path'));
            if (!$realpath || !is_dir($realpath)) {
                throw new \UnexpectedValueException('Invalid plugin path: ' . $input->getArgument('path'));
            }
            $this->path = $realpath;
        }
        if (null === $this->config) {
            $loader = new YamlPluginLoader();
            $this->config = $loader->load("$this->path/plugin.yml");
        }

        foreach ($this->subCommands as $cmd) {
            $cmd->setPath($this->path);
            $cmd->setConfig($this->config);
        }
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }
}
