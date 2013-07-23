<?php

namespace WPPluginDevTools\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
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
            $path = realpath($input->getArgument('path'));
            if (!$path || !is_dir($path)) {
                throw new \UnexpectedValueException('Invalid plugin path: ' . $input->getArgument('path'));
            }
            $this->path = $path;
            $loader = new YamlPluginLoader();
            $this->config = $loader->load("$path/plugin.yml");
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

    protected function exec($command, &$lines = null)
    {
        exec($command, $lines, $code);
        if ($code !== 0) {
            throw new \RuntimeException("Command `$command` exited with code $code", $code);
        }
        return $code;
    }

    protected function exec_interactive($command, InputInterface $input, OutputInterface $output)
    {
        if (!$input->isInteractive()) {
            throw new \LogicException("Cannot run command `$command` interactively because input is not interactive");
        }

        $in = STDIN;
        $out = ($output instanceof StreamOutput) ? $output->getStream() : STDOUT;
        $err = ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput()->getStream() : STDERR;

        $proc = proc_open($command, array($in, $out, $err), $pipes);

        // blocking until process ends
        $code = proc_close($proc);

        if ($code !== 0) {
            throw new \RuntimeException("Command `$command` exited with code $code", $code);
        }

        return $code;
    }

    protected function svnUrl()
    {
        return $this->config['svn_repo'] ? : $this->config['svn_repo_base'] . '/' . $this->config['slug'];
    }
}
