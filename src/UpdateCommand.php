<?php

namespace Statamic\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update the current directory\'s Statamic install to the latest version.');
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
        if (! is_dir(getcwd().'/statamic')) {
            throw new \RuntimeException('This does not appear to be a Statamic project.');
        }

        (new Please($output))->run('update');

        return 0;
    }
}
