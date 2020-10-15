<?php

namespace Statamic\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('version')
            ->setDescription('Get the version of Statamic installed in the current directory');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $please = new Please($output);

        if ($please->isV2()) {
            $please->run('version');
        } else {
            $please->run('--version');
        }

        return 0;
    }
}
