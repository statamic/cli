<?php

namespace Statamic\Cli\Concerns;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ConfiguresPrompts
{
    /**
     * Configure the prompt fallbacks.
     *
     * @return void
     */
    protected function configurePrompts(InputInterface $input, OutputInterface $output)
    {
        Prompt::fallbackWhen(! $input->isInteractive() || PHP_OS_FAMILY === 'Windows');

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->ask($prompt->label, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate,
            $output
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->confirm($prompt->label, $prompt->default),
            $prompt->required,
            $prompt->validate,
            $output
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate,
            $output
        ));

        SuggestPrompt::fallbackUsing(fn (SuggestPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt, $input, $output) {
                $question = new Question($prompt->label, $prompt->default);

                is_callable($prompt->options)
                    ? $question->setAutocompleterCallback($prompt->options)
                    : $question->setAutocompleterValues($prompt->options);

                return (new SymfonyStyle($input, $output))->askQuestion($question);
            },
            $prompt->required,
            $prompt->validate,
            $output
        ));
    }

    /**
     * Prompt the user until the given validation callback passes.
     *
     * @param  \Closure  $prompt
     * @param  bool|string  $required
     * @param  \Closure|null  $validate
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function promptUntilValid($prompt, $required, $validate, $output)
    {
        while (true) {
            $result = $prompt();

            if ($required && ($result === '' || $result === [] || $result === false)) {
                $output->writeln('<error>'.(is_string($required) ? $required : 'Required.').'</error>');

                continue;
            }

            if ($validate) {
                $error = $validate($result);

                if (is_string($error) && strlen($error) > 0) {
                    $output->writeln("<error>{$error}</error>");

                    continue;
                }
            }

            return $result;
        }
    }
}
