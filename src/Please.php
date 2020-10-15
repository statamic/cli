<?php

namespace Statamic\Cli;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\RuntimeException;

class Please
{
    protected $output;
    protected $cwd;
    protected $v2;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function cwd($cwd = null)
    {
        if (func_num_args() === 0) {
            return $this->cwd ?? getcwd();
        }

        $this->cwd = $cwd;

        return $this;
    }

    public function isV2()
    {
        return is_dir($this->cwd().'/statamic');
    }

    public function run($command)
    {
        if (! is_file($this->cwd().'/please')) {
            throw new \RuntimeException('This does not appear to be a Statamic project.');
        }

        $process = (new Process([PHP_BINARY, 'please', $command]))
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
    }
}
