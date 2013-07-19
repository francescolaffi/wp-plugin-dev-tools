<?php

namespace WPPluginDevTools\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TagCommand extends Command
{

    /**
     * @var CheckCommand
     */
    protected $checkCmd;

    /**
     * @var MakePotCommand
     */
    protected $makePotCmd;

    public function __construct($name = null)
    {
        $this->subCommands['check'] = $this->checkCmd = new CheckCommand();
        $this->subCommands['makePot'] = $this->makePotCmd = new MakePotCommand();

        parent::__construct($name);
    }


    protected function configure()
    {
        parent::configure();
        $this->setName('tag')
            ->setDescription('tag and release current version');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkCmd->checkFiles($input, $output);

        $output->writeln('');

        $this->makePotCmd->makePot($input, $output);

        $info = $output->writeln('');


    }

}
