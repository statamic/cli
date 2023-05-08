<?php

namespace Statamic\Cli\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Statamic\Cli\NewCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandIntegrationTest extends TestCase
{
    protected $scaffoldName;
    protected $scaffoldDirectory;

    public function setUp(): void
    {
        parent::setUp();

        $this->scaffoldName = 'tests-output/my-app';
        $this->scaffoldDirectory = __DIR__.'/../'.$this->scaffoldName;

        $this->clearScaffoldDirectory();
    }

    public function tearDown(): void
    {
        $this->clearScaffoldDirectory();

        parent::tearDown();
    }

    /** @test */
    public function it_can_scaffold_a_new_statamic_app()
    {
        $this->assertAppNotExists();

        $statusCode = $this->scaffoldNewApp();

        $this->assertSame(0, $statusCode);
        $this->assertBasicAppScaffolded();
    }

    /** @test */
    public function it_can_scaffold_a_new_statamic_app_with_a_starter_kit()
    {
        $this->assertAppNotExists();

        $statusCode = $this->scaffoldNewApp(['starter-kit' => 'statamic/starter-kit-cool-writings']);

        $this->assertSame(0, $statusCode);
        $this->assertBasicAppScaffolded();
        $this->assertFileExists($this->appPath('content/collections/articles/1994-07-05.magic.md'));
        $this->assertFileExists($this->appPath('resources/blueprints/collections/articles/article.yaml'));
        $this->assertFileNotExists($this->appPath('starter-kit.yaml'));
    }

    /** @test */
    public function it_can_scaffold_with_starter_kit_config()
    {
        $this->assertAppNotExists();

        $statusCode = $this->scaffoldNewApp(['starter-kit' => 'statamic/starter-kit-cool-writings', '--with-config' => true]);

        $this->assertSame(0, $statusCode);
        $this->assertBasicAppScaffolded();
        $this->assertFileExists($this->appPath('content/collections/articles/1994-07-05.magic.md'));
        $this->assertFileExists($this->appPath('resources/blueprints/collections/articles/article.yaml'));
        $this->assertFileExists($this->appPath('starter-kit.yaml'));
    }

    /** @test */
    public function it_fails_if_application_folder_already_exists()
    {
        mkdir($this->appPath());

        $this->assertRuntimeException(function () {
            $this->scaffoldNewApp();
        });

        $this->assertFileExists($this->appPath());
        $this->assertFileNotExists($this->appPath('vendor'));
        $this->assertFileNotExists($this->appPath('.env'));
        $this->assertFileNotExists($this->appPath('artisan'));
        $this->assertFileNotExists($this->appPath('please'));
    }

    /** @test */
    public function it_overwrites_application_when_using_force_option()
    {
        mkdir($this->appPath());
        file_put_contents($this->appPath('test.md'), 'test content');

        $this->assertFileExists($this->appPath('test.md'));

        $this->scaffoldNewApp(['--force' => true]);

        $this->assertBasicAppScaffolded();
        $this->assertFileNotExists($this->appPath('test.md'));
    }

    /** @test */
    public function it_fails_if_using_force_option_to_cwd()
    {
        $this->assertRuntimeException(function () {
            $this->scaffoldNewApp(['name' => '.', '--force' => true]);
        });

        $this->assertAppNotExists();
    }

    /** @test */
    public function it_fails_if_invalid_starter_kit_repo_is_passed()
    {
        $this->assertRuntimeException(function () {
            $this->scaffoldNewApp(['starter-kit' => 'not-a-valid-repo']);
        });

        $this->assertAppNotExists();
    }

    /** @test */
    public function it_fails_when_there_is_starter_kit_error_but_leaves_base_installation()
    {
        $this->assertRuntimeException(function () {
            $this->scaffoldNewApp(['starter-kit' => 'statamic/not-an-actual-starter-kit']);
        });

        $this->assertBasicAppScaffolded();
    }

    protected function assertRuntimeException($callback)
    {
        $error = false;

        try {
            $callback();
        } catch (RuntimeException $exception) {
            $error = true;
        }

        $this->assertTrue($error);
    }

    protected function clearScaffoldDirectory()
    {
        if (file_exists($this->scaffoldDirectory)) {
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$this->scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$this->scaffoldDirectory\"");
            }
        }
    }

    protected function appPath($path = null)
    {
        if ($path) {
            return $this->scaffoldDirectory.'/'.$path;
        }

        return $this->scaffoldDirectory;
    }

    protected function scaffoldNewApp($args = [])
    {
        $app = new Application('Statamic Installer');

        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $args = array_merge(['name' => $this->scaffoldName], $args);

        $statusCode = $tester->execute($args);

        return $statusCode;
    }

    protected function assertBasicAppScaffolded()
    {
        $this->assertFileExists($this->appPath('vendor'));
        $this->assertFileExists($this->appPath('.env'));
        $this->assertFileExists($this->appPath('artisan'));
        $this->assertFileExists($this->appPath('please'));

        $envFile = file_get_contents($this->appPath('.env'));
        $this->assertStringContainsString('APP_URL=http://my-app.test', $envFile);
        $this->assertStringContainsString('DB_DATABASE=my_app', $envFile);
    }

    protected function assertAppNotExists()
    {
        $this->assertFileNotExists($this->appPath());
    }
}
