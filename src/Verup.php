<?php

namespace duzun;

/**
 * Increment and update version in all project files.
 *
 * @author Dumitru Uzun (DUzun.Me)
 * @version 1.2.0
 */

// Verup class definition
class Verup
{
    const VERSION = '1.2.0';
    const NAME    = 'duzun/verup';

    public static $args = [
        /**
         * Bump should be 1 for revision, 1.0 for minor and 1.0.0 for major version
         * @var string
         */
        'bump' => '1',

        /**
         * Package name to bump (search for folder containing package file with this name)
         * @var string
         */
        'name' => '',

        /**
         * Show help message?
         * @var bool
         */
        'help' => false,

        /**
         * Package file to use (e.g. composer.json or package.json)
         * @var string
         */
        'package' => 'composer.json',

        /**
         * Working directory to use
         * @var string|null
         */
        'dir' => null,
    ];


    /**
     * Regular expressions for matching version strings in different formats
     * @var array<string>
     */
    public static $ver_reg = array(
        // var version = 'x.x.x'; $version = 'x.x.x'; version := 'x.x.x'; @version x.x.x;
        '/^((?:\$|(?:\s*\**\s*@)|(?:\s*(?:var|,)?\s+))version[\s\:=\'"]+)([0-9]+(?:\.[0-9]+){2,2})/i',
        // const VERSION = 'x.x.x';
        '/^(\s*(?:export\s+)?(?:const|var|let)\s+VERSION[\s=\'"]+)([0-9]+(?:\.[0-9]+){2,2})/i',
        // * vX.X.X
        '/^(\s?\*.*v)([0-9]+(?:\.[0-9]+){2,2})/',
    );

    /**
     * Path to the found package file
     * @var string|null
     */
    public static $packFile;

    /**
     * @var resource Error output stream
     */
    protected static $errorStream = null;

    /**
     * Show help message
     */
    public static function showHelp($asError = false)
    {
        $help = <<<HELP
Usage: composer exec verup [options] [<bump>]

Options:
  -n, --name <name>    Package name to bump
                       Search for folder with package file containing this name.
  -p, --package <file> Package file to use (default: composer.json)
                       Supported: composer.json, package.json
  -b, --bump <bump>    Version increment (default: 1)
                       1     - increment revision by 1
                       1.0   - increment minor version by 1
                       1.0.0 - increment major version by 1
  -h, --help          Show this help message

Example:
  composer exec verup 1          # Increment revision by 1
  composer exec verup 1.0        # Increment minor version by 1
  composer exec verup 1.0.0      # Increment major version by 1
  composer exec verup -n my/pkg  # Bump version for package my/pkg
HELP;
        if ($asError) {
            self::echoError($help . PHP_EOL);
        } else {
            echo $help . PHP_EOL;
        }
    }

    /**
     * Run verup
     * @param string|null $cwd Current working directory
     * @return bool
     */
    public static function run($cwd = null)
    {
        $readArgv = self::readArgv();
        if (!$readArgv || self::$args['help']) {
            self::showHelp(!$readArgv);
            return $readArgv;
        }

        $cwd = $cwd ?? self::$args['dir'];
        // Use specified directory or current working directory
        if ($cwd !== null) {
            $cwd = self::normPath($cwd);
        } else {
            $cwd = rtrim(getcwd(), '/\\');
        }

        // Find package file
        self::$packFile = self::findPackage($cwd, self::$args['name']);

        if (self::$packFile === false) {
            self::echoError(self::$args['package'] . ' file not found');
            return false;
        }
        if (self::$packFile === null) {
            self::echoError('Can\'t read ' . self::$args['package'] . ' file');
            return false;
        }

        // Get root directory and read package file
        $_root = dirname(self::$packFile);
        $packo = self::readJSONFile(self::$packFile);

        if (!$packo) {
            self::echoError('Can\'t read ' . self::$args['package'] . ' file');
            return false;
        }

        $_verup = $packo->extra->verup;

        if (!$_verup) {
            self::echoError(self::$args['package'] . ' doesn\'t have a `.extra.verup` property defined');
            return false;
        }

        $files = $_verup->files;
        $ver_reg = self::getVersionRegexes($_verup->regs);

        // First try to get version from extra.verup.version, fallback to version
        $over = $_verup->version ?? $packo->version;
        if ($over) {
            $nver = self::bumpVersion($over, self::$args['bump']);
            if (property_exists($packo, 'version')) {
                $packo->version = $nver;
            }
            if (!empty($packo->extra->verup) && property_exists($packo->extra->verup, 'version')) {
                $packo->extra->verup->version = $nver;
            }

            echo ('Bumping version: ' . $over . ' -> ' . $nver), PHP_EOL;

            $buf = self::json_encode($packo);
            if ($buf && $over != $nver) {
                $buf .= "\n";
                file_put_contents(self::$packFile, $buf);
            }

            foreach ($files as $f) {
                $fn = $_root . '/' . $f;
                $cnt = file_get_contents($fn);
                $ext = strrchr($f, '.');
                $buf = NULL;

                switch ($ext) {
                    case '.json': {
                            $packo = json_decode($cnt, false);
                            if (property_exists($packo, 'version')) {
                                $packo->version = $nver;
                            }
                            if (!empty($packo->extra->verup) && property_exists($packo->extra->verup, 'version')) {
                                $packo->extra->verup->version = $nver;
                            }
                            $buf = self::json_encode($packo);
                            if ($buf) {
                                $buf .= "\n";
                            }
                        }
                        break;

                    default: {
                            $buf = NULL;
                            foreach (explode("\n", $cnt) as $l) {
                                for ($i = count($ver_reg); $i--;) {
                                    if (preg_match($e = $ver_reg[$i], $l)) {
                                        $l = preg_replace($e, '${1}' . $nver, $l, 1);
                                        break;
                                    }
                                }
                                $buf[] = $l;
                            }
                            $buf = implode("\n", $buf);
                            // $buf = NULL; // dev
                        }
                }
                if ($buf && $buf != $cnt) {
                    echo ("\t" . preg_replace('/^[\\/]+/', '', str_replace($_root, '', $fn))), PHP_EOL;
                    file_put_contents($fn, $buf);
                }
            }
        } else {
            self::echoError('There is no .version property in your ' . self::$args['package']);
            return false;
        }

        return $nver;
    }

    /**
     * Bump version number according to semver
     * @param string $version Current version
     * @param string $bump Version increment (e.g. '1', '1.0', '1.0.0')
     * @return string New version
     */
    public static function bumpVersion($version, $bump)
    {
        $bump = array_reverse(explode('.', $bump));
        $nver = array_reverse(explode('.', $version));
        $b = NULL;
        $l = NULL;
        while (count($bump) && !($b = (int)(array_pop($bump))));
        $l = count($bump);

        $nver[$l] = $nver[$l] + $b;
        foreach ($bump as $i => $v) {
            $nver[$i] = $v;
        };

        return implode('.', array_reverse($nver));
    }

    // Helpers:

    /**
     * Read command line options
     * @return bool false to stop execution
     */
    public static function readArgv()
    {
        global $argv;
        $_a = 'b';

        foreach ($argv as $i => $v) {
            if ($i < 1) continue;

            if (strncmp($v, '-', 1) == 0 && !is_numeric($v)) {
                $_a = ltrim($v, '-');
                if ($_a === 'h' || $_a === 'help') {
                    self::$args['help'] = true;
                }
                continue;
            }

            switch ($_a) {
                case 'n':
                case 'name':
                    self::$args['name'] = $v;
                    break;
                case 'p':
                case 'package':
                    self::$args['package'] = $v;
                    break;
                case 'd':
                case 'dir':
                    self::$args['dir'] = $v;
                    break;
                case 'b':
                case 'bump':
                default:
                    self::$args['bump'] = $v;
                    break;
            }

            $_a = 'b';
        }

        return true;
    }


    /**
     * Find package file in closest folder from `dir` and up.
     * @param string $dir Directory to start searching from
     * @param string $packageName Package name to find
     * @return string|false|null Path to package file, false if not found, null if unreadable
     */
    public static function findPackage($dir, $packageName)
    {
        $d = $dir ? self::normPath($dir) : '.';
        $f = NULL;
        do {
            $f = rtrim($d, '/') . '/' . self::$args['package'];
            if (file_exists($f)) {
                if (!is_readable($f)) {
                    return null; // Indicates file exists but not readable
                }
                $p = self::readJSONFile($f);
                // Look for a specific project name
                if ($packageName) {
                    if ($p && isset($p->name)) {
                        if ($p->name == $packageName) {
                            return realpath($f);
                        }
                    }
                }
                // Look for any project except this one (verup)
                else {
                    if (!$p || $p->name != self::NAME) {
                        return $f;
                    }
                }
            }
            $dir = $d;
            $d = dirname($d);
        } while ($d != $dir && $d != '.');
        return false; // Indicates file not found
    }

    /**
     * Read a .json file as object
     * @param string $filename Path to JSON file
     * @return object|false JSON data as object, false on failure
     */
    public static function readJSONFile($filename)
    {
        if (!file_exists($filename)) return false;
        $data = file_get_contents($filename) and
            $data = json_decode($data);
        return $data;
    }

    public static function json_encode($data, $o = 0)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $o);
        // Convert each group of 4 spaces at the start of lines to 2 spaces
        return preg_replace_callback('/^((?: )+)/m', function ($m) {
            return str_repeat('  ', strlen($m[1]) / 4);
        }, $json);
    }

    /**
     * Set a custom error output stream
     *
     * @param resource $stream A writable stream
     */
    public static function setErrorStream($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Error stream must be a resource');
        }
        self::$errorStream = $stream;
    }

    /**
     * Get version regular expressions for matching version strings
     * @param array $regs List of regular expressions
     * @return array List of regular expressions
     */
    protected static function getVersionRegexes($regs = null)
    {
        if ($regs) {
            $ver_reg = array();
            foreach ($regs as $i => $v) {
                if (strncmp($v, '/', 1) != 0) {
                    $v = '/' . $v . '/i';
                }
                $ver_reg[$i] = $v;
            }
            return $ver_reg;
        }
        return self::$ver_reg;
    }

    /**
     * Write to error stream
     *
     * @param string $message
     */
    protected static function echoError($message)
    {
        $stream = self::$errorStream ?: STDERR;
        fwrite($stream, $message . PHP_EOL);
    }

    protected static function normPath($path)
    {
        $path = preg_replace('#[\\/]+(\.[\\/]+)*#', '/', $path);
        do {
            $_path = $path;
            $path = preg_replace('#(^|/)[^/]+(?<!\.\.)/\.\.(/|$)#', '/', $path);
        } while ($_path != $path);
        return rtrim($path, '/');
    }
}
