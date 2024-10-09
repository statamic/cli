<?php

namespace Statamic\Cli;

use GuzzleHttp\Client;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use RuntimeException;
use Statamic\Cli\Theme\ConfirmPromptRenderer;
use Statamic\Cli\Theme\SelectPromptRenderer;
use Statamic\Cli\Theme\SuggestPromptRenderer;
use Statamic\Cli\Theme\TextPromptRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts, Concerns\RunsCommands;

    const BASE_REPO = 'statamic/statamic';
    const OUTPOST_ENDPOINT = 'https://outpost.statamic.com/v3/starter-kits/';
    const GITHUB_LATEST_RELEASE_ENDPOINT = 'https://api.github.com/repos/statamic/cli/releases/latest';
    const STATAMIC_API_URL = 'https://statamic.com/api/v1/';

    /** @var InputInterface */
    public $input;

    /** @var OutputInterface */
    public $output;

    public $relativePath;
    public $absolutePath;
    public $name;
    public $version;
    public $starterKit;
    public $starterKits;
    public $starterKitLicense;
    public $local;
    public $withConfig;
    public $withoutDependencies;
    public $addons;
    public $force;
    public $baseInstallSuccessful;
    public $shouldUpdateCliToVersion = false;
    public $makeUser = false;
    public $initializeGitRepository = false;
    public $shouldPushToGithub = false;
    public $spreadJoy = false;
    public $githubRepository;
    public $repositoryVisibility;

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
            ->addOption('addon', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Install first-party addons?', [])
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository')
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Optionally specify the name of the GitHub repository')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->configurePrompts($input, $output);

        $this
            ->setupTheme()
            ->checkCliVersion()
            ->notifyIfOldCliVersion()
            ->showStatamicTitleArt();
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (! $this->input->getArgument('name')) {
            $this->input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }
    }

    /**
     * Execute the command.
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this
            ->processArguments()
            ->validateArguments()
            ->askForRepo()
            ->validateStarterKitLicense()
            ->askToInstallAddons()
            ->askToMakeSuperUser()
            ->askToInitializeGitRepository()
            ->askToPushToGithub()
            ->askToSpreadJoy()
            ->readySetGo()
            ->installBaseProject()
            ->installStarterKit()
            ->makeSuperUser()
            ->installAddons()
            ->initializeGitRepository()
            ->pushToGithub()
            ->notifyIfOldCliVersion()
            ->showSuccessMessage()
            ->showPostInstallInstructions();

        return 0;
    }

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

    protected function setupTheme()
    {
        Prompt::addTheme('statamic', [
            SelectPrompt::class => SelectPromptRenderer::class,
            SuggestPrompt::class => SuggestPromptRenderer::class,
            ConfirmPrompt::class => ConfirmPromptRenderer::class,
            TextPrompt::class => TextPromptRenderer::class,
        ]);

        Prompt::theme('statamic');

        return $this;
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
        $this->addons = $this->input->getOption('addon');
        $this->force = $this->input->getOption('force');
        $this->initializeGitRepository = $this->input->getOption('git') || $this->input->getOption('github');
        $this->shouldPushToGithub = $this->input->getOption('github');
        $this->githubRepository = $this->input->getOption('repo');
        $this->repositoryVisibility = $this->input->getOption('github');

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
        $this->output->write(PHP_EOL."<fg=#FF269E>              $$\                $$\                             $$\
              $$ |               $$ |                            \__|
  $$$$$$$\ $$$$$$\    $$$$$$\ $$$$$$\    $$$$$$\   $$$$$$\$$$$\  $$\  $$$$$$$\
  $$  _____|\_$$  _|   \____$$\\_$$  _|   \____$$\  $$  _$$  _$$\ $$ |$$  _____|
  \$$$$$$\     $$ |     $$$$$$$ | $$ |     $$$$$$$ |$$ / $$ / $$ |$$ |$$ /
   \____$$\   $$ |$$\ $$  __$$ | $$ |$$\ $$  __$$ |$$ | $$ | $$ |$$ |$$ |
  $$$$$$$  |  \\$$$$  |\\$$$$$$$ | \\$$$$  |\\$$$$$$$ |$$ | $$ | $$ |$$ |\\$$$$$$$\
  \_______/    \____/  \_______|  \____/  \_______|\__| \__| \__|\__| \_______|
        </>".PHP_EOL);

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

        $choice = select(
            'Would you like to install a starter kit?',
            options: [
                $blankSiteOption = 'No, start with a blank site.',
                'Yes, let me pick a Starter Kit.',
            ],
        );

        if ($choice === $blankSiteOption) {
            return $this;
        }

        $this->output->write('  You can find starter kits at <info>https://statamic.com/starter-kits</info> 🏄'.PHP_EOL.PHP_EOL);

        $this->starterKit = $this->normalizeStarterKitSelection(suggest(
            'Which starter kit would you like to install?',
            fn ($value) => $this->searchStarterKits($value)
        ));

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
            $this->output->write('  <comment>This is a paid starter kit. If you haven\'t already, you may purchase a license at:</comment>'.PHP_EOL);
            $this->output->write("  <comment>{$marketplaceUrl}</comment>".PHP_EOL);
            $this->output->write(PHP_EOL);
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
        if (! confirm('Starter kit not found on Statamic Marketplace! Install unlisted starter kit?')) {
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

        $this->output->write(PHP_EOL);
        $this->output->write('<comment>Once successfully installed, this Starter Kit license will be marked as used</comment>'.PHP_EOL);
        $this->output->write('<comment>and cannot be applied to future installations!</comment>');

        if (! $this->input->isInteractive()) {
            return $this;
        }

        $this->output->write(PHP_EOL.PHP_EOL);

        if (! confirm('Would you like to continue the installation?', false, 'I understand. Install now and mark used.', "No, I'll install it later.")) {
            return $this->exitInstallation();
        }

        return $this;
    }

    /**
     * Final confirmation
     *
     * @return $this
     */
    protected function readySetGo()
    {
        if (! $this->input->isInteractive()) {
            return $this;
        }

        if (! confirm('Ready?', yes: "Yes, let's do this!", no: 'No, shut it down.')) {
            return $this->exitInstallation();
        }

        if ($this->spreadJoy) {
            if (PHP_OS_FAMILY == 'Darwin') {
                exec('open https://github.com/statamic/cms');
            } elseif (PHP_OS_FAMILY == 'Windows') {
                exec('start https://github.com/statamic/cms');
            } elseif (PHP_OS_FAMILY == 'Linux') {
                exec('xdg-open https://github.com/statamic/cms');
            }
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

    protected function askToInstallAddons()
    {
        if ($this->addons || ! $this->input->isInteractive()) {
            return $this;
        }

        $choice = select(
            label: 'Would you like to install any first-party addons?',
            options: [
                $withoutAddonsOption = "No, I'm good for now.",
                "Yes, let me pick.",
            ],
        );

        if ($choice === $withoutAddonsOption) {
            return $this;
        }

        $this->addons = multiselect(
            label: 'Which first-party addons do you want to install?',
            options: [
                'collaboration' => 'Collaboration',
                'eloquent-driver' => 'Eloquent Driver',
                'ssg' => 'Static Site Generator',
            ],
            hint: 'Use the space bar to select options.'
        );

        if (count($this->addons) > 0) {
            $this->output->write("  Great. We'll get these installed right after we setup your Statamic site.".PHP_EOL.PHP_EOL);
        }

        return $this;
    }

    protected function installAddons()
    {
        if (! $this->addons) {
            return $this;
        }

        collect($this->addons)->each(function (string $addon) {
            $statusCode = (new Please($this->output))
                ->cwd($this->absolutePath)
                ->run("install:{$addon}");

            if ($statusCode !== 0) {
                throw new RuntimeException("There was a problem installing the [{$addon}] addon!");
            }
        });

        return $this;
    }

    protected function askToMakeSuperUser()
    {
        if (! $this->input->isInteractive()) {
            return $this;
        }

        $this->makeUser = confirm('Create a super user?', false);

        $this->output->write($this->makeUser
            ? "  Great. You'll be prompted for details after installation."
            : '  No problem. You can create one later with <comment>php please make:user</comment>.'
        );

        $this->output->write(PHP_EOL.PHP_EOL);

        return $this;
    }

    /**
     * Make super user.
     *
     * @return $this
     */
    protected function makeSuperUser()
    {
        if (! $this->makeUser) {
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
     * Ask to initialize a Git repository.
     *
     * @return $this
     */
    protected function askToInitializeGitRepository()
    {
        if (
            $this->initializeGitRepository
            || ! $this->isGitInstalled()
            || ! $this->input->isInteractive()
        ) {
            return $this;
        }

        $this->initializeGitRepository = confirm(
            label: 'Would you like to initialize a Git repository?',
            default: false
        );

        return $this;
    }

    /**
     * Initialize a Git repository.
     *
     * @return $this
     */
    protected function initializeGitRepository()
    {
        if (! $this->initializeGitRepository || ! $this->isGitInstalled()) {
            return $this;
        }

        $branch = $this->input->getOption('branch') ?: $this->defaultBranch();

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Set up a fresh Statamic site"',
            "git branch -M {$branch}",
        ];

        $this->runCommands($commands, $this->absolutePath);

        return $this;
    }

    /**
     * Check if Git is installed.
     *
     * @return bool
     */
    protected function isGitInstalled(): bool
    {
        $process = new Process(['git', '--version']);

        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Return the local machine's default Git branch if set or default to `main`.
     *
     * @return string
     */
    protected function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);
        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    /**
     * Ask if the user wants to push the repository to GitHub.
     *
     * @return $this
     */
    protected function askToPushToGithub()
    {
        if (
            ! $this->initializeGitRepository
            || ! $this->isGitInstalled()
            || ! $this->isGhInstalled()
            || ! $this->input->isInteractive()
        ) {
            return $this;
        }

        if (! $this->shouldPushToGithub) {
            $this->shouldPushToGithub = confirm(
                label: 'Would you like to create a new repository on GitHub?',
                default: false
            );
        }

        if ($this->shouldPushToGithub && ! $this->githubRepository) {
            $this->githubRepository = text(
                label: 'What should be your full repository name?',
                default: $this->name,
                required: true,
            );
        }

        if ($this->shouldPushToGithub && ! $this->repositoryVisibility) {
            $this->repositoryVisibility = select(
                label: 'Should the repository be public or private?',
                options: [
                    'public' => 'Public',
                    'private' => 'Private',
                ],
                default: 'private',
            );
        }

        return $this;
    }

    /**
     * Create a GitHub repository and push the git log to it.
     *
     * @return $this
     */
    protected function pushToGithub()
    {
        if (! $this->shouldPushToGithub) {
            return $this;
        }

        $name = $this->githubRepository ?? $this->name;
        $visibility = $this->repositoryVisibility ?? 'private';

        $commands = [
            "gh repo create {$name} --source=. --push --{$visibility}",
        ];

        $this->runCommands($commands, $this->absolutePath, disableOutput: true);

        return $this;
    }

    /**
     * Check if GitHub's GH CLI tool is installed.
     *
     * @return bool
     */
    protected function isGhInstalled(): bool
    {
        $process = new Process(['gh', 'auth', 'status']);

        $process->run();

        return $process->isSuccessful();
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

        if (! $this->spreadJoy) {
            $this->output->writeln('Spread some joy and star our GitHub repo! https://github.com/statamic/cms');
        }

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

        $response = select('Would you like to spread the joy of Statamic by starring the repo?', [
            $yes = "Absolutely. I'll star it while you finish installing.",
            $no = 'Maybe later',
        ], $no);

        if ($this->spreadJoy = $response === $yes) {
            $this->output->write('  Awesome. The browser will open when the installation begins.');
        } else {
            $this->output->write('  You can star the GitHub repo at any time if you change your mind.');
        }

        $this->output->write(PHP_EOL.PHP_EOL);

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
        throw new RuntimeException('Cannot connect to [statamic.com] to validate license. Please try again later.');
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

        return text('Please enter your license key', required: true);
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

    private function searchStarterKits($value)
    {
        $kits = $this->getStarterKits();

        return array_filter($kits, fn ($kit) => str_contains(strtolower($kit), strtolower($value)));
    }

    private function getStarterKits()
    {
        return $this->starterKits ??= $this->fetchStarterKits();
    }

    private function fetchStarterKits()
    {
        $request = new Client(['base_uri' => self::STATAMIC_API_URL]);

        try {
            $response = $request->get('marketplace/starter-kits', ['query' => ['perPage' => 100]]);
            $results = json_decode($response->getBody(), true)['data'];
            $options = [];

            foreach ($results as $value) {
                $options[$value['package']] = $value['name'].' ('.$value['package'].')';
            }

            return $options;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function normalizeStarterKitSelection($kit)
    {
        // If it doesn't have a bracket it means they manually entered a value and didn't pick a suggestion.
        if (! str_contains($kit, ' (')) {
            return $kit;
        }

        return array_search($kit, $this->getStarterKits());
    }
}
