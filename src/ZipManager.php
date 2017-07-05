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
