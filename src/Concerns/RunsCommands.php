<?php

namespace Statamic\Cli\Concerns;

use Symfony\Component\Process\Process;

trait RunsCommands
{
    /**
     * Run the given command.
     *
     * @param string $command
     * @return Process
     */
    protected function runCommand($command)
    {
        return $this->runCommands([$command]);
    }

    /**
     * Run the given commands.
     *
     * @param array $commands
     * @return Process
     */
    protected function runCommands($commands)
    {
        if (! $this->output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($this->input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: '.$e->getMessage());
            }
        }

        $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });

        return $process;
    }
}
