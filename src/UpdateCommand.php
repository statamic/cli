<?php

namespace Statamic\Cli;

use Statamic\Cli\Concerns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        $command = $this->updateCommand($input, $output);

        $this->runCommands([$command], $input, $output);

        return 0;
    }

    /**
     * Determine the update command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function updateCommand(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $options = [
            'Update Statamic and its dependencies [composer update statamic/cms --with-dependencies]',
            'Update all project dependencies [composer update]',
        ];

        $question = new ChoiceQuestion('How would you like to update Statamic?', $options, 0);

        $selection = $helper->ask($input, new SymfonyStyle($input, $output), $question);

        return strpos($selection, 'statamic/cms --with-dependencies')
            ? 'composer update statamic/cms --with-dependencies'
            : 'composer update';
    }
}
