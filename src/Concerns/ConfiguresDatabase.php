<?php

namespace Statamic\Cli\Concerns;

use function Laravel\Prompts\select;

/**
 * The code in this trait is a slightly modified version of the code in Laravel's Installer CLI.
 * See: https://github.com/laravel/installer/blob/master/src/NewCommand.php
 */
trait ConfiguresDatabase
{
    /**
     * Determine the default database connection.
     */
    protected function promptForDatabaseOptions(): string
    {
        $defaultDatabase = collect(
            $databaseOptions = $this->databaseOptions()
        )->keys()->first();

        if ($this->input->isInteractive()) {
            $database = select(
                label: 'Which database will your application use?',
                options: $databaseOptions,
                default: $defaultDatabase,
            );
        }

        return $database ?? $defaultDatabase;
    }

    /**
     * Get the available database options.
     */
    protected function databaseOptions(): array
    {
        return collect([
            'sqlite' => ['SQLite', extension_loaded('pdo_sqlite')],
            'mysql' => ['MySQL', extension_loaded('pdo_mysql')],
            'mariadb' => ['MariaDB', extension_loaded('pdo_mysql')],
            'pgsql' => ['PostgreSQL', extension_loaded('pdo_pgsql')],
            'sqlsrv' => ['SQL Server', extension_loaded('pdo_sqlsrv')],
        ])
            ->sortBy(fn ($database) => $database[1] ? 0 : 1)
            ->map(fn ($database) => $database[0].($database[1] ? '' : ' (Missing PDO extension)'))
            ->all();
    }

    /**
     * Configure the default database connection.
     */
    protected function configureDefaultDatabaseConnection(string $database, string $name): void
    {
        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $this->absolutePath.'/.env'
        );

        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $this->absolutePath.'/.env.example'
        );

        if ($database === 'sqlite') {
            $environment = file_get_contents($this->absolutePath.'/.env');

            // If database options aren't commented, comment them for SQLite...
            if (! str_contains($environment, '# DB_HOST=127.0.0.1')) {
                $this->commentDatabaseConfigurationForSqlite($this->absolutePath);

                return;
            }

            return;
        }

        // Any commented database configuration options should be uncommented when not on SQLite...
        $this->uncommentDatabaseConfiguration($this->absolutePath);

        $defaultPorts = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        if (isset($defaultPorts[$database])) {
            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $this->absolutePath.'/.env'
            );

            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $this->absolutePath.'/.env.example'
            );
        }

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
            $this->absolutePath.'/.env'
        );

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
            $this->absolutePath.'/.env.example'
        );
    }

    /**
     * Comment the irrelevant database configuration entries for SQLite applications.
     */
    protected function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = [
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=laravel',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env.example'
        );
    }

    /**
     * Uncomment the relevant database configuration entries for non SQLite applications.
     */
    protected function uncommentDatabaseConfiguration(string $directory): void
    {
        $defaults = [
            '# DB_HOST=127.0.0.1',
            '# DB_PORT=3306',
            '# DB_DATABASE=laravel',
            '# DB_USERNAME=root',
            '# DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env.example'
        );
    }
}