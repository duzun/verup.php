# verup.php

Increment and update version in all project files according to semver.

> Note: This is a port to PHP of [verup.js](https://github.com/duzun/verup).

**v1.2.0**

## Installation

```bash
composer require duzun/verup --dev
```

## Usage

### Basic Examples

```bash
# Increment revision (patch) version by 1 (1.2.3 -> 1.2.4)
composer exec verup 1

# Increment minor version by 1 (1.2.3 -> 1.3.0)
composer exec verup 1.0

# Increment major version by 1 (1.2.3 -> 2.0.0)
composer exec verup 1.0.0
```

### Options

```text
-n, --name <n>      Package name to bump (finds package in parent directories)
-p, --package <file> Package file to use (default: composer.json)
                     Supported: composer.json, package.json
-h, --help          Show help message
```

### Examples with Options

If you have subprojects, and want to avoid confusion as of
which project to patch, use `-n <projectName>` option:

```bash
# Update version in a specific package (search in parent folders)
composer exec verup -n my/pkg 1.0
```

This will look for `package.json` with `name == 'my/pkg'` in all parent folders,
until it finds the right level, and patch the files at that level.

You can also use `-p <file>` option to specify a different package file:

```bash
# Use package.json instead of composer.json
composer exec verup -p package.json 1
```

## In your composer.json (or package.json)

The minimum setup for your project is to add the list of file names that contain
version string to `composer.json` at `extra.verup.files`.
Here is a sample:

```js
...
"extra": {
  "verup": {
    "files": [
      "manifest.json",
      "index.js",
      "README.MD" ...
    ]
  }
}
...
```

If the file is a `.json`, version is expected to be at key `extra.verup.version` or `version`.

You can define you own list of regular expressions in `package.json` at `extra.verup.regs`:

```json
...
"extra": {
  "verup": {
    "regs": [
      "((?:\\$|(?:\\s*\\*?\\s*@)|(?:^\\s*(?:var|,)?\\s+))ver(?:sion)?[\\s\\:='\"]+)([0-9]+(?:\\.[0-9]+){2,2})",
      "^(\\s*\\$(?:_)?version[\\s='\"]+)([0-9]+(?:\\.[0-9]+){2,2})",
      "^(\\s?\\*.*v)([0-9]+(?:\\.[0-9]+){2,2})"
    ]
  }
}
...
```

### Configuration Options

-   `version`: Current version of your package
-   `files`: List of files to update when version changes
-   `regs`: List of regular expressions to match version strings in files
