<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

use Oxhq\Oxcribe\Auth\AuthProfile;

final readonly class MergedOperation
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $defaults
     * @param  list<RouteBinding>  $bindings
     * @param  array<string, mixed>|null  $controller
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
        public RouteMatch $routeMatch,
        public ?array $controller,
    ) {}

    public function requiresAuthentication(): bool
    {
        return $this->authProfile()->requiresAuthentication;
    }

    public function requiresAuthorization(): bool
    {
        return $this->authProfile()->requiresAuthorization;
    }

    public function authProfile(): AuthProfile
    {
        return AuthProfile::fromMiddleware($this->middleware, (array) config('oxcribe', []));
    }

    /**
     * @return list<string>
     */
    public function authenticationMiddleware(): array
    {
        return $this->authProfile()->authenticationMiddleware();
    }

    /**
     * @return list<string>
     */
    public function authorizationMiddleware(): array
    {
        return $this->authProfile()->authorizationMiddleware();
    }

    /**
     * @return list<array{kind: string, values: list<string>, guard: ?string, guards: list<string>, schemeCandidates: list<string>, source: string, subject?: ?string, ability?: ?string, resolution: string}>
     */
    public function authorizationConstraints(): array
    {
        return $this->authProfile()->authorizationConstraints();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staticAuthorizationHints(): array
    {
        $hints = (array) ($this->controller['authorization'] ?? []);

        return array_values(array_filter($hints, static function (mixed $hint): bool {
            return is_array($hint)
                && is_string($hint['kind'] ?? null)
                && is_string($hint['source'] ?? null)
                && is_string($hint['resolution'] ?? null)
                && ($hint['resolution'] ?? null) !== 'runtime';
        }));
    }

    /**
     * @return list<array{kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, source: string, resolution: string, metadata?: array<string, mixed>}>
     */
    public function runtimeConstraints(): array
    {
        return $this->authProfile()->runtimeConstraints();
    }

    public function operationId(): string
    {
        return $this->name
            ?? str_replace(['/', '{', '}', '-', '.'], ['_', '', '', '_', '_'], $this->routeId);
    }
}
