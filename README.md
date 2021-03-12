# Statamic CLI Tool

![Statamic 3.0+](https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge&link=https://statamic.com)

🌴 Install and manage your **Statamic v3** projects from the command line.

- [Installing the CLI tool](#installing-the-cli-tool)
- [Using the CLI tool](#using-the-cli-tool)
    - [Installing Statamic](#installing-statamic)
    - [Checking Statamic versions](#checking-statamic-versions)
    - [Updating Statamic](#updating-statamic)

## Installing the CLI tool

```
composer global require statamic/cli
```

Make sure to place Composer's system-wide vendor bin directory in your `$PATH` so the `statamic` executable can be located by your system. This directory exists in different locations based on your operating system; however, some common locations include:

- MacOS: `$HOME/.composer/vendor/bin`
- Windows: `%USERPROFILE%\AppData\Roaming\Composer\vendor\bin`
- GNU / Linux Distributions: `$HOME/.config/composer/vendor/bin` or `$HOME/.composer/vendor/bin`

Once installed, you should be able to run `statamic {command name}` from within any directory.

### GitHub authentication

When you install starter kits, the CLI might present you with a warning that the GitHub API limit is reached. [Generate a Personal acces token](https://github.com/settings/tokens/new) and paste it in the CLI. Composer will save this for future use.

## Updating the CLI tool

```
composer global update statamic/cli
```

Run this command to update the CLI tool to the most recent published version.

## Using the CLI tool

### Installing Statamic

You may create a new Statamic site with the `new` command:

```
statamic new my-site
```

This will present you with a list of supported starter kits to select from.  Upon selection, the latest version will be downloaded and installed into the `my-site` directory.

You may also pass an explicit starter kit repo if you wish to skip the selection prompt:

```
statamic new my-site --starter="statamic/starter-kit-cool-writings"
```

### Checking Statamic versions

From within an existing Statamic project root directory, you may run the following command to quickly find out which version is being used.

```
statamic version
```

### Updating Statamic

From within an existing Statamic project root directory, you may use the following command to update to the latest version.

```
statamic update
```

This is just syntactic sugar for the `composer update statamic/cms --with-dependencies` command.
