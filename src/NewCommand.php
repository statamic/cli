<?php

namespace Statamic\Cli;

use RuntimeException;
use Statamic\Cli\Concerns;
use Statamic\Cli\Please;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class NewCommand extends Command
{
    use Concerns\RunsCommands,
        Concerns\InstallsLegacy;

    const BASE_REPO = 'statamic/statamic';

    public $input;
    public $output;
    public $relativePath;
    public $absolutePath;
    public $name;
    public $starterKit;
    public $withConfig;
    public $force;
    public $v2;
    public $baseInstallSuccessful;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Statamic application')
            ->addArgument('name', InputArgument::REQUIRED, 'Statamic application directory name')
            ->addArgument('starter-kit', InputArgument::OPTIONAL, 'Optionally install specific starter kit')
            ->addOption('with-config', null, InputOption::VALUE_NONE, 'Optionally copy starter-kit.yaml config for development')
            ->addOption('v2', null, InputOption::VALUE_NONE, 'Create a legacy Statamic v2 application (not recommended)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists');
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this
            ->processArguments()
            ->validateArguments()
            ->showStatamicTitleArt();

        if ($this->v2) {
            return $this->installV2();
        }

        $this
            ->askForRepo()
            ->installBaseProject()
            ->installStarterKit()
            ->makeSuperUser()
            ->showSuccessMessage();

        return 0;
    }

    /**
     * Process arguments and options.
     *
     * @return $this
     */
    protected function processArguments()
    {
        $this->relativePath = $this->input->getArgument('name');

        $this->absolutePath = $this->relativePath && $this->relativePath !== '.'
            ? getcwd().'/'.$this->relativePath
            : getcwd();

        $this->name = pathinfo($this->absolutePath)['basename'];

        $this->starterKit = $this->input->getArgument('starter-kit');
        $this->withConfig = $this->input->getOption('with-config');

        $this->force = $this->input->getOption('force');

        $this->v2 = $this->input->getOption('v2');

        return $this;
    }

    /**
     * Validate arguments and options.
     *
     * @return $this
     * @throws RuntimeException
     */
    protected function validateArguments()
    {
        if (! $this->force && $this->applicationExists()) {
            throw new RuntimeException('Application already exists!');
        }

        if ($this->force && $this->pathIsCwd()) {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        if ($this->starterKit && $this->v2) {
            throw new RuntimeException('Cannot use starter kit with legacy v2 installation!');
        }

        if ($this->starterKit && $this->isInvalidStarterKit()) {
            throw new RuntimeException('Please enter a valid composer package name (eg. hasselhoff/kung-fury)!');
        }

        if (! $this->starterKit && $this->withConfig) {
            throw new RuntimeException('Starter kit is required when using `--with-config` option!');
        }

        return $this;
    }

    /**
     * Show Statamic title art.
     *
     * @return $this
     */
    protected function showStatamicTitleArt()
    {
        $this->output->write(PHP_EOL.'<fg=red>   _____ __        __                  _
  / ___// /_____ _/ /_____ _____ ___  (_)____
  \__ \/ __/ __ `/ __/ __ `/ __ `__ \/ / ___/
 ___/ / /_/ /_/ / /_/ /_/ / / / / / / / /__
/____/\__/\__,_/\__/\__,_/_/ /_/ /_/_/\___/</>'.PHP_EOL.PHP_EOL);

        return $this;
    }

    /**
     * Ask which starter repo to install.
     *
     * @return $this
     */
    protected function askForRepo()
    {
        if ($this->starterKit || ! $this->input->isInteractive()) {
            return $this;
        }

        $starterKits = YAML::parse(file_get_contents(__DIR__.'/../resources/starter-kits.yaml'));

        $baseRepo = self::BASE_REPO;
        $official = $starterKits['official'];
        $thirdParty = $starterKits['third_party'];

        asort($thirdParty);

        $repositories = array_merge([$baseRepo], $official, $thirdParty);

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion("Which starter kit would you like to install from? [<comment>{$baseRepo}</comment>]", $repositories, 0);

        $repo = $helper->ask($this->input, new SymfonyStyle($this->input, $this->output), $question);

        $this->output->write(PHP_EOL);

        if ($repo !== $baseRepo) {
            $this->starterKit = $repo;
        }

        return $this;
    }

    /**
     * Install base project.
     *
     * @return $this
     * @throws RuntimeException
     */
    protected function installBaseProject()
    {
        $commands = [];

        if ($this->force && ! $this->pathIsCwd()) {
            if (PHP_OS_FAMILY == 'Windows') {
                $commands[] = "rd /s /q \"$this->absolutePath\"";
            } else {
                $commands[] = "rm -rf \"$this->absolutePath\"";
            }
        }

        $commands[] = $this->createProjectCommand();

        if (PHP_OS_FAMILY != 'Windows') {
            $commands[] = "chmod 755 \"$this->absolutePath/artisan\"";
            $commands[] = "chmod 755 \"$this->absolutePath/please\"";
        }

        $this->runCommands($commands);

        if (! $this->wasBaseInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Statamic!');
        }

        $this->updateEnvVars();

        $this->baseInstallSuccessful = true;

        return $this;
    }

    /**
     * Install starter kit.
     *
     * @return $this
     * @throws RuntimeException
     */
    protected function installStarterKit()
    {
        if (! $this->baseInstallSuccessful || ! $this->starterKit) {
            return $this;
        }

        $options = ['--no-interaction', '--clear-site'];

        if ($this->withConfig) {
            $options[] = '--with-config';
        }

        $statusCode = (new Please($this->output))
            ->cwd($this->absolutePath)
            ->run('starter-kit:install', $this->starterKit, ...$options);

        if ($statusCode !== 0) {
            throw new RuntimeException('There was a problem installing Statamic with the chosen starter kit!');
        }

        return $this;
    }

    /**
     * Make super user.
     *
     * @return $this
     */
    protected function makeSuperUser()
    {
        if (! $this->input->isInteractive()) {
            return $this;
        }

        $questionText = 'Create a super user? (yes/no) [<comment>no</comment>]: ';
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        $this->output->write(PHP_EOL);

        if (! $helper->ask($this->input, $this->output, $question)) {
            return $this;
        }

        (new Please($this->output))
            ->cwd($this->absolutePath)
            ->run('make:user', '--super');

        return $this;
    }

    /**
     * Show success message.
     *
     * @return $this
     */
    protected function showSuccessMessage()
    {
        $this->output->writeln(PHP_EOL."<info>[✔] Statamic has been successfully installed into the <comment>{$this->relativePath}</comment> directory.</info>");

        $this->output->writeln("Build something rad!");

        return $this;
    }

    /**
     * Check if the application path already exists.
     *
     * @return bool
     */
    protected function applicationExists()
    {
        if ($this->pathIsCwd()) {
            return is_file("{$this->absolutePath}/composer.json");
        }

        return is_dir($this->absolutePath) || is_file($this->absolutePath);
    }

    /**
     * Check if the application path is the current working directory.
     *
     * @return bool
     */
    protected function pathIsCwd()
    {
        return $this->absolutePath === getcwd();
    }

    /**
     * Check if the starter kit is invalid.
     *
     * @return bool
     */
    protected function isInvalidStarterKit()
    {
        return ! preg_match("/^[^\/\s]+\/[^\/\s]+$/", $this->starterKit);
    }

    /**
     * Determine if base install was successful.
     *
     * @return bool
     */
    protected function wasBaseInstallSuccessful()
    {
        return is_file("{$this->absolutePath}/composer.json")
            && is_dir("{$this->absolutePath}/vendor")
            && is_file("{$this->absolutePath}/artisan")
            && is_file("{$this->absolutePath}/please");
    }

    /**
     * Create the composer create-project command.
     *
     * @return string
     */
    protected function createProjectCommand()
    {
        $composer = $this->findComposer();

        $baseRepo = self::BASE_REPO;

        $directory = $this->pathIsCwd() ? '.' : $this->relativePath;

        return $composer." create-project {$baseRepo} \"{$directory}\" --remove-vcs --prefer-dist";
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }

    /**
     * Update application env vars.
     */
    protected function updateEnvVars()
    {
        $this->replaceInFile(
            'APP_URL=http://localhost',
            'APP_URL=http://'.$this->name.'.test',
            $this->absolutePath.'/.env'
        );

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($this->name)),
            $this->absolutePath.'/.env'
        );
    }

    /**
     * Replace the given string in the given file.
     *
     * @param string $search
     * @param string $replace
     * @param string $file
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }
}
