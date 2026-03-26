<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FixtureRuntimeSnapshotFactory implements RuntimeSnapshotFactory
{
    private bool $loaded = false;

    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly RouteSnapshotExtractor $routeSnapshotExtractor,
        private readonly PackageInventoryDetector $packageInventoryDetector,
        private readonly string $fixtureRoot,
        private readonly string $routeNamePrefix,
        private readonly string $routeFile = 'routes/api.php',
        private readonly array $routeGroupMiddleware = ['api'],
        private readonly string $routeGroupPrefix = 'api',
    ) {}

    public function make(): RuntimeSnapshot
    {
        $this->bootFixtureRoutes();

        $routes = [];
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name) || ! str_starts_with($name, $this->routeNamePrefix)) {
                continue;
            }

            $routes[] = $this->routeSnapshotExtractor->extractSnapshot($route);
        }

        return new RuntimeSnapshot(
            app: new AppSnapshot(
                basePath: $this->fixtureRoot,
                laravelVersion: $this->app->version(),
                phpVersion: PHP_VERSION,
                appEnv: $this->app->environment(),
            ),
            routes: $routes,
            packages: $this->packageInventoryDetector->detect($this->fixtureRoot),
        );
    }

    private function bootFixtureRoutes(): void
    {
        if ($this->loaded) {
            return;
        }

        require_once __DIR__.'/FixtureSpatieStubs.php';

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixtureRoot.'/app', RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files);

        foreach ($files as $file) {
            require_once $file;
        }

        $registrar = Route::middleware($this->routeGroupMiddleware);
        if ($this->routeGroupPrefix !== '') {
            $registrar = $registrar->prefix($this->routeGroupPrefix);
        }

        $registrar->group($this->fixtureRoot.'/'.$this->routeFile);

        $this->loaded = true;
    }
}
