<?php

namespace WPPluginDevTools\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\StreamOutput;

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
            ->setDescription('tag and release current version')
            ->addOption('no-trunk', null, InputOption::VALUE_NONE, 'don\'t update the svn trunk')
            ->addOption('no-assets', null, InputOption::VALUE_NONE, 'don\'t update the svn assets')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'commit message', 'Release version %s');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        if (!$this->config['trunk']['dev_url'] && $this->config['trunk']['minimal']
            && false !== strpos($this->config['trunk']['readme_content'], '%dev-url%')
        ) {
            $this->exec("cd '{$this->path}'; git remote -v", $remotes);
            $remotes = join(PHP_EOL, $remotes);
            if (preg_match('#github.com[:/](.+)\.git#', $remotes, $match)) {
                $this->config['trunk']['dev_url'] = "https://github.com/{$match[1]}";
            } else {
                throw new \UnexpectedValueException('Unable to guess a dev url for the readme trunk from git remotes, adjust the trunk settings in plugin.yml');
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $info = $this->checkCmd->checkFiles($input, $output);

        if (!empty($info['warnings']) && $input->isInteractive()
            && !$this->getHelper('dialog')->askConfirmation($output, 'Checks generated warnings, continue?(Y/n)')
        ) {
            return;
        }

        $output->writeln('');

        $this->makePotCmd->makePot($input, $output);

        $output->writeln('');

        $output->writeln('Setting up svn repo');

        $tempDir = $this->tempDir($this->config['slug']);
        $output->writeln("Using temp dir $tempDir");

        $tag = $info['version'];
        $svnDir = "$tempDir/svn";

        $doTrunk = $this->config['trunk']['enabled'] && !$input->getOption('no-trunk');
        $doAssets = $this->config['assets']['enabled'] && !$input->getOption('no-assets');

        $this->checkout($svnDir, $tag);
        $output->writeln('Done sparse svn checkout');

        $filters = $this->getRsyncFilters($tempDir);
        $rsyncFiltersFile = "$tempDir/rsync-filters";
        file_put_contents($rsyncFiltersFile, join(PHP_EOL, $filters));

        $this->exec("rsync -a --delete '{$this->path}/' '$svnDir/tags/$tag' --filter='. $rsyncFiltersFile' --filter='P /.svn/'");
        $this->exec("svn add '$svnDir/tags/$tag/*' --non-interactive");

        if ($doTrunk) {
            if ($this->config['trunk']['minimal']) {
                $trunkReadme = str_replace(
                    array('%name%', '%version%', '%dev-url%'),
                    array($info['name'], $tag, $this->config['trunk']['dev_url']),
                    $this->config['trunk']['readme_content']
                );
                file_put_contents("$svnDir/trunk/readme.txt", $trunkReadme);
            } else {
                $this->exec("rsync -a --delete '{$this->path}/' '$svnDir/trunk' --filter='. $rsyncFiltersFile' --filter='P /.svn/'");
            }
            $this->exec("svn add '$svnDir/trunk/*' --non-interactive");
        }
        if ($doAssets) {
            $this->exec("rsync -a --delete '{$this->path}/".trim($this->config['assets']['dir'], '/')."/' '$svnDir/assets' --filter='P /.svn/'");
            $this->exec("svn add '$svnDir/assets/*' --non-interactive");
        }

        $commitMsg = sprintf($input->getOption('message'), $tag);
        if ($input->isInteractive()) {
            $this->exec_interactive("svn commit '$svnDir' -message='$commitMsg'", $input, $output);
        } else {
            $this->exec("svn commit '$svnDir' -message='$commitMsg' --non-interactive");
        }

        if (strpos($tempDir, sys_get_temp_dir()) === 0) {
            $output->writeln("Cleaning up temp dir $tempDir");
            $this->exec("/bin/rm -rf '$tempDir'");
        }
        $output->writeln('Finished');
    }

    private function tempDir($prefix)
    {
        $tempDir = tempnam(sys_get_temp_dir(), $prefix);
        unlink($tempDir);
        mkdir($tempDir);

        if (!is_dir($tempDir) || !strpos($tempDir, sys_get_temp_dir()) === 0) {
            throw new \RuntimeException("Problem creating temp dir $tempDir");
        }
        return $tempDir;
    }

    private function checkout($svnDir, $tag)
    {
        $svnUrl = $this->svnUrl();

        $this->exec("svn co --depth=immediates '$svnUrl' '$svnDir' --non-interactive");
        $this->exec("svn up --depth=immediates '$svnDir/tags' --non-interactive");

        if (is_dir("$svnDir/tags/$tag")) {
            $this->exec("svn up --depth=infinity '$svnDir/tags/$tag' --non-interactive");
        } else {
            $this->exec("svn mkdir '$svnDir/tags/$tag' --non-interactive");
        }
    }

    private function getRsyncFilters()
    {
        if (!$this->config['use_default_exclude_list'] && empty($this->config['exclude'])) {
            return array();
        }

        $rules = array();

        if ($this->config['use_default_exclude_list']) {
            $this->exec("
                cd '{$this->path}';
                git ls-files -oi --directory --exclude-standard;
                git ls-files -i --exclude-standard;
            ", $files);

            $rules = array_merge($rules, array_map(function ($f) {
                return "/$f";
            }, $files));

            $composerJsonPath = "{$this->path}/composer.json";
            if (is_readable($composerJsonPath)) {
                $composerConfig = json_decode(file_get_contents($composerJsonPath));
                if (!empty($composerConfig->archive->exclude) && is_array($composerConfig->archive->exclude)) {
                    $rules = array_merge($rules, $composerConfig->archive->exclude);
                }
            }

            $rules[] = '/.git/';
            $rules[] = '/.gitignore';
            $rules[] = '/plugin.yml';
            $rules[] = '/composer.json';
            $rules[] = '/' . trim($this->config['assets']['dir'], '/');
        }

        if (!empty($this->config['exclude'])) {
            $rules = array_merge($rules, $this->config['exclude']);
        }

        $filters = array_map(function ($f) {
            return ('!' === $f[0]) ? '+ ' . substr($f, 1) : '- ' . $f;
        }, $rules);

        return $filters;
    }
}
