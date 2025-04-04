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

```bash
# Update version in a specific package (search in parent folders)
composer exec verup -n my/pkg 1.0

# Use package.json instead of composer.json
composer exec verup -p package.json 1
```
