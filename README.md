# Statamic CLI Tool

> Install and manage your **Statamic v2** projects from the command line.

## Installing the package

```
composer global require statamic/cli
```

Make sure to place the `$HOME/.composer/vendor/bin` directory (or the equivalent directory for your OS) in your `$PATH` so that the `statamic` executable can be located by your system.

Once installed, you should be able to run `statamic {command name}` from within any directory.



## Checking Statamic Versions

From within an existing Statamic project root directory, you may run the following command to quickly find out which version is being used.

```
statamic version
```



## Installing Statamic

You may create a new Statamic site with the `new` command:

```
statamic new my-site
```

This will download the latest version and install it into the `my-site` directory.



## Updating Statamic

From within an existing Statamic project root directory, you may use the following command to update to the latest version.

```
statamic update
```

This is just syntactic sugar for the `php please update` command, available from 2.6 onwards.
