<?php

namespace Statamic\Cli\Concerns;

use Laravel\Prompts\Support\Logger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\task;

trait RunsCommands
{
    /**
     * Run the given command.
     *
     * @return Process
     */
    protected function runCommand(string $command, ?string $workingPath = null, bool $disableOutput = false, ?string $taskLabel = null)
    {
        return $this->runCommands([$command], $workingPath, $disableOutput, $taskLabel);
    }

    /**
     * Run the given commands.
     *
     * @return Process
     */
    protected function runCommands(array $commands, ?string $workingPath = null, bool $disableOutput = false, ?string $taskLabel = null)
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

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, timeout: null);

        if ($taskLabel && ! $disableOutput && $this->shouldRunAsTask()) {
            return $this->runProcessAsTask($process, $taskLabel);
        }

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                if ($this->input->hasOption('no-interaction') && $this->input->getOption('no-interaction')) {
                    $process->setTty(false);
                } else {
                    $process->setTty(true);
                }
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

    /**
     * Determine if the process should be rendered as a collapsible Prompts task.
     */
    private function shouldRunAsTask(): bool
    {
        return $this->output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL
            && $this->output->isDecorated()
            && function_exists('Laravel\Prompts\task')
            && function_exists('pcntl_fork');
    }

    /**
     * Run the given process within a Laravel Prompts task, streaming its output into the task's log.
     */
    private function runProcessAsTask(Process $process, string $taskLabel): Process
    {
        task(
            label: $taskLabel,
            keepSummary: true,
            callback: function (Logger $logger) use ($process) {
                $process->run(function ($type, $line) use ($logger) {
                    $logger->line($line);
                });

                if (! $process->isSuccessful()) {
                    $logger->error(trim($process->getErrorOutput()));
                }
            },
        );

        return $process;
    }
}
