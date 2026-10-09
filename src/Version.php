<?php

namespace Statamic\Cli;

use Composer\InstalledVersions;

class Version
{
    public static function get()
    {
        try {
            return InstalledVersions::getPrettyVersion('statamic/cli');
        } catch (\OutOfBoundsException $e) {
            return null;
        }
    }

    public static function isDev($version)
    {
        return str_starts_with($version, 'dev-') || str_ends_with($version, '-dev');
    }
}
