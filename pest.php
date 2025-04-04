<?php

use Pest\Configuration;

return Configuration::create()
    ->withPath(__DIR__)
    ->withTestsDirectory(__DIR__ . '/tests')
    ->withBootstrapFile(__DIR__ . '/tests/bootstrap.php');
