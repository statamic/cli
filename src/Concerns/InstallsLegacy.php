<?php

namespace Statamic\Cli\Concerns;

use GuzzleHttp\Client;
use RuntimeException;
use Statamic\Cli\Please;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZipArchive;

trait InstallsLegacy
{
    protected function installV2()
    {
        (new LegacyInstaller($this))->install();

        $this->showSuccessMessage();

        return 0;
    }
}

class LegacyInstaller
{
    protected $command;
    protected $version;
    protected $zipFilename;
    protected $progressBar;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function install()
    {
        $this
            ->verifyZipExtension()
            ->getLatestVersion()
            ->makeZipFilename()
            ->download()
            ->extract()
            ->cleanup()
            ->applyPermissions()
            ->createUser();
    }

    protected function verifyZipExtension()
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        return $this;
    }

    protected function getLatestVersion()
    {
        $this->command->output->write('Checking for the latest version...');

        $this->version = (new Client)
            ->get('https://outpost.statamic.com/v2/check')
            ->getBody();

        $this->command->output->writeln(" <info>[✔] {$this->version}</info>");

        return $this;
    }

    protected function makeZipFilename()
    {
        $this->zipFilename = getcwd().'/statamic_'.md5(time().uniqid());

        return $this;
    }

    protected function applyPermissions()
    {
        $this->command->output->write('Updating file permissions...');

        foreach (['local', 'site', 'statamic', 'assets'] as $folder) {
            $dir = $this->command->absolutePath.'/'.$folder;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterator as $item) {
                chmod($item, 0777);
            }
        }

        $this->command->output->writeln(' <info>[✔]</info>');

        return $this;
    }

    protected function createUser()
    {
        $questionText = 'Create a user? (yes/no) [<comment>no</comment>]: ';
        $helper = $this->command->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        if (! $helper->ask($this->command->input, $this->command->output, $question)) {
            $this->command->output->writeln("\x1B[1A\x1B[2K{$questionText}<fg=red>[✘]</>");
            $this->command->output->writeln('<comment>[!]</comment> You may create a user with <comment>php please make:user</comment>');

            return $this;
        }

        (new Please($this->command->output))
            ->cwd($this->command->absolutePath)
            ->run('make:user');

        $this->command->output->writeln("\x1B[1A\x1B[2KUser created <info>[✔]</info>");
        $this->command->output->writeln('');

        return $this;
    }

    protected function download()
    {
        $zipContents = $this->getZipFromServer();

        file_put_contents($this->zipFilename, $zipContents);

        return $this;
    }

    protected function getZipFromServer()
    {
        $this->command->output->writeln('Downloading...');
        $this->command->output->writeln('Press Ctrl+C to cancel.');

        $client = new Client([
            'progress' => function ($downloadSize, $downloaded) {
                if ($downloadSize === 0) {
                    return;
                }

                if ($this->progressBar === null) {
                    $this->createProgressBar($downloadSize);
                }

                $this->progressBar->setProgress($downloaded);
            },
        ]);

        $response = $client->get("https://outpost.statamic.com/v2/get/{$this->version}");

        $zipContents = $response->getBody();

        $this->command->output->writeln("\n<info>Download complete!</info>");

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

        $this->progressBar = new ProgressBar($this->command->output, $downloadSize);
        $this->progressBar->setFormat('%current% / %max% %bar% %percent:3s%%');
        $this->progressBar->setRedrawFrequency(max(1, floor($downloadSize / 1000)));
        $this->progressBar->setBarWidth(60);

        $this->progressBar->start();
    }

    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return number_format($bytes, 2).' '.$units[$pow];
    }

    protected function extract()
    {
        $this->command->output->write('Extracting zip...');

        $archive = new ZipArchive;

        $archive->open($this->zipFilename);

        $archive->extractTo($this->command->absolutePath.'_tmp');

        $archive->close();

        $this->command->output->writeln(' <info>[✔]</info>');

        return $this;
    }

    protected function cleanUp()
    {
        $this->command->output->write('Cleaning up...');

        rename($this->command->absolutePath.'_tmp/statamic', $this->command->absolutePath);

        @rmdir($this->command->absolutePath.'_tmp');

        @chmod($this->zipFilename, 0777);

        @unlink($this->zipFilename);

        $this->command->output->writeln(' <info>[✔]</info>');

        return $this;
    }
}
