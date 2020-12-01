<?php

namespace Statamic\Cli;

use RuntimeException;
use Statamic\Cli\Concerns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class NewCommand extends Command
{
    use Concerns\RunsCommands,
        Concerns\InstallsLegacy;

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
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('starter', null, InputOption::VALUE_OPTIONAL, 'Install a specific starter kit', false)
            ->addOption('v2', null, InputOption::VALUE_NONE, 'Create a legacy Statamic v2 application (not recommended)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the dirctory already exists');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $please = new Please($output);

        if ($input->getOption('v2')) {
            return $this->installV2($input, $output);
        }

        $this->testQuestions($input, $output);

        $output->write(PHP_EOL.'<fg=red>   _____ __        __                  _
  / ___// /_____ _/ /_____ _____ ___  (_)____
  \__ \/ __/ __ `/ __/ __ `/ __ `__ \/ / ___/
 ___/ / /_/ /_/ / /_/ /_/ / / / / / / / /__
/____/\__/\__,_/\__/\__,_/_/ /_/ /_/_/\___/</>'.PHP_EOL.PHP_EOL);

        sleep(1);

        $name = $input->getArgument('name');

        $this->testQuestions($input, $output);
        $directory = $name && $name !== '.' ? getcwd().'/'.$name : '.';

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $commands = [
            $this->createProjectCommand($repo, $directory)
        ];

        if ($directory != '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY == 'Windows') {
                array_unshift($commands, "rd /s /q \"$directory\"");
            } else {
                array_unshift($commands, "rm -rf \"$directory\"");
            }
        }

        if (PHP_OS_FAMILY != 'Windows') {
            $commands[] = "chmod 755 \"$directory/artisan\"";
            $commands[] = "chmod 755 \"$directory/please\"";
        }

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            if ($name && $name !== '.') {
                $this->replaceInFile(
                    'APP_URL=http://localhost',
                    'APP_URL=http://'.$name.'.test',
                    $directory.'/.env'
                );

                $this->replaceInFile(
                    'DB_DATABASE=laravel',
                    'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
                    $directory.'/.env'
                );
            }

            $this->testQuestions($input, $output);

            $success = $name
                ? "Statamic has been successfully installed into the <comment>{$name}</comment> directory."
                : "Statamic has been successfully installed.";

            $output->writeln(PHP_EOL."<info>[✔] {$success}</info>");
            $output->writeln("Build something rad!");
        }

        return $process->getExitCode();
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
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
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $file
     * @return string
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Determine the starter repo.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function repo(InputInterface $input, OutputInterface $output)
    {
        $starter = $input->getOption('starter');

        $starterKits = YAML::parse(file_get_contents(__DIR__.'/../resources/starter-kits.yaml'));

        $blank = ['statamic/statamic'];
        $official = $starterKits['official'];
        $thirdParty = $starterKits['third_party'];

        asort($thirdParty);

        $repositories = array_merge($blank, $official, $thirdParty);

        if ($starter && in_array($starter, $repositories)) {
            return $starter;
        } elseif ($starter) {
            $output->writeln(PHP_EOL."<error>Could not find starter kit [$starter]</error>");
        }

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion('Which starter kit would you like to install from? [<comment>statamic/statamic</comment>]', $repositories, 0);

        $output->write(PHP_EOL);

        return $helper->ask($input, new SymfonyStyle($input, $output), $question);
    }

    /**
     * Create the composer create-project command.
     *
     * @param string $repo
     * @param string $directory
     * @return string
     */
    protected function createProjectCommand($repo, $directory)
    {
        $composer = $this->findComposer();

        $command = $composer." create-project $repo \"$directory\" --remove-vcs --prefer-dist";

        if ($repo !== 'statamic/statamic') {
            $command .= sprintf(
                ' --repository="{\"url\": \"%s\", \"type\": \"vcs\"}" --stability="dev"',
                "https://github.com/$repo"
            );
        }

        return $command;
    }

    protected function testQuestions(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $output->write(PHP_EOL);

        $question = new ConfirmationQuestion('Would you like to create a user? [<comment>no</comment>]', false);
        $createUser = $helper->ask($input, new SymfonyStyle($input, $output), $question);

        if (! $createUser) {
            $question = new ConfirmationQuestion('Would you like to enable Statamic Pro? [<comment>no</comment>]', false);
            $enablePro = $helper->ask($input, new SymfonyStyle($input, $output), $question);
            return;
        }

        $question = new ConfirmationQuestion('Creating more users later will require Statamic Pro, would you like to enable Pro now? [<comment>no</comment>]', false);
        $enablePro = $helper->ask($input, new SymfonyStyle($input, $output), $question);



        var_dump($createUser);
        die;
    }
}
