<?php

namespace Statamic\Cli;

use ZipArchive;

trait ZipManager
{
    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function extract($zipFile)
    {
        $this->output->writeln('<info>Extracting zip...</info>');

        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($this->directory.'_tmp');

        $archive->close();

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
        $this->output->writeln('<info>Cleaning up...</info>');

        rename($this->directory.'_tmp/statamic', $this->directory);

        @rmdir($this->directory.'_tmp');

        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }
}
