<?php

namespace Statamic\Cli;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class Artisan
{
    protected $output;
    protected $cwd;

    /**
     * Instantiate Statamic `please` command wrapper.
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Get or set current working directory.
     *
     * @param  mixed  $cwd
     * @return mixed
     */
    public function cwd($cwd = null)
    {
        if (func_num_args() === 0) {
            return $this->cwd ?? getcwd();
        }

        $this->cwd = $cwd;

        return $this;
    }

    /**
     * Run please command.
     *
     * @param  mixed  $commandParts
     * @return int
     */
    public function run(...$commandParts)
    {
        if (! is_file($this->cwd().'/artisan')) {
            throw new \RuntimeException('This does not appear to be a Laravel project.');
        }

        $process = (new Process(array_merge([PHP_BINARY, 'artisan'], $commandParts)))
            ->setTimeout(null);

        if ($this->cwd) {
            $process->setWorkingDirectory($this->cwd);
        }

        try {
            $process->setTty(true);
        } catch (RuntimeException $e) {
            // TTY not supported. Move along.
        }

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        return $process->getExitCode();
    }
}
