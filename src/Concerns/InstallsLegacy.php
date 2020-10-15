<?php

namespace Statamic\Cli\Concerns;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use RuntimeException;
use Statamic\Cli\Please;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZipArchive;

trait InstallsLegacy
{
    protected $input;
    protected $output;
    protected $directory;
    protected $version;
    protected $progressBar;

    /**
     * Install Statamic v2.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function installV2(InputInterface $input, OutputInterface $output)
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

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $zipContents = $this->getZipFromServer();

        file_put_contents($zipFile, $zipContents);

        return $this;
    }

    protected function getZipFromServer()
    {
        $this->output->writeln('Downloading...');
        $this->output->writeln('Press Ctrl+C to cancel.');

        $client = new Client([
            'progress' => function ($downloadSize, $downloaded) {
                if ($downloadSize === 0) {
                    return;
                }

                if ($this->progressBar === null) {
                    $this->createProgressBar($downloadSize);
                }

                $this->progressBar->setProgress($downloaded);
            }
        ]);

        $response = $client->get("https://outpost.statamic.com/v2/get/{$this->version}");

        $zipContents = $response->getBody();

        $this->output->writeln("\n<info>Download complete!</info>");

        return $zipContents;
    }

    protected function createProgressBar($downloadSize)
    {
        ProgressBar::setPlaceholderFormatterDefinition('max', function (ProgressBar $bar) {
            return $this->formatBytes($bar->getMaxSteps());
        });

        ProgressBar::setPlaceholderFormatterDefinition('current', function (ProgressBar $bar) {
            return str_pad($this->formatBytes($bar->getProgress()), 11, ' ', STR_PAD_LEFT);
        });

        $this->progressBar = new ProgressBar($this->output, $downloadSize);
        $this->progressBar->setFormat('%current% / %max% %bar% %percent:3s%%');
        $this->progressBar->setRedrawFrequency(max(1, floor($downloadSize / 1000)));
        $this->progressBar->setBarWidth(60);

        $this->progressBar->start();
    }

    protected function formatBytes($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return number_format($bytes, 2).' '.$units[$pow];
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function extract($zipFile)
    {
        $this->output->write('Extracting zip...');

        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($this->directory.'_tmp');

        $archive->close();

        $this->output->writeln(' <info>[✔]</info>');

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        $this->output->write('Cleaning up...');

        rename($this->directory.'_tmp/statamic', $this->directory);

        @rmdir($this->directory.'_tmp');

        @chmod($zipFile, 0777);

        @unlink($zipFile);

        $this->output->writeln(' <info>[✔]</info>');

        return $this;
    }
}
