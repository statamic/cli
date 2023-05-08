<?php

namespace Statamic\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command
{
    use Concerns\RunsCommands;

    protected $input;
    protected $output;

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
        $this->input = $input;
        $this->output = $output;

        $please = new Please($output);

        if ($please->isV2()) {
            $output->writeln(PHP_EOL.'<error>Statamic v2 is no longer supported!</error>'.PHP_EOL);

            return 1;
        }

        $output->writeln(PHP_EOL.'<comment>NOTE: If you have previously updated using the CP, you may need to update the version in your composer.json before running this update!</comment>'.PHP_EOL);

        $command = $this->updateCommand();

        $this->runCommand($command);

        return 0;
    }

    /**
     * Determine the update command.
     *
     * @return string
     */
    protected function updateCommand()
    {
        $helper = $this->getHelper('question');

        $options = [
            'Update Statamic and its dependencies [composer update statamic/cms --with-dependencies]',
            'Update all project dependencies [composer update]',
        ];

        $question = new ChoiceQuestion('How would you like to update Statamic?', $options, 0);

        $selection = $helper->ask($this->input, new SymfonyStyle($this->input, $this->output), $question);

        return strpos($selection, 'statamic/cms --with-dependencies')
            ? 'composer update statamic/cms --with-dependencies'
            : 'composer update';
    }
}
