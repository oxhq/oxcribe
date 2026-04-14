<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\OperationGraph;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteMatch;
use Oxhq\Oxcribe\Overrides\OverrideApplier;
use Oxhq\Oxcribe\Overrides\OverrideRule;
use Oxhq\Oxcribe\Overrides\OverrideSet;

it('filters excluded operations and carries additive override metadata', function () {
    $graph = new OperationGraph(
        operations: [
            new MergedOperation(
                routeId: 'route-users-index',
                methods: ['GET'],
                uri: 'users',
                domain: null,
                name: 'users.index',
                prefix: 'api',
                middleware: ['api', 'auth:sanctum'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserController', 'index'),
                routeMatch: new RouteMatch('route-users-index', 'controller_method', 'matched', 'App\\Http\\Controllers\\UserController::index'),
                controller: [
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ),
            new MergedOperation(
                routeId: 'route-hidden',
                methods: ['GET'],
                uri: 'hidden',
                domain: null,
                name: 'hidden.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\HiddenController', 'index'),
                routeMatch: new RouteMatch('route-hidden', 'controller_method', 'missing_static', 'App\\Http\\Controllers\\HiddenController::index'),
                controller: [
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ),
        ],
        diagnostics: [],
        models: [],
        resources: [],
        polymorphic: [],
        broadcast: [],
    );

    $overrides = new OverrideSet([
        OverrideRule::fromArray([
            'tags' => ['Config'],
        ], 'config:defaults'),
        OverrideRule::fromArray([
            'match' => [
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
            ],
            'tags' => ['Users'],
            'summary' => 'List users',
            'description' => 'List active users in the tenant.',
            'operationId' => 'users.index',
            'deprecated' => true,
            'security' => [
                ['bearerAuth' => []],
            ],
            'examples' => [
                '200' => [
                    'summary' => 'Users response',
                    'value' => ['data' => []],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Users payload',
                ],
            ],
            'requestBody' => [
                'required' => false,
            ],
            'x-oxcribe' => [
                'product' => [
                    'surface' => 'admin',
                ],
            ],
            'externalDocs' => [
                'url' => 'https://example.test/docs/users',
            ],
            'extensions' => [
                'x-internal' => [
                    'owner' => 'platform',
                ],
            ],
        ], 'file:users'),
        OverrideRule::fromArray([
            'match' => [
                'routeId' => 'route-hidden',
            ],
            'include' => false,
        ], 'file:hidden'),
    ], ['config', 'file']);

    $result = app(OverrideApplier::class)->apply($graph, $overrides);

    expect($result->graph->operations)->toHaveCount(1)
        ->and($result->graph->operations[0]->routeId)->toBe('route-users-index')
        ->and($result->graph->operations[0]->controller)->toMatchArray([
            'http' => [
                'status' => 200,
                'explicit' => true,
            ],
            'overrides' => [
                'summary' => 'List users',
                'description' => 'List active users in the tenant.',
                'operationId' => 'users.index',
                'tags' => ['Config', 'Users'],
                'deprecated' => true,
                'security' => [
                    ['bearerAuth' => []],
                ],
                'examples' => [
                    '200' => [
                        'summary' => 'Users response',
                        'value' => ['data' => []],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Users payload',
                    ],
                ],
                'requestBody' => [
                    'required' => false,
                ],
                'x-oxcribe' => [
                    'product' => [
                        'surface' => 'admin',
                    ],
                ],
                'externalDocs' => [
                    'url' => 'https://example.test/docs/users',
                ],
                'extensions' => [
                    'x-internal' => [
                        'owner' => 'platform',
                    ],
                ],
                'matchedSources' => ['config:defaults', 'file:users'],
            ],
        ])
        ->and($result->resolutions)->toHaveCount(2)
        ->and($result->resolutions[1]->included)->toBeFalse()
        ->and($result->resolutions[1]->routeId)->toBe('route-hidden');
});

it('supports allowlisting published routes by middleware marker', function () {
    $graph = new OperationGraph(
        operations: [
            new MergedOperation(
                routeId: 'route-public',
                methods: ['GET'],
                uri: 'api/public/metrics',
                domain: null,
                name: 'public.metrics.index',
                prefix: 'api/public',
                middleware: ['api', 'auth:sanctum', 'oxcribe.publish'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\PublicMetricsController', 'index'),
                routeMatch: new RouteMatch('route-public', 'controller_method', 'matched', 'App\\Http\\Controllers\\PublicMetricsController::index'),
                controller: null,
            ),
            new MergedOperation(
                routeId: 'route-private',
                methods: ['GET'],
                uri: 'api/private/metrics',
                domain: null,
                name: 'private.metrics.index',
                prefix: 'api/private',
                middleware: ['api', 'auth:sanctum', 'oxcribe.private'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\PrivateMetricsController', 'index'),
                routeMatch: new RouteMatch('route-private', 'controller_method', 'matched', 'App\\Http\\Controllers\\PrivateMetricsController::index'),
                controller: null,
            ),
            new MergedOperation(
                routeId: 'route-unmarked',
                methods: ['GET'],
                uri: 'api/internal/stats',
                domain: null,
                name: 'internal.stats.index',
                prefix: 'api/internal',
                middleware: ['api', 'auth:sanctum'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\InternalStatsController', 'index'),
                routeMatch: new RouteMatch('route-unmarked', 'controller_method', 'matched', 'App\\Http\\Controllers\\InternalStatsController::index'),
                controller: null,
            ),
        ],
        diagnostics: [],
        models: [],
        resources: [],
        polymorphic: [],
        broadcast: [],
    );

    $overrides = new OverrideSet([
        OverrideRule::fromArray([
            'match' => [
                'uri' => '*',
            ],
            'include' => false,
        ], 'visibility:exclude-all'),
        OverrideRule::fromArray([
            'match' => [
                'middleware' => 'oxcribe.publish',
            ],
            'include' => true,
        ], 'visibility:include'),
        OverrideRule::fromArray([
            'match' => [
                'middleware' => ['oxcribe.private', 'ox.private'],
            ],
            'include' => false,
        ], 'visibility:exclude'),
    ], ['visibility']);

    $result = app(OverrideApplier::class)->apply($graph, $overrides);

    expect($result->graph->operations)->toHaveCount(1)
        ->and($result->graph->operations[0]->routeId)->toBe('route-public')
        ->and($result->resolutions[0]->included)->toBeTrue()
        ->and($result->resolutions[1]->included)->toBeFalse()
        ->and($result->resolutions[2]->included)->toBeFalse();
});
