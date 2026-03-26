<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteBinding;
use Oxhq\Oxcribe\Data\RouteSnapshot;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

final class RouteSnapshotExtractor
{
    public function __construct(
        private readonly RouteIdFactory $routeIdFactory,
    ) {}

    public function extract(Route $route): array
    {
        return $this->extractSnapshot($route)->toArray();
    }

    public function extractSnapshot(Route $route): RouteSnapshot
    {
        $action = $this->resolveAction($route);
        $methods = array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => $method !== 'HEAD'
        ));

        return new RouteSnapshot(
            routeId: $this->routeIdFactory->make(
                methods: $methods,
                domain: $this->routeDomain($route),
                uri: $route->uri(),
                action: $action,
                name: $route->getName(),
            ),
            methods: $methods,
            uri: $route->uri(),
            domain: $this->routeDomain($route),
            name: $route->getName(),
            prefix: $this->routePrefix($route),
            middleware: array_values($route->gatherMiddleware()),
            where: $this->routeWhere($route),
            defaults: (array) ($route->getAction()['defaults'] ?? []),
            bindings: $this->extractBindings($route, $action),
            action: $action,
        );
    }

    private function resolveAction(Route $route): RouteAction
    {
        $action = $route->getAction();
        $uses = $action['uses'] ?? null;
        $controller = $action['controller'] ?? null;
        $signature = is_string($controller) ? $controller : (is_string($uses) ? $uses : null);

        if ($uses instanceof Closure || $route->getActionName() === 'Closure') {
            return new RouteAction('closure');
        }

        if (is_string($signature) && str_contains($signature, '@')) {
            [$fqcn, $method] = explode('@', $signature, 2);

            if ($method === '__invoke') {
                return new RouteAction('invokable_controller', $fqcn, '__invoke');
            }

            return new RouteAction('controller_method', $fqcn, $method);
        }

        if (is_string($signature) && $signature !== '') {
            return new RouteAction('invokable_controller', $signature, '__invoke');
        }

        return new RouteAction('unknown');
    }

    /**
     * @return list<RouteBinding>
     */
    private function extractBindings(Route $route, RouteAction $action): array
    {
        $parameterNames = $route->parameterNames();
        $reflection = $this->resolveReflection($route, $action);

        if ($reflection === null) {
            return [];
        }

        $bindings = [];
        foreach ($reflection->getParameters() as $parameter) {
            if (! in_array($parameter->getName(), $parameterNames, true)) {
                continue;
            }

            $typeName = $this->parameterTypeName($parameter);
            $isImplicit = $typeName !== null && is_a($typeName, UrlRoutable::class, true);

            $bindings[] = new RouteBinding(
                parameter: $parameter->getName(),
                kind: $this->bindingKind($typeName, $isImplicit),
                targetFqcn: $typeName,
                isImplicit: $isImplicit,
            );
        }

        return $bindings;
    }

    private function resolveReflection(Route $route, RouteAction $action): ReflectionFunction|ReflectionMethod|null
    {
        $uses = $route->getAction()['uses'] ?? null;

        if ($uses instanceof Closure) {
            return new ReflectionFunction($uses);
        }

        if ($action->fqcn === null || $action->method === null || ! method_exists($action->fqcn, $action->method)) {
            return null;
        }

        return new ReflectionMethod($action->fqcn, $action->method);
    }

    private function parameterTypeName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? null : $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if (! $namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                    continue;
                }

                return $namedType->getName();
            }
        }

        return null;
    }

    private function bindingKind(?string $typeName, bool $isImplicit): string
    {
        if ($isImplicit) {
            return 'implicit_model';
        }

        if ($typeName !== null) {
            return 'typed_parameter';
        }

        return 'parameter';
    }

    /**
     * @return array<string, mixed>
     */
    private function routeWhere(Route $route): array
    {
        return property_exists($route, 'wheres') && is_array($route->wheres)
            ? $route->wheres
            : [];
    }

    private function routeDomain(Route $route): ?string
    {
        return method_exists($route, 'getDomain') ? $route->getDomain() : null;
    }

    private function routePrefix(Route $route): ?string
    {
        return method_exists($route, 'getPrefix') ? $route->getPrefix() : null;
    }
}
