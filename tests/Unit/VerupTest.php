<?php

namespace duzun\Verup\Tests\Unit;

use duzun\Verup;
use duzun\Verup\Tests\TestUtils;

// Include the bootstrap file
require_once __DIR__ . '/../bootstrap.php';

/**
 * Common test utilities setup
 */
beforeAll(function () {
    // Create fresh test files once before all tests
    TestUtils::setupStandardTestFiles();
});

describe('Verup', function() {
    $beforeEach = function () {
        // Reset the CLI arguments before each test
        TestUtils::setArgv();

        // Reset static variables
        Verup::$args['bump'] = '1';
        Verup::$args['name'] = '';
        Verup::$args['package'] = 'composer.json';
        Verup::$args['help'] = false;
        Verup::$packFile = null;

    };
    beforeEach($beforeEach);

    /**
     * Tests for the main run() method
     */
    describe('run() method', function() use($beforeEach) {
        beforeEach(function() use($beforeEach) {
            $beforeEach();
            TestUtils::createPackageFile('composer.json', ['version' => '', 'extra' => ['verup' => ['version' => '1.2.3']]]);
            TestUtils::createPackageFile('package.json', ['version' => '', 'extra' => ['verup' => ['version' => '1.2.3']]]);
            TestUtils::createVersionFile('2.3.1');
        });

        it('can increment version by revision in composer.json', function () {
            TestUtils::setArgv(['1']);

            // Run with test directory as cwd
            $result = Verup::run(TestUtils::$testDir);

            expect($result)->toBe('1.2.4');

            $composerContent = json_decode(file_get_contents(TestUtils::getFilePath('composer.json')), true);
            expect($composerContent['version'])->toBe('1.2.4');
            expect($composerContent['extra']['verup']['version'])->toBe('1.2.4');

            $versionContent = file_get_contents(TestUtils::getFilePath('version.php'));
            expect($versionContent)->toContain('@version 1.2.4');
        });

        it('can increment version by revision in package.json', function () {
            TestUtils::createPackageFile('package.json', ['version' => '', 'extra' => ['verup' => ['version' => '1.2.3']]]);
            TestUtils::setArgv(['1', '--package', 'package.json']);

            // Run with test directory as cwd
            $result = Verup::run(TestUtils::$testDir);

            expect($result)->toBe('1.2.4');

            $packageContent = json_decode(file_get_contents(TestUtils::getFilePath('package.json')), true);
            expect($packageContent['version'])->toBe('1.2.4');
            expect($packageContent['extra']['verup']['version'])->toBe('1.2.4');

            $versionContent = file_get_contents(TestUtils::getFilePath('version.php'));
            expect($versionContent)->toContain('@version 1.2.4');
        });

        it('can increment version by minor', function () {
            TestUtils::setArgv(['1.0']);

            $result = Verup::run(TestUtils::$testDir);

            expect($result)->toBe('1.3.0');

            $composerContent = json_decode(file_get_contents(TestUtils::getFilePath('composer.json')), true);
            expect($composerContent['version'])->toBe('1.3.0');

            $versionContent = file_get_contents(TestUtils::getFilePath('version.php'));
            expect($versionContent)->toContain('@version 1.3.0');
        });

        it('can increment version by major', function () {
            TestUtils::setArgv(['1.0.0']);

            $result = Verup::run(TestUtils::$testDir);

            expect($result)->toBe('2.0.0');

            $composerContent = json_decode(file_get_contents(TestUtils::getFilePath('composer.json')), true);
            expect($composerContent['version'])->toBe('2.0.0');
            expect($composerContent['extra']['verup']['version'])->toBe('2.0.0');

            $versionContent = file_get_contents(TestUtils::getFilePath('version.php'));
            expect($versionContent)->toContain('@version 2.0.0');
        });

        it('returns false when composer.json not found', function () {
            unlink(TestUtils::getFilePath('composer.json'));

            [$result, $output] = TestUtils::captureIO(function() {
                return Verup::run(TestUtils::$testDir);
            });

            expect($result)->toBeFalse();
            expect($output)->toContain('composer.json file not found');
        });

        it('returns false when version property not found in composer.json', function () {
            // Create composer.json without version
            TestUtils::createPackageFile('composer.json', ['version' => null, 'extra' => ['verup' => ['version' => null]]]);

            [$result, $output] = TestUtils::captureIO(function() {
                return Verup::run(TestUtils::$testDir);
            });

            expect($result)->toBeFalse();
            expect($output)->toContain('There is no .version property in your composer.json');
        });

        it('returns false when can\'t read composer.json', function () {
            // Make composer.json unreadable
            chmod(TestUtils::getFilePath('composer.json'), 0000);

            [$result, $output] = TestUtils::captureIO(function() {
                return Verup::run(TestUtils::$testDir);
            });

            expect($result)->toBeFalse();
            expect($output)->toContain('Can\'t read composer.json file');

            // Restore permissions for cleanup
            chmod(TestUtils::getFilePath('composer.json'), 0644);
        });
    });

    /**
     * Tests for readArgv() method
     */
    describe('readArgv() method', function() use($beforeEach) {
        beforeEach($beforeEach);

        it('correctly parses increment level from arguments', function() {
            // Test revision increment
            Verup::$args['bump'] = '0';
            TestUtils::setArgv(['1']);
            Verup::readArgv();
            expect(Verup::$args['bump'])->toBe('1');

            // Test minor increment
            Verup::$args['bump'] = '1';
            TestUtils::setArgv(['1.0']);
            Verup::readArgv();
            expect(Verup::$args['bump'])->toBe('1.0');

            // Test major increment
            Verup::$args['bump'] = '1';
            TestUtils::setArgv(['1.0.0']);
            Verup::readArgv();
            expect(Verup::$args['bump'])->toBe('1.0.0');
        });

        it('correctly parses package name from arguments', function() {
            // Test with package name
            Verup::$args['bump'] = '1.0.0';
            Verup::$args['name'] = '';
            TestUtils::setArgv(['-n', 'test/project']);
            Verup::readArgv();
            expect(Verup::$args['name'])->toBe('test/project');
            expect(Verup::$args['bump'])->toBe('1.0.0'); // unchanged

            // Test with increment and package name
            Verup::$args['name'] = '';
            TestUtils::setArgv(['1.1', '--name', 'another/project']);
            Verup::readArgv();
            expect(Verup::$args['name'])->toBe('another/project');
            expect(Verup::$args['bump'])->toBe('1.1');
        });

        it('correctly parses package file type from arguments', function() {
            // Test with package.json
            Verup::$args['package'] = 'composer.json';
            TestUtils::setArgv(['-p', 'package.json']);
            Verup::readArgv();
            expect(Verup::$args['package'])->toBe('package.json');

            // Test with composer.json
            Verup::$args['package'] = 'package.json';
            TestUtils::setArgv(['--package', 'composer.json']);
            Verup::readArgv();
            expect(Verup::$args['package'])->toBe('composer.json');

            // Test directory flag
            TestUtils::setArgv(['--dir', '/tmp']);
            Verup::readArgv();
            expect(Verup::$args['dir'])->toBe('/tmp');

            // Test directory flag with short option
            TestUtils::setArgv(['-d', '/var/tmp']);
            Verup::readArgv();
            expect(Verup::$args['dir'])->toBe('/var/tmp');
        });
    });

    it('shows help message when help flag is used', function() {
        [$result, $output] = TestUtils::captureIO(function() {
            TestUtils::setArgv(['--help']);
            Verup::readArgv();
            expect(Verup::$args['help'])->toBeTrue();
            return Verup::run();
        });
        expect($result)->toBeTrue(); // no error when asking to show help
        expect($output)->toContain('Usage: composer exec verup [options] [<bump>]');
        expect($output)->toContain('-h, --help');
    });

    /**
     * Tests for findPackage() method
     */
    describe('findPackage() method', function() {
        it('finds composer.json in current directory', function() {
            $result = Verup::findPackage(TestUtils::$testDir, '');
            // Using realpath to normalize paths for comparison
            expect(realpath($result))->toBe(realpath(TestUtils::getFilePath('composer.json')));
        });

        it('finds composer.json in parent directory', function() {
            $result = Verup::findPackage(TestUtils::$testDir.'/sub-directory', '');
            // Using realpath to normalize paths for comparison
            expect(realpath($result))->toBe(realpath(TestUtils::getFilePath('composer.json')));
        });

        it('returns false when composer.json not found', function() {
            $result = Verup::findPackage(TestUtils::$testDir.'/../non-existent-directory', '');
            expect($result)->toBeFalse();
        });

        it('returns null when composer.json exists but not readable', function() {
            // Make composer.json unreadable
            chmod(TestUtils::getFilePath('composer.json'), 0000);

            $result = Verup::findPackage(TestUtils::$testDir, '');
            expect($result)->toBeNull();

            // Restore permissions
            chmod(TestUtils::getFilePath('composer.json'), 0644);
        });

        it('can find specific package by name', function() {
            // Create a specific package
            TestUtils::createPackageFile('composer.json', ['name' => 'specific/package']);

            $result = Verup::findPackage(TestUtils::$testDir, 'specific/package');
            // Using realpath to normalize paths for comparison
            expect(realpath($result))->toBe(realpath(TestUtils::getFilePath('composer.json')));
        });
    });

    /**
     * Tests for readJSONFile() method
     */
    describe('readJSONFile() method', function() {
        it('correctly reads and parses JSON files', function() {
            $result = Verup::readJSONFile(TestUtils::getFilePath('composer.json'));
            expect($result)->toBeObject();
            expect($result->name)->toBe('specific/package');
            expect($result->version)->toBe('1.1.1');
            expect($result->extra->verup->version)->toBe('1.2.3');
        });

        it('returns false for non-existent files', function() {
            $result = Verup::readJSONFile(TestUtils::getFilePath('non-existent.json'));
            expect($result)->toBeFalse();
        });

        it('returns false for invalid JSON files', function() {
            // Create invalid JSON file
            TestUtils::writeFile('invalid.json', '{ "name": "test", invalid json }');

            $result = Verup::readJSONFile(TestUtils::getFilePath('invalid.json'));
            expect($result)->toBeNull();
        });
    });

    /**
     * Tests for json_encode() method
     */
    describe('json_encode() method', function() {
        it('properly encodes data to JSON format', function() {
            $data = ['name' => 'test', 'version' => '1.0.0'];
            $result = Verup::json_encode($data);
            $decoded = json_decode($result);
            expect($decoded->name)->toBe('test');
            expect($decoded->version)->toBe('1.0.0');

            // Test with pretty print option
            $result = Verup::json_encode($data, JSON_PRETTY_PRINT);
            expect($result)->toContain('"name": "test"');
            expect($result)->toContain('"version": "1.0.0"');
        });
    });
});