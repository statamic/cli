<?php

namespace Statamic\Cli;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class NewCommand extends Command
{
    use Concerns\RunsCommands;

    const BASE_REPO = 'statamic/statamic';
    const OUTPOST_ENDPOINT = 'https://outpost.statamic.com/v3/starter-kits/';
    const GITHUB_LATEST_RELEASE_ENDPOINT = 'https://api.github.com/repos/statamic/cli/releases/latest';

    public $input;
    public $output;
    public $relativePath;
    public $absolutePath;
    public $name;
    public $version;
    public $starterKit;
    public $starterKitLicense;
    public $local;
    public $withConfig;
    public $withoutDependencies;
    public $force;
    public $baseInstallSuccessful;
    public $shouldUpdateCliToVersion = false;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Statamic application')
            ->addArgument('name', InputArgument::REQUIRED, 'Statamic application directory name')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addArgument('starter-kit', InputArgument::OPTIONAL, 'Optionally install specific starter kit')
            ->addOption('license', null, InputOption::VALUE_OPTIONAL, 'Optionally provide explicit starter kit license')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Optionally install from local repo configured in composer config.json')
            ->addOption('with-config', null, InputOption::VALUE_NONE, 'Optionally copy starter-kit.yaml config for local development')
            ->addOption('without-dependencies', null, InputOption::VALUE_NONE, 'Optionally install starter kit without dependencies')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this
            ->checkCliVersion()
            ->notifyIfOldCliVersion()
            ->processArguments()
            ->validateArguments()
            ->showStatamicTitleArt()
            ->askForRepo()
            ->validateStarterKitLicense()
            ->installBaseProject()
            ->installStarterKit()
            ->makeSuperUser()
            ->notifyIfOldCliVersion()
            ->showSuccessMessage()
            ->showPostInstallInstructions()
            ->askToSpreadJoy();

        return 0;
    }

    /**
     * Check cli version.
     *
     * @return $this
     */
    protected function checkCliVersion()
    {
        $request = new Client;

        if (! $currentVersion = Version::get()) {
            return $this;
        }

        try {
            $response = $request->get(self::GITHUB_LATEST_RELEASE_ENDPOINT);
            $latestVersion = json_decode($response->getBody(), true)['tag_name'];
        } catch (\Throwable $exception) {
            return $this;
        }

        if (version_compare($currentVersion, $latestVersion, '<')) {
            $this->shouldUpdateCliToVersion = $latestVersion;
        }

        return $this;
    }

    /**
     * Notify user if a statamic/cli upgrade exists.
     *
     * @return $this
     */
    protected function notifyIfOldCliVersion()
    {
        if (! $this->shouldUpdateCliToVersion) {
            return $this;
        }

        $this->output->write(PHP_EOL);
        $this->output->write("<comment>This is an old version of the Statamic CLI Tool, please upgrade to {$this->shouldUpdateCliToVersion}!</comment>".PHP_EOL);
        $this->output->write('<comment>If you have a global composer installation, you may upgrade by running the following command:</comment>'.PHP_EOL);
        $this->output->write('<comment>composer global update statamic/cli</comment>'.PHP_EOL);

        return $this;
    }

    /**
     * Process arguments and options.
     *
     * @return $this
     */
    protected function processArguments()
    {
        $this->relativePath = $this->input->getArgument('name');

        $this->absolutePath = $this->relativePath && $this->relativePath !== '.'
            ? getcwd().'/'.$this->relativePath
            : getcwd();

        $this->name = pathinfo($this->absolutePath)['basename'];

        $this->version = $this->input->getOption('dev')
            ? 'dev-master'
            : '';

        $this->starterKit = $this->input->getArgument('starter-kit');
        $this->starterKitLicense = $this->input->getOption('license');
        $this->local = $this->input->getOption('local');
        $this->withConfig = $this->input->getOption('with-config');
        $this->withoutDependencies = $this->input->getOption('without-dependencies');

        $this->force = $this->input->getOption('force');

        return $this;
    }

    /**
     * Validate arguments and options.
     *
     * @return $this
     *
     * @throws RuntimeException
     */
    protected function validateArguments()
    {
        if (! $this->force && $this->applicationExists()) {
            throw new RuntimeException('Application already exists!');
        }

        if ($this->force && $this->pathIsCwd()) {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        if ($this->starterKit && $this->isInvalidStarterKit()) {
            throw new RuntimeException('Please enter a valid composer package name (eg. hasselhoff/kung-fury)!');
        }

        if (! $this->starterKit && $this->starterKitLicense) {
            throw new RuntimeException('Starter kit is required when using `--license` option!');
        }

        if (! $this->starterKit && $this->local) {
            throw new RuntimeException('Starter kit is required when using `--local` option!');
        }

        if (! $this->starterKit && $this->withConfig) {
            throw new RuntimeException('Starter kit is required when using `--with-config` option!');
        }

        if (! $this->starterKit && $this->withoutDependencies) {
            throw new RuntimeException('Starter kit is required when using `--without-dependencies` option!');
        }

        return $this;
    }

    /**
     * Show Statamic title art.
     *
     * @return $this
     */
    protected function showStatamicTitleArt()
    {
        $this->output->write(PHP_EOL.'<fg=red>   _____ __        __                  _
  / ___// /_____ _/ /_____ _____ ___  (_)____
  \__ \/ __/ __ `/ __/ __ `/ __ `__ \/ / ___/
 ___/ / /_/ /_/ / /_/ /_/ / / / / / / / /__
/____/\__/\__,_/\__/\__,_/_/ /_/ /_/_/\___/</>'.PHP_EOL.PHP_EOL);

        return $this;
    }

    /**
     * Ask which starter kit repo to install.
     *
     * @return $this
     */
    protected function askForRepo()
    {
        if ($this->starterKit || ! $this->input->isInteractive()) {
            return $this;
        }

        $helper = $this->getHelper('question');

        $options = [
            'Blank Site',
            'Starter Kit',
        ];

        $question = new ChoiceQuestion('Would you like to start with a blank site or starter kit? [<comment>Blank Site</comment>]', $options, 0);

        $choice = $helper->ask($this->input, new SymfonyStyle($this->input, $this->output), $question);

        $this->output->write(PHP_EOL);

        if ($choice === 'Blank Site') {
            return $this;
        }

        $this->output->write('You can find starter kits at <info>https://statamic.com/starter-kits</info> 🏄'.PHP_EOL.PHP_EOL);

        $question = new Question('Enter the package name of the Starter Kit: ');

        $this->starterKit = $helper->ask($this->input, new SymfonyStyle($this->input, $this->output), $question);

        if ($this->isInvalidStarterKit()) {
            throw new RuntimeException('Please enter a valid composer package name (eg. hasselhoff/kung-fury)!');
        }

        return $this;
    }

    /**
     * Validate starter kit license.
     *
     * @return $this
     */
    protected function validateStarterKitLicense()
    {
        if (! $this->starterKit) {
            return $this;
        }

        $request = new Client;

        try {
            $response = $request->get(self::OUTPOST_ENDPOINT."{$this->starterKit}");
        } catch (\Exception $exception) {
            $this->throwConnectionException();
        }

        $details = json_decode($response->getBody(), true);

        // If $details === `false`, then no product was returned and we'll consider it a free starter kit.
        if ($details['data'] === false) {
            return $this->confirmUnlistedKit();
        }

        // If the returned product doesn't have a price, then we'll consider it a free starter kit.
        if (! $details['data']['price']) {
            return $this;
        }

        $sellerSlug = $details['data']['seller']['slug'];
        $kitSlug = $details['data']['slug'];
        $marketplaceUrl = "https://statamic.com/starter-kits/{$sellerSlug}/{$kitSlug}";

        if ($this->input->isInteractive()) {
            $this->output->write(PHP_EOL);
            $this->output->write('<comment>This is a paid starter kit. If you haven\'t already, you may purchase a license at:</comment>'.PHP_EOL);
            $this->output->write("<comment>{$marketplaceUrl}</comment>".PHP_EOL);
        }

        $license = $this->getStarterKitLicense();

        try {
            $response = $request->post(self::OUTPOST_ENDPOINT.'validate', ['json' => [
                'license' => $license,
                'package' => $this->starterKit,
            ]]);
        } catch (\Exception $exception) {
            $this->throwConnectionException();
        }

        $validation = json_decode($response->getBody(), true);

        if (! $validation['data']['valid']) {
            throw new RuntimeException("Invalid license for [{$this->starterKit}]!");
        }

        $this->output->write('<info>Starter kit license valid!</info>'.PHP_EOL);

        $this->starterKitLicense = $license;

        return $this->confirmSingleSiteLicense();
    }

    /**
     * Confirm unlisted kit.
     *
     * @return $this
     */
    protected function confirmUnlistedKit()
    {
        $questionText = 'Starter kit not found on Statamic Marketplace! Install unlisted starter kit? (yes/no) [<comment>yes</comment>]: ';
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, true);

        $this->output->write(PHP_EOL);

        if (! $helper->ask($this->input, $this->output, $question)) {
            return $this->exitInstallation();
        }

        return $this;
    }

    /**
     * Confirm single-site license.
     *
     * @return $this
     */
    protected function confirmSingleSiteLicense()
    {
        $appendedContinueText = $this->input->isInteractive() ? ' Would you like to continue installation?' : PHP_EOL;

        $this->output->write(PHP_EOL);
        $this->output->write('<comment>Once successfully installed, this single-site license will be marked as used</comment>'.PHP_EOL);
        $this->output->write("<comment>and cannot be installed on future Statamic sites!{$appendedContinueText}</comment>");

        if (! $this->input->isInteractive()) {
            return $this;
        }

        $helper = $this->getHelper('question');

        $questionText = 'I am aware this is a single-site license (yes/no) [<comment>no</comment>]: ';

        $question = new ConfirmationQuestion($questionText, false);

        $this->output->write(PHP_EOL);

        if (! $helper->ask($this->input, $this->output, $question)) {
            return $this->exitInstallation();
        }

        return $this;
    }

    /**
     * Install base project.
     *
     * @return $this
     *
     * @throws RuntimeException
     */
    protected function installBaseProject()
    {
        $commands = [];

        if ($this->force && ! $this->pathIsCwd()) {
            if (PHP_OS_FAMILY == 'Windows') {
                $commands[] = "rd /s /q \"$this->absolutePath\"";
            } else {
                $commands[] = "rm -rf \"$this->absolutePath\"";
            }
        }

        $commands[] = $this->createProjectCommand();

        if (PHP_OS_FAMILY != 'Windows') {
            $commands[] = "chmod 755 \"$this->absolutePath/artisan\"";
            $commands[] = "chmod 755 \"$this->absolutePath/please\"";
        }

        $this->output->write(PHP_EOL);

        $this->runCommands($commands);

        if (! $this->wasBaseInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Statamic!');
        }

        $this->updateEnvVars();

        $this->baseInstallSuccessful = true;

        return $this;
    }

    /**
     * Install starter kit.
     *
     * @return $this
     *
     * @throws RuntimeException
     */
    protected function installStarterKit()
    {
        if (! $this->baseInstallSuccessful || ! $this->starterKit) {
            return $this;
        }

        $options = [
            '--cli-install',
            '--clear-site',
        ];

        if (! $this->input->isInteractive()) {
            $options[] = '--no-interaction';
        }

        if ($this->local) {
            $options[] = '--local';
        }

        if ($this->withConfig) {
            $options[] = '--with-config';
        }

        if ($this->starterKitLicense) {
            $options[] = '--license';
            $options[] = $this->starterKitLicense;
        }

        if ($this->withoutDependencies) {
            $options[] = '--without-dependencies';
        }

        $statusCode = (new Please($this->output))
            ->cwd($this->absolutePath)
            ->run('starter-kit:install', $this->starterKit, ...$options);

        if ($statusCode !== 0) {
            throw new RuntimeException('There was a problem installing Statamic with the chosen starter kit!');
        }

        return $this;
    }

    /**
     * Make super user.
     *
     * @return $this
     */
    protected function makeSuperUser()
    {
        if (! $this->input->isInteractive()) {
            return $this;
        }

        $questionText = 'Create a super user? (yes/no) [<comment>no</comment>]: ';
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        $this->output->write(PHP_EOL);

        if (! $helper->ask($this->input, $this->output, $question)) {
            return $this;
        }

        // Since Windows cannot TTY, we'll capture their input here and make a user.
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->makeSuperUserInWindows();
        }

        // Otherwise, delegate to the `make:user` command with interactivity and let core handle the finer details.
        (new Please($this->output))
            ->cwd($this->absolutePath)
            ->run('make:user', '--super');

        return $this;
    }

    /**
     * Make super user in Windows.
     *
     * @return $this
     */
    protected function makeSuperUserInWindows()
    {
        $please = (new Please($this->output))->cwd($this->absolutePath);

        // Ask for email
        while (! isset($email) || ! $this->validateEmail($email)) {
            $email = $this->askForBasicInput('Email');
        }

        // Ask for name
        $name = $this->askForBasicInput('Name');

        // Ask for password
        while (! isset($password) || ! $this->validatePassword($password)) {
            $password = $this->askForBasicInput('Password (Your input will be hidden)', true);
        }

        // Create super user and update with captured input.
        $please->run('make:user', '--super', $email);

        $updateUser = '\Statamic\Facades\User::findByEmail('.escapeshellarg($email).')'
            .'->password('.escapeshellarg($password).')'
            .'->makeSuper()';

        if ($name) {
            $updateUser .= '->set("name", '.escapeshellarg($name).')';
        }

        $updateUser .= '->save();';

        $please->run('tinker', '--execute', $updateUser);

        return $this;
    }

    /**
     * Ask for basic input.
     *
     * @param  string  $label
     * @param  bool  $hiddenInput
     * @return mixed
     */
    protected function askForBasicInput($label, $hiddenInput = false)
    {
        return $this->getHelper('question')->ask(
            $this->input,
            new SymfonyStyle($this->input, $this->output),
            (new Question("{$label}: "))->setHidden($hiddenInput)
        );
    }

    /**
     * Validate email address.
     *
     * @param  string  $email
     * @return bool
     */
    protected function validateEmail($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $this->output->write('<error>Invalid email address.</error>'.PHP_EOL);

        return false;
    }

    /**
     * Validate password.
     *
     * @param  string  $password
     * @return bool
     */
    protected function validatePassword($password)
    {
        if (strlen($password) >= 8) {
            return true;
        }

        $this->output->write('<error>The input must be at least 8 characters.</error>'.PHP_EOL);

        return false;
    }

    /**
     * Show success message.
     *
     * @return $this
     */
    protected function showSuccessMessage()
    {
        $this->output->writeln(PHP_EOL."<info>[✔] Statamic has been successfully installed into the <comment>{$this->relativePath}</comment> directory.</info>");

        $this->output->writeln('Build something rad!');

        return $this;
    }

    /**
     * Show cached post-install instructions, if provided.
     *
     * @return $this
     */
    protected function showPostInstallInstructions()
    {
        if (! file_exists($instructionsPath = $this->absolutePath.'/storage/statamic/tmp/cli/post-install-instructions.txt')) {
            return $this;
        }

        $this->output->write(PHP_EOL);

        foreach (file($instructionsPath) as $line) {
            $this->output->write('<comment>'.trim($line).'</comment>'.PHP_EOL);
        }

        return $this;
    }

    /**
     * Ask if user wants to star our GitHub repo.
     *
     * @return $this
     */
    protected function askToSpreadJoy()
    {
        if (! $this->input->isInteractive()) {
            return $this;
        }

        $questionText = 'Would you like to spread the joy of Statamic by starring the repo? (yes/no) [<comment>no</comment>]: ';
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        $this->output->write(PHP_EOL);

        if (! $helper->ask($this->input, $this->output, $question)) {
            return $this;
        }

        if (PHP_OS_FAMILY == 'Darwin') {
            exec('open https://github.com/statamic/cms');
        } elseif (PHP_OS_FAMILY == 'Windows') {
            exec('start https://github.com/statamic/cms');
        } elseif (PHP_OS_FAMILY == 'Linux') {
            exec('xdg-open https://github.com/statamic/cms');
        }

        return $this;
    }

    /**
     * Check if the application path already exists.
     *
     * @return bool
     */
    protected function applicationExists()
    {
        if ($this->pathIsCwd()) {
            return is_file("{$this->absolutePath}/composer.json");
        }

        return is_dir($this->absolutePath) || is_file($this->absolutePath);
    }

    /**
     * Check if the application path is the current working directory.
     *
     * @return bool
     */
    protected function pathIsCwd()
    {
        return $this->absolutePath === getcwd();
    }

    /**
     * Check if the starter kit is invalid.
     *
     * @return bool
     */
    protected function isInvalidStarterKit()
    {
        return ! preg_match("/^[^\/\s]+\/[^\/\s]+$/", $this->starterKit);
    }

    /**
     * Determine if base install was successful.
     *
     * @return bool
     */
    protected function wasBaseInstallSuccessful()
    {
        return is_file("{$this->absolutePath}/composer.json")
            && is_dir("{$this->absolutePath}/vendor")
            && is_file("{$this->absolutePath}/artisan")
            && is_file("{$this->absolutePath}/please");
    }

    /**
     * Create the composer create-project command.
     *
     * @return string
     */
    protected function createProjectCommand()
    {
        $composer = $this->findComposer();

        $baseRepo = self::BASE_REPO;

        $directory = $this->pathIsCwd() ? '.' : $this->relativePath;

        return $composer." create-project {$baseRepo} \"{$directory}\" {$this->version} --remove-vcs --prefer-dist";
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }

    /**
     * Update application env vars.
     */
    protected function updateEnvVars()
    {
        $this->replaceInFile(
            'APP_URL=http://localhost',
            'APP_URL=http://'.$this->name.'.test',
            $this->absolutePath.'/.env'
        );

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($this->name)),
            $this->absolutePath.'/.env'
        );
    }

    /**
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $file
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Throw guzzle connection exception.
     *
     * @throws RuntimeException
     */
    protected function throwConnectionException()
    {
        throw new RuntimeException('Cannot connect to [statamic.com] to validate license!');
    }

    /**
     * Get starter kit license from parsed options, or ask user for license.
     *
     * @return string
     */
    protected function getStarterKitLicense()
    {
        if ($this->starterKitLicense) {
            return $this->starterKitLicense;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('A starter kit license is required, please pass using the `--license` option!');
        }

        $helper = $this->getHelper('question');

        $question = new Question('Please enter your license key: ');

        while (! isset($license)) {
            $license = $helper->ask($this->input, new SymfonyStyle($this->input, $this->output), $question);
        }

        return $license;
    }

    /**
     * Exit installation.
     *
     * @return \stdClass
     */
    protected function exitInstallation()
    {
        return new class
        {
            public function __call($method, $args)
            {
                return $this;
            }
        };
    }
}
