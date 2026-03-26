<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Tests;

use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Oxhq\Oxcribe\OxcribeServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package provider once the oxcribe package skeleton exists.
     */
    protected function getPackageProviders($app): array
    {
        return [
            OxcribeServiceProvider::class,
            ServiceProvider::class,
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
