<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Merge;

use Oxhq\Oxcribe\Data\AnalysisResponse;
use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\OperationGraph;
use Oxhq\Oxcribe\Data\RouteMatch;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\FormRequestFieldResolver;

final class OperationGraphMerger
{
    public function __construct(
        private readonly FormRequestFieldResolver $formRequestFieldResolver = new FormRequestFieldResolver,
    ) {}

    public function merge(RuntimeSnapshot $runtime, AnalysisResponse $response): OperationGraph
    {
        $routeMatches = [];
        foreach ($response->routeMatches as $routeMatch) {
            $routeMatches[$routeMatch->routeId] = $routeMatch;
        }

        $controllers = [];
        foreach ((array) ($response->delta['controllers'] ?? []) as $controller) {
            $actionKey = sprintf('%s::%s', $controller['fqcn'], $controller['method']);
            $controllers[$actionKey] = $controller;
        }

        $operations = [];
        foreach ($runtime->routes as $route) {
            $routeMatch = $routeMatches[$route->routeId] ?? new RouteMatch(
                routeId: $route->routeId,
                actionKind: $route->action->kind,
                matchStatus: 'unsupported',
            );

            $controller = $routeMatch->actionKey !== null
                ? ($controllers[$routeMatch->actionKey] ?? null)
                : null;
            $controller = $this->formRequestFieldResolver->augment($route->action, $route->methods, $controller);

            $operations[] = new MergedOperation(
                routeId: $route->routeId,
                methods: $route->methods,
                uri: $route->uri,
                domain: $route->domain,
                name: $route->name,
                prefix: $route->prefix,
                middleware: $route->middleware,
                where: $route->where,
                defaults: $route->defaults,
                bindings: $route->bindings,
                action: $route->action,
                routeMatch: $routeMatch,
                controller: $controller,
            );
        }

        return new OperationGraph(
            operations: $operations,
            diagnostics: $response->diagnostics,
            models: array_values((array) ($response->delta['models'] ?? [])),
            resources: array_values((array) ($response->delta['resources'] ?? [])),
            polymorphic: array_values((array) ($response->delta['polymorphic'] ?? [])),
            broadcast: array_values((array) ($response->delta['broadcast'] ?? [])),
        );
    }
}
