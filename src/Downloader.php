<?php

namespace Statamic\Cli;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Helper\ProgressBar;

trait Downloader
{
    protected $progressBar;

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $this->cacheGarbageCollection();

        $zipContents = ($this->shouldUseCachedZip())
            ? $this->getCachedZip()
            : $this->getZipFromServer();

        file_put_contents($zipFile, $zipContents);

        return $this;
    }

    protected function shouldUseCachedZip()
    {
        if ($this->input->getOption('force-download')) {
            return false;
        }

        return file_exists($this->getCachedZipFilename());
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

        $this->attemptToCacheDownloadedZip($zipContents = $response->getBody());

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

    protected function getCachedZip()
    {
        $this->output->writeln('Downloading... <info>[✔] From cache</info>');

        return file_get_contents($this->getCachedZipFilename());
    }

    protected function cacheGarbageCollection()
    {
        foreach (glob($this->getCachedDownloadsDirectory().'/*.zip') as $zip) {
            if ($zip !== $this->getCachedZipFilename()) {
                unlink($zip);
            }
        }
    }

    protected function attemptToCacheDownloadedZip($zip)
    {
        if (! is_dir($dir = $this->getCachedDownloadsDirectory())) {
            @mkdir($dir, 0755, true);
            @chown($dir, $_SERVER['SUDO_USER'] ?? $_SERVER['USER']);
        }

        @file_put_contents($this->getCachedZipFilename(), $zip);
    }

    protected function getCachedZipFilename()
    {
        return sprintf('%s/%s.zip', $this->getCachedDownloadsDirectory(), $this->version);
    }

    protected function getCachedDownloadsDirectory()
    {
        return STATAMIC_HOME_PATH . '/cache';
    }
}
