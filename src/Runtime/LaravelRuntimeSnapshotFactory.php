<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Runtime;

use Garaekz\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Garaekz\Oxcribe\Data\AppSnapshot;
use Garaekz\Oxcribe\Contracts\PackageInventoryDetector;
use Garaekz\Oxcribe\Data\RuntimeSnapshot;
use Garaekz\Oxcribe\Support\RouteSnapshotExtractor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;

final class LaravelRuntimeSnapshotFactory implements RuntimeSnapshotFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly RouteSnapshotExtractor $routeSnapshotExtractor,
        private readonly PackageInventoryDetector $packageInventoryDetector,
    ) {
    }

    public function make(): RuntimeSnapshot
    {
        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $routes[] = $this->routeSnapshotExtractor->extractSnapshot($route);
        }

        return new RuntimeSnapshot(
            app: new AppSnapshot(
                basePath: $this->app->basePath(),
                laravelVersion: $this->app->version(),
                phpVersion: PHP_VERSION,
                appEnv: $this->app->environment(),
            ),
            routes: $routes,
            packages: $this->packageInventoryDetector->detect($this->app->basePath()),
        );
    }
}
