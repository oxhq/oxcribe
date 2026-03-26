<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RuntimeSnapshot
{
    public PackageInventorySnapshot $packages;

    /**
     * @param  list<RouteSnapshot>  $routes
     */
    public function __construct(
        public AppSnapshot $app,
        public array $routes,
        ?PackageInventorySnapshot $packages = null,
    ) {
        $this->packages = $packages ?? PackageInventorySnapshot::empty();
    }

    public function toArray(): array
    {
        return [
            'app' => $this->app->toArray(),
            'routes' => array_map(static fn (RouteSnapshot $route): array => $route->toArray(), $this->routes),
            'packages' => $this->packages->toArray(),
        ];
    }

    public function toWireArray(): array
    {
        return [
            'app' => $this->app->toArray(),
            'routes' => array_map(static fn (RouteSnapshot $route): array => $route->toArray(), $this->routes),
            'packages' => $this->packages->toWireArray(),
        ];
    }
}
