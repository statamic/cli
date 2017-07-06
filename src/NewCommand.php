<?php

namespace Statamic\Cli;

use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @return void
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
            ->cleanup($zipName);

        $this->output->writeln("<info>[✔] Statamic has been installed into the <comment>{$dir}</comment> directory.</info>");
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
}
