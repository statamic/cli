<?php

namespace Statamic\Cli;

class Config
{
    public static function get($key, $default = null)
    {
        return static::all()[$key] ?? $default;
    }

    public static function set($key, $value)
    {
        if (! $path = static::path()) {
            return;
        }

        $config = array_merge(static::all(), [$key => $value]);

        if (! is_dir($dir = dirname($path))) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    public static function all()
    {
        if (! ($path = static::path()) || ! is_file($path)) {
            return [];
        }

        $config = json_decode(@file_get_contents($path), true);

        return is_array($config) ? $config : [];
    }

    public static function path()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $base = getenv('APPDATA') ?: null;
        } elseif ($xdg = getenv('XDG_CONFIG_HOME')) {
            $base = $xdg;
        } elseif ($home = getenv('HOME')) {
            $base = $home.'/.config';
        } else {
            return null;
        }

        return $base ? $base.DIRECTORY_SEPARATOR.'statamic'.DIRECTORY_SEPARATOR.'cli.json' : null;
    }
}
