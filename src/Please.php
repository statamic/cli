<?php

namespace Statamic\Cli;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Output\OutputInterface;

class Please
{
	public function __construct(OutputInterface $output)
	{
		$this->output = $output;
	}

	public function run($command)
	{
        $process = (new ProcessBuilder)
            ->setTimeout(null)
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
