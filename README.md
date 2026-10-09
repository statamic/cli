# Statamic CLI Tool

🌴 Install and manage your **Statamic** projects from the command line.

- [Installing the CLI tool](#installing-the-cli-tool)
    - [Adding an alias](#adding-an-alias)
    - [Installing directly](#installing-directly)
    - [GitHub authentication](#github-authentication)
- [Using the CLI tool](#using-the-cli-tool)
    - [Installing Statamic](#installing-statamic)
    - [Checking Statamic versions](#checking-statamic-versions)
    - [Updating Statamic](#updating-statamic)
- [Troubleshooting](#troubleshooting)
    - [Composer version conflicts](#composer-version-conflicts)

## Installing the CLI tool

We recommend running the CLI tool through [cpx](https://cpx.dev), rather than installing it directly. cpx runs Composer
packages on-the-fly, so you'll always be using the latest version of the CLI tool without needing to update it yourself.

If you're using Laravel Herd, `cpx` should already be available.

Otherwise, install cpx:

```
composer global require cpx/cpx
```

Then you can run the CLI tool with:

```
cpx statamic/cli {command name}
```

### Adding an alias

The rest of this document uses the shorter `statamic {command name}`. To make that work, add an alias to your shell
profile (e.g. `~/.zshrc` or `~/.bashrc`):

```
alias statamic='cpx statamic/cli'
```

If you install the CLI tool [directly](#installing-directly), `statamic` is available without an alias.

### Installing directly

If you'd rather not use cpx, you can install the CLI tool directly instead.

<details>
<summary>Show instructions</summary>

<br>

```
composer global require statamic/cli
```

Make sure to place Composer's system-wide vendor bin directory in your `$PATH` so the `statamic` executable can be
located by your system. [Here's how](https://statamic.dev/troubleshooting/command-not-found-statamic).

Once installed, you should be able to run `statamic {command name}` from within any directory, with no alias needed.

To update the CLI tool itself to the most recent published version, run:

```
composer global update statamic/cli
```

If there's been a major version release, you may need to run `require` instead of `update`.

</details>

### GitHub authentication

When you install starter kits, the CLI might present you with a warning that the GitHub API limit is
reached. [Generate a Personal access token](https://github.com/settings/tokens/new) and paste it in your terminal with
this command so Composer will save it for future use:

```bash
composer config --global --auth github-oauth.github.com [your_token_here]
```

Read more on this in the [Composer Docs](https://getcomposer.org/doc/articles/authentication-for-private-packages.md).

## Using the CLI tool

### Installing Statamic

You may create a new Statamic site with the `new` command:

```
statamic new my-site
```

This will present you with a list of supported starter kits to select from. Upon selection, the latest version will be
downloaded and installed into the `my-site` directory.

You may also pass an explicit starter kit repo if you wish to skip the selection prompt:

```
statamic new my-site statamic/starter-kit-cool-writings
```

### Checking Statamic versions

From within an existing Statamic project root directory, you may run the following command to quickly find out which
version is being used.

```
statamic version
```

### Updating Statamic

From within an existing Statamic project root directory, you may use the following command to update to the latest
version.

```
statamic update
```

This is just syntactic sugar for the `composer update statamic/cms --with-dependencies` command.

## Troubleshooting

### Composer version conflicts

If you installed the CLI tool [directly](#installing-directly), it shares Composer's global dependencies with every
other globally required package. When those packages need different versions of the same dependency, Composer may fail
to install or update the CLI tool.

The easiest fix is to remove the global install (`composer global remove statamic/cli`) and
[use cpx](#installing-the-cli-tool) instead, which runs the CLI tool in isolation.
