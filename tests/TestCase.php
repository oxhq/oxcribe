<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package provider once the oxcribe package skeleton exists.
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Garaekz\Oxcribe\OxcribeServiceProvider::class,
            \Inertia\ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
