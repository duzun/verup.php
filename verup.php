#!/usr/bin/env php
<?php
/**
 * Increment and update version in all project files.
 *
 * Usage:
 *
 *  Increment revision by 1:
 *      php verup.php 1
 *
 *  Increment minor version by 1:
 *      php verup.php 1.0
 *
 *  Increment major version by 1:
 *      php verup.php 1.0.0
 *
 *
 * @author Dumitru Uzun (DUzun.Me)
 * @version 1.0.0
 */

Verup::run() !== false or die(1);

class Verup {
    const VERSION = '1.0.0';
    const NAME = 'duzun/verup';

    public static $ver_reg = array(
        // var version = 'x.x.x'; $version = 'x.x.x'; version := 'x.x.x'; @version x.x.x;
        '/^((?:\$|(?:\s*\**\s*@)|(?:\s*(?:var|,)?\s+))version[\s\:=\'"]+)([0-9]+(?:\.[0-9]+){2,2})/i'
        // const VERSION = 'x.x.x';
      , '/^(\s*const\s+VERSION[\s=\'"]+)([0-9]+(?:\.[0-9]+){2,2})/i'
        // * vX.X.X
      , '/^(\s?\*.*v)([0-9]+(?:\.[0-9]+){2,2})/'
    );

    /// bump should be 1 for revision, 1.0 for minor and 1.0.0 for major version
    public static $bump = '1'; // bump by

    /// Project name to bump (search it's composer.json folder)
    public static $name = '';

    public static $packFile;

    /// Run verup
    public static function run() {
        self::readArgv();

        self::$packFile = self::findPackage(dirname(__FILE__), self::$name);

        if ( !self::$packFile ) {
            echo('composer.json file not found'), PHP_EOL;
            return false;
        }

        $_root = dirname(self::$packFile);
        $packo = self::readJSONFile(self::$packFile);

        if ( !$packo ) {
            echo('Can\'t read composer.json file'), PHP_EOL;
            return false;
        }

        $_verup = $packo->extra->verup;

        if ( !$_verup ) {
            echo('composer.json doesn\'t have a `.extra.verup` property defined'), PHP_EOL;
            return false;
        }

        $files = $_verup->files;

        if ( $_verup->regs ) {
            $ver_reg = array();
            foreach($_verup->regs as $i => $v) {
                if ( strncmp($v, '/', 1) != 0 ) {
                    $v = '/' . preg_quote($v) . '/i';
                }
                $ver_reg[$i] = $v;
            }
        }

        $over = $packo->version;
        if ( $over ) {
            $bump = array_reverse(explode('.', self::$bump));
            $nver = array_reverse(explode('.', $over));
            $b = NULL;
            $l = NULL;
            while(count($bump) && !($b = (int)(array_pop($bump))));
            $l = count($bump);

            // echo({b:b,nver:nver,over:over,l:l,bump:bump})
            $nver[$l] = $nver[$l] + $b;
            foreach($bump as $i => $v) { $nver[$i] = $v; };

            $nver = implode('.', array_reverse($nver));
            $packo->version = $nver;

            echo('Bumping version: ' . $over . ' -> ' . $nver), PHP_EOL;

            $buf = json_encode($packo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ( $buf && $over != $nver ) {
                $buf .= "\n";
                file_put_contents(self::$packFile, $buf);
            }

            foreach($files as $f) {
                $fn = $_root . '/' . $f;
                $cnt = file_get_contents($fn);
                $ext = strrchr($f, '.');
                $buf = NULL;

                switch($ext) {
                    case '.json': {
                        $packo = json_decode($cnt);
                        $packo->version = $nver;
                        $buf = json_encode($packo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        if ( $buf ) {
                            $buf .= "\n";
                        }
                    } break;

                    default: {
                        $buf = NULL;
                        foreach(explode("\n", $cnt) as $l) {
                            for($i=count($ver_reg); $i--;) {
                                if ( preg_match($ver_reg[$i], $l) ) {
                                    $buf[] = preg_replace($ver_reg[$i], '$1nver', $l);
                                    continue;
                                }
                            }
                            $buf[] = $l;
                        }
                        $buf = implode("\n", $buf);
                    }
                }
                if ( $buf && $buf != $cnt ) {
                    echo("\t" . preg_replace('/^[\\/]+/', '', str_replace($_root, '', $fn))), PHP_EOL;
                    file_put_contents($fn, $buf);
                }
            }

        }
        else {
            echo('There is no .version property in your composer.json'), PHP_EOL;
            return false;
        }

        return $nver;
    }

    // Helpers:

    /// Read command line options
    public static function readArgv() {
        global $argv;
        $_a = 'b';
        foreach($argv as $i => $v) {
            if ( $i < 1 ) continue;
            if ( strncmp($v, '-', 1) == 0 && !is_numeric($v) ) {
                $_a = substr($v, 1);
            }
            else {
                switch($_a) {
                    case 'b': {
                        self::$bump = $v;
                    } break;
                    case 'n': {
                        self::$name = $v;
                    } break;
                }
                $_a = 'b';
            }
        }
    }

    /// Find composer.json file in closest folder from `dir` and up.
    public static function findPackage($dir, $packageName) {
        $d = $dir or $d = '.';
        $f = NULL;
        do {
            $f = $d . '/composer.json';
            if ( file_exists($f) ) {
                $p = self::readJSONFile($f);
                // Look for a specific project name
                if ( $packageName ) {
                    if ( $p ) {
                        if ( $p->name == $packageName ) {
                            return $f;
                        }
                    }
                }
                // Look for any project except this one (verup)
                else {
                    if ( !$p || $p->name != self::NAME ) {
                        return $f;
                    }
                }
            }
            $dir = $d;
            $d = dirname($d);
        } while ($d != $dir && $d != '.');
        return false;
    }

    /// Read a .json file as object
    public static function readJSONFile($filename) {
        $data = file_get_contents($filename) and
        $data = json_decode($data);
        return $data;
    }

}


