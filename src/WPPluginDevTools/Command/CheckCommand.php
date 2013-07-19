<?php

namespace WPPluginDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends Command
{
    const REGEX_MAIN_FILE = '/\n[ \t\/*#@]*Plugin Name:\s*(?<name>[^\n]*\S).*\n[ \t\/*#@]*Version:\s*(?<vers>\S+)/si';
    const REGEX_README = '/^===\s*(?<name>[\S ]+?)\s*===.*\nStable tag:\s*(?<vers>\S+)/si';
    const REGEX_VERSION = '[0-9]+(\.[0-9]+)*';

    protected function configure()
    {
        parent::configure();
        $this->setName('check')
            ->setDescription('check plugin version consistency')
            ->addOption('no-svn-check', null, InputOption::VALUE_NONE, 'don\'t check existing svn tags');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkFiles($input, $output);
    }

    public function checkFiles(InputInterface $input, OutputInterface $output)
    {
        $error = false;
        $versions = array();
        $warnings = array();

        // (1) find and parse main file
        $output->write('searching main file: ');
        $maybeMainFile = glob("{$this->path}/*.php");
        // if a file is named as the plugin slug look that first
        $slugMainFile = "{$this->path}/{$this->config['slug']}.php";
        if ($k = array_search($slugMainFile, $maybeMainFile)) {
            unset($maybeMainFile[$k]);
            array_unshift($maybeMainFile, $slugMainFile);
        }
        $mainFile = false;
        foreach ($maybeMainFile as $file) {
            $content = file_get_contents($file, false, null, -1, 8192); #8kb only, same as wp
            if (preg_match(self::REGEX_MAIN_FILE, $content, $match)) {
                $mainFile = $file;
                $versions[] = $match['vers'];
                break;
            }
        }
        if ($mainFile) {
            $output->writeln('<info>OK</info>');
            $output->writeln('['.basename($mainFile)."] vers:<info>{$match['vers']}</info>");
        } else {
            $output->writeln('<error>error!</error> main file not found or invalid');
            $error = true;
        }

        // (2) parse readme.txt
        $output->write("[readme.txt] ");
        if (!is_readable("{$this->path}/readme.txt")) {
            $output->writeln('<error>error!</error> file not found');
            $error = true;
        } elseif (!preg_match(self::REGEX_README, file_get_contents("{$this->path}/readme.txt"), $match)) {
            $output->writeln('<error>error!</error> invalid');
            $error = true;
        } else {
            $name = $match['name'];
            $versions[] = $match['vers'];
            ## todo: check changelog and notice presence
            $output->writeln("vers:<info>{$match['vers']}</info> name:{$match['name']}");
        }

        # (3) check custom locations
        foreach ($this->config['version_locations'] as $file => $regex) {
            $output->write("[$file] ");
            $file = "{$this->path}/$file";;
            if (!is_readable($file)) {
                $output->writeln('<error>error!</error> file not found');
                $error = true;
            } elseif (!preg_match($regex, file_get_contents($file), $match) || empty($match['vers'])) {
                $output->writeln('<error>error!</error> version not found');
                $error = true;
            } else {
                $versions[] = $v = trim($match['vers']);
                $output->writeln("vers:<info>{$match['vers']}</info>");
            }
        }

        // (4) check version format and uniqueness
        $versions = array_unique($versions);
        if (count($versions) > 1) {
            $output->writeln('<error>Error:</error> multiple versions found');
            $error = true;
        }
        $invalid = array_filter($versions, function ($v) {
            return !preg_match('/^' . self::REGEX_VERSION . '$/', $v);
        });
        if (count($invalid) > 0) {
            $output->writeln('<error>Error:</error> invalid version formats '.join(' ', $invalid).' (only numbers joined by periods allowed)');
            $error = true;
        }

        if ($error) exit(1);

        $vers = $versions[0];

        // (5) check existing tags
        if ($input->getOption('no-svn-check')) {
            $output->writeln('Skipping check of existing svn tags');
        } else {
            $output->writeln('Checking existing svn tags');

            $svnUrl = $this->config['svn_repo'].'/'.$this->config['slug'];
            $tags = `svn ls "$svnUrl/tags" --non-interactive`;
            $tags = array_filter(array_map(function ($s) {
                return trim($s, '/');
            }, explode("\n", $tags)));

            usort($tags, 'version_compare');

            $error = false;
            if (in_array($vers, $tags)) {
                $output->writeln("<error>Error:</error> $vers already tagged");
                $error = true;
            }
            $highestVers = end($tags);
            if(version_compare($highestVers, $vers, '>')) {
                $output->writeln("<error>Error:</error> higher version $highestVers found");
                $error = true;
            }

            if ($error) exit(1);

            $output->writeln('<info>OK</info> no conflict with tagged versions');
        }

        $output->writeln('<info>All checks completed</info>');

        return array(
            'name' => $name,
            'version' => $vers,
            'warnings' => $warnings
        );
    }
}
