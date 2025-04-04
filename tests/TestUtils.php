<?php

namespace duzun\Verup\Tests;

use RuntimeException;
use duzun\Verup;

/**
 * Test utilities and setup helpers
 */
class TestUtils
{
    /**
     * Path to the test directory
     */
    public static $testDir;

    /**
     * Registry of files to clean up
     */
    private static $cleanupRegistry = [];

    /**
     * Initialize the test environment
     */
    public static function init()
    {
        // Set test directory path
        self::$testDir = __DIR__ . '/tmp';

        // Ensure test directory exists with proper permissions
        if (!file_exists(self::$testDir)) {
            if (!mkdir(self::$testDir, 0777, true)) {
                throw new RuntimeException('Failed to create test directory: ' . self::$testDir);
            }
            chmod(self::$testDir, 0777); // Ensure directory is writable
        }

        // Register cleanup function
        register_shutdown_function(function () {
            self::cleanup();
        });

        // Initialize mock CLI arguments
        self::setArgv([]);
    }

    /**
     * Set the CLI arguments for the test
     */
    public static function setArgv(array $args = [])
    {
        global $argv;
        $argv = [dirname(__DIR__) . '/verup.php', ...$args];
    }

    /**
     * Create a standard composer.json file for testing
     *
     * @param array $customData Additional/override data for composer.json
     * @return bool|int Number of bytes written or false on failure
     */
    public static function createPackageFile(string $filename, array $customData = [])
    {
        // Ensure test directory exists
        if (!file_exists(self::$testDir)) {
            if (!mkdir(self::$testDir, 0777, true)) {
                throw new RuntimeException('Failed to create test directory: ' . self::$testDir);
            }
            chmod(self::$testDir, 0777);
        }

        $defaultContent = array_replace_recursive([
            'name' => 'test/project',
            'version' => '1.1.1', // Could be missing if .extra.verup.version is present
            'description' => 'Test project',
            'type' => 'library',
            'license' => 'MIT',
            'extra' => [
                'verup' => [
                    'version' => '1.2.3', // SoT
                    'files' => ['./version.php'], // Relative to composer.json
                    'regs' => [
                        '/^((?:\$|(?:\s*\*\s*@)|(?:\s*(?:var|,)?\s+))version[\s\:=\'"]+)([0-9]+(?:\.[0-9]+){2,2})/i'
                    ]
                ]
            ]
        ], $customData);

        $jsonContent = json_encode($defaultContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
            throw new RuntimeException("Failed to encode {$filename} content");
        }
        return self::writeFile($filename, $jsonContent . "\n");
    }

    /**
     * Create a standard version.php file for testing
     *
     * @param string $version Version number to use
     * @return bool|int Number of bytes written or false on failure
     */
    public static function createVersionFile(string $version = '1.2.3')
    {
        return self::writeFile('version.php', "<?php\n/**\n * Version file for tests\n * @version {$version}\n */\n\n\$version = '{$version}';\n\nreturn \$version;");
    }

    /**
     * Setup standard test files
     */
    public static function setupStandardTestFiles()
    {
        // Create fresh test files
        self::createPackageFile('composer.json');
        self::createPackageFile('package.json');
        self::createVersionFile();
    }

    /**
     * Create a test file with the given content
     *
     * @param string $file Filename
     * @param string $content File content
     * @return bool|int Number of bytes written or false on failure
     */
    public static function writeFile(string $file, string $content)
    {
        $filePath = self::getFilePath($file);

        // Create parent directory if it doesn't exist
        $dir = dirname($filePath);
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new RuntimeException('Failed to create directory: ' . $dir);
            }
            chmod($dir, 0777);
        }

        // Write file
        $ret = file_put_contents($filePath, $content, LOCK_EX);
        if (false === $ret) {
            throw new RuntimeException('Failed to write file: ' . $filePath);
        }

        // Make file readable
        chmod($filePath, 0666);

        // Register for cleanup
        self::$cleanupRegistry[] = $filePath;

        return $ret;
    }

    /**
     * Cleanup all test files
     */
    public static function cleanup()
    {
        // Clean up registered files
        foreach (self::$cleanupRegistry as $k => $file) {
            if (file_exists($file)) {
                if (!unlink($file)) {
                    error_log('Failed to remove file: ' . $file);
                }
            }
            unset(self::$cleanupRegistry[$k]);
        }
    }

    /**
     * Get the full path to a test file
     *
     * @param string $filename Filename in test directory
     * @return string Full path to file
     */
    public static function getFilePath(string $filename)
    {
        return rtrim(self::$testDir, '/') . '/' . ltrim($filename, '/');
    }

    /**
     * Capture I/O during execution of a callback
     *
     * @param callable $callback Function to execute while capturing I/O
     * @return array [mixed $result, string $output] Result of callback and captured output
     */
    public static function captureIO(callable $callback)
    {
        // Create a temporary memory stream for errors
        $stream = fopen('php://memory', 'w+');

        try {
            // Set the error stream in Verup
            Verup::setErrorStream($stream);

            // Start output buffering
            ob_start();

            try {
                // Run the callback
                $result = $callback();
            } finally {
                // Get buffered output
                $output = ob_get_clean();

                // Get error output
                rewind($stream);
                $output .= stream_get_contents($stream);
            }

            return [$result, $output];
        } finally {
            // Close the stream
            fclose($stream);
        }
    }
}
