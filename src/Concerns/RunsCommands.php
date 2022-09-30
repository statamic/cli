<?php

namespace Statamic\Cli\Concerns;

use Symfony\Component\Process\Process;

trait RunsCommands
{
    /**
     * Run the given command.
     *
     * @param  string  $command
     * @param  bool  $disableOutput
     * @return Process
     */
    protected function runCommand($command, $disableOutput = false)
    {
        return $this->runCommands([$command], $disableOutput);
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param  bool  $disableOutput
     * @return Process
     */
    protected function runCommands($commands, $disableOutput = false)
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

        if ($disableOutput) {
            $process->disableOutput()->run();
        } else {
            $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        }

        return $process;
    }
}
