<?php

namespace Statamic\Cli\Concerns;

use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

trait InteractsWithHerdOrValet
{
    protected function isParkedOnHerdOrValet(string $directory): bool
    {
        $paths = json_decode($this->runOnValetOrHerd('paths'));

        return is_array($paths)
            && (in_array(dirname($directory), $paths) || in_array(dirname($directory).DIRECTORY_SEPARATOR, $paths));
    }

    protected function generateAppUrl(string $name): string
    {
        $hostname = mb_strtolower($name).'.'.($this->runOnValetOrHerd('tld') ?: 'test');

        return gethostbyname($hostname.'.') !== $hostname.'.' ? 'http://'.$hostname : 'http://localhost';
    }

    protected function runOnValetOrHerd(string $command): string|false
    {
        foreach (['herd', 'valet'] as $tool) {
            $process = new Process([$tool, $command, '-v']);

            try {
                $process->run();

                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
            } catch (ProcessStartFailedException) {
            }
        }

        return false;
    }
}
