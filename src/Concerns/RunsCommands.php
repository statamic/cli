<?php

namespace Statamic\Cli\Concerns;

use Symfony\Component\Process\Process;

trait RunsCommands
{
    /**
     * Run the given command.
     *
     * @param string $command
     * @param string|null $workingPath
     * @param bool $disableOutput
     * @return Process
     */
    protected function runCommand(string $command, string $workingPath = null, bool $disableOutput = false)
    {
        return $this->runCommands([$command], $workingPath, $disableOutput);
    }

    /**
     * Run the given commands.
     *
     * @param array $commands
     * @param string|null $workingPath
     * @param bool $disableOutput
     * @return Process
     */
    protected function runCommands(array $commands, string $workingPath = null, bool $disableOutput = false)
    {
        if (! $this->output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($this->input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        if ($disableOutput) {
            $process->disableOutput()->run();
        } else {
            $process->run(function ($type, $line) {
                $this->output->write('    '.$line);
            });
        }

        return $process;
    }
}
