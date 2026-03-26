<?php

declare(strict_types=1);

use Garaekz\Oxcribe\OxcribeServiceProvider;

it('registers the package service provider in the test application', function () {
    if (! class_exists(OxcribeServiceProvider::class)) {
        $this->markTestSkipped('OxcribeServiceProvider has not been created yet.');
    }

    expect(app()->getProvider(OxcribeServiceProvider::class))
        ->toBeInstanceOf(OxcribeServiceProvider::class);
});

