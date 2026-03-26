<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Runtime;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;

final class LaravelRuntimeSnapshotFactory implements RuntimeSnapshotFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly RouteSnapshotExtractor $routeSnapshotExtractor,
        private readonly PackageInventoryDetector $packageInventoryDetector,
    ) {}

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
