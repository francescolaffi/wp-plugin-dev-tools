<?php


namespace WPPluginDevTools;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WPPluginDevTools\Command\MakePotCommand;
use WPPluginDevTools\Command\CheckCommand;
use WPPluginDevTools\Command\TagCommand;


class Application extends BaseApplication {

    const VERSION = 0.1;

    public function __construct()
    {
        parent::__construct('WP Plugin Dev Tools', self::VERSION);

        $this->add(new CheckCommand());
        $this->add(new MakePotCommand());
        $this->add(new TagCommand());
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        if (in_array($command->getName(), array('makepot', 'tag'))) {

        }
        return parent::doRunCommand($command, $input, $output);
    }


}