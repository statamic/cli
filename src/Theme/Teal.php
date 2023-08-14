<?php

namespace Statamic\Cli\Theme;

trait Teal
{
    public function cyan(string $text): string
    {
        return $this->teal($text);
    }

    public function teal(string $text): string
    {
        return "\e[38;2;1;215;176m{$text}\e[39m";
    }
}
