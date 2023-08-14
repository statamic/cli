<?php

namespace Statamic\Cli\Theme;

use Symfony\Component\Console\Terminal;

trait Teal
{
    public function cyan(string $text): string
    {
        return $this->teal($text);
    }

    public function teal(string $text): string
    {
        $color = Terminal::getColorMode()->convertFromHexToAnsiColorCode('01D7B0');

        return "\e[3{$color}m{$text}\e[39m";
    }
}
