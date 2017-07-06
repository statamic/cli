<?php

namespace Statamic\Cli;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Output\OutputInterface;

class Please
{
    protected $output;
    protected $cwd;

	public function __construct(OutputInterface $output)
	{
		$this->output = $output;
	}

    public function cwd($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

	public function run($command)
	{
        $process = (new ProcessBuilder)
            ->setTimeout(null)
            ->setWorkingDirectory($this->cwd)
            ->setPrefix([PHP_BINARY, 'please', $command])
            ->getProcess();

        try {
            $process->setTty(true);
        } catch (RuntimeException $e) {
            $this->output->writeln('Warning: '.$e->getMessage());
        }

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
	}
}
