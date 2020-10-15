<?php

namespace Statamic\Cli;

use Statamic\Cli\Concerns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    use Concerns\RunsCommands;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update the current directory\'s Statamic install to the latest version');
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
            $please->run('update');

            return 0;
        }

        $output->writeln(PHP_EOL.'<comment>NOTE: If you have previously updated using the CP, you may need to update the version in your composer.json before running this update!</comment>'.PHP_EOL);

        $this->runCommands(['composer update statamic/cms --with-dependencies'], $input, $output);

        return 0;
    }
}
