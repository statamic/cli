<?php

namespace Statamic\Cli\Theme;

trait HotPink
{
    public function cyan(string $text): string
    {
        return $this->pink($text);
    }

    public function pink(string $text): string
    {
        return "\e[38;2;255;51;167m{$text}\e[39m";
    }
}
