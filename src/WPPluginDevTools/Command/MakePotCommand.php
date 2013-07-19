<?php

namespace WPPluginDevTools\Command;

use MakePOT;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakePotCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('makepot')
            ->setDescription('update the pot file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->makePot($inpu, $output);
    }

    public function makePot(InputInterface $input, OutputInterface $output){
        $output->writeln('generating pot file');

        // MakePot would throw a lot of errors
        $errLevel = error_reporting();
        error_reporting($errLevel & ~(E_WARNING | E_NOTICE | E_STRICT | E_DEPRECATED));

        $makePOT = new MakePOT();

        $potfile = $this->path.'/'.trim($this->config['pot_location'], '/');

        if ('pot' !== pathinfo($potfile, PATHINFO_EXTENSION)) {
            $potfile .= "/{$this->config['slug']}.pot";
        }

        $result = $makePOT->wp_plugin($this->path, $potfile, $this->config['slug']);

        error_reporting($errLevel);

        $output->writeln('pot generation: '.($result ? '<info>OK</info>' : '<error>error</error>'));
    }

}
