<?php

namespace Statamic\Cli;

use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NewCommand extends Command
{
    use Downloader;
    use ZipManager;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $version;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Statamic application.')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('force-download', null, InputOption::VALUE_NONE, 'Force Statamic to be downloaded, even if a cached version exists.');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $this->output = $output;
        $this->input = $input;

        $dir = $this->input->getArgument('name');

        $this->verifyApplicationDoesntExist(
            $this->directory = getcwd().'/'.$dir
        );

        $this->version = $this->getVersion();

        $this
            ->download($zipName = $this->makeFilename())
            ->extract($zipName)
            ->cleanup($zipName)
            ->applyPermissions()
            ->createUser();

        $this->output->writeln("<info>[✔] Statamic has been installed into the <comment>{$dir}</comment> directory.</info>");

        return 0;
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if (is_dir($directory)) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/statamic_'.md5(time().uniqid());
    }

    protected function getVersion()
    {
        $this->output->write('Checking for the latest version...');

        $version = (new Client)
            ->get('https://outpost.statamic.com/v2/check')
            ->getBody();

        $this->output->writeln(" <info>[✔] $version</info>");

        return $version;
    }

    /**
     * Recursively apply permissions to appropriate directories.
     *
     * @return void
     */
    protected function applyPermissions()
    {
        $this->output->write('Updating file permissions...');

        foreach (['local', 'site', 'statamic', 'assets'] as $folder) {
            $dir = $this->directory . '/' . $folder;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterator as $item) {
                chmod($item, 0777);
            }
        }

        $this->output->writeln(" <info>[✔]</info>");

        return $this;
    }

    protected function createUser()
    {
        $questionText = 'Create a user? (yes/no) [<comment>no</comment>]: ';
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        if (! $helper->ask($this->input, $this->output, $question)) {
            $this->output->writeln("\x1B[1A\x1B[2K{$questionText}<fg=red>[✘]</>");
            $this->output->writeln("<comment>[!]</comment> You may create a user with <comment>php please make:user</comment>");
            return $this;
        }

        (new Please($this->output))
            ->cwd($this->directory)
            ->run('make:user');

        $this->output->writeln("\x1B[1A\x1B[2KUser created <info>[✔]</info>");
        $this->output->writeln('');

        return $this;
    }
}
