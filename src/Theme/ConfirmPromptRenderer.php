<?php

namespace Statamic\Cli\Theme;

use Laravel\Prompts\Themes\Default\ConfirmPromptRenderer as Renderer;

class ConfirmPromptRenderer extends Renderer
{
    use Teal;

    public function green(string $text): string
    {
        return $this->teal($text);
    }
}
