<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RouteSnapshot
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $defaults
     * @param  list<RouteBinding>  $bindings
     */
    public function __construct(
        public string $routeId,
        public array $methods,
        public string $uri,
        public ?string $domain,
        public ?string $name,
        public ?string $prefix,
        public array $middleware,
        public array $where,
        public array $defaults,
        public array $bindings,
        public RouteAction $action,
    ) {}

    public function toArray(): array
    {
        return [
            'routeId' => $this->routeId,
            'methods' => array_values($this->methods),
            'uri' => $this->uri,
            'domain' => $this->domain,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'middleware' => array_values($this->middleware),
            'where' => (object) $this->where,
            'defaults' => (object) $this->defaults,
            'bindings' => array_map(static fn (RouteBinding $binding): array => $binding->toArray(), $this->bindings),
            'action' => $this->action->toArray(),
        ];
    }
}
