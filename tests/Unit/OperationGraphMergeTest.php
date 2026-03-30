<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Auth\AuthProfile;
use Oxhq\Oxcribe\Data\AnalysisResponse;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteBinding;
use Oxhq\Oxcribe\Data\RouteSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Merge\OperationGraphMerger;
use Oxhq\Oxcribe\OpenApi\OpenApiDocumentFactory;

it('merges runtime routes with oxinfer controller matches', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
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
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-1',
        'runtimeFingerprint' => 'fp-1',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 4,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 4,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['application/json'],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);

    expect($graph->operations)->toHaveCount(1)
        ->and($graph->operations[0]->routeMatch->matchStatus)->toBe('matched')
        ->and($graph->operations[0]->controller)->toMatchArray([
            'fqcn' => 'App\\Http\\Controllers\\UserController',
            'method' => 'index',
        ]);
});

it('promotes body-only GET request fields into query parameters', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-games-index',
                methods: ['GET'],
                uri: 'api/games',
                domain: null,
                name: 'games.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\Api\\DiscoveryController', 'games'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-query-fallback',
        'runtimeFingerprint' => 'fp-query-fallback',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\Api\\DiscoveryController',
                    'method' => 'games',
                    'request' => [
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'search',
                                'type' => 'string',
                                'required' => false,
                                'nullable' => false,
                            ],
                            [
                                'location' => 'body',
                                'path' => 'limit',
                                'type' => 'integer',
                                'required' => false,
                                'nullable' => false,
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'required' => ['data'],
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-games-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\Api\\DiscoveryController::games',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $operation = $document['paths']['/api/games']['get'];
    $parameters = collect($operation['parameters'])->keyBy('name');

    expect($parameters->keys()->all())->toBe(['limit', 'search'])
        ->and($parameters['limit'])->toMatchArray([
            'in' => 'query',
            'required' => false,
            'schema' => [
                'type' => 'integer',
            ],
        ])
        ->and($parameters['search'])->toMatchArray([
            'in' => 'query',
            'required' => false,
            'schema' => [
                'type' => 'string',
            ],
        ])
        ->and(isset($operation['requestBody']))->toBeFalse();
});

it('does not emit a request body for GET operations when content types are present but fields resolve to query', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-games-index',
                methods: ['GET'],
                uri: 'api/games',
                domain: null,
                name: 'games.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\Api\\DiscoveryController', 'games'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-query-content-type',
        'runtimeFingerprint' => 'fp-query-content-type',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\Api\\DiscoveryController',
                    'method' => 'games',
                    'request' => [
                        'contentTypes' => ['application/json'],
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'search',
                                'type' => 'string',
                                'required' => false,
                                'nullable' => false,
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-games-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\Api\\DiscoveryController::games',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/api/games']['get'])->not->toHaveKey('requestBody');
});

it('hydrates GET query parameters from form request rules at runtime when static request fields are missing', function () {
    require_once __DIR__.'/../Fixtures/Requests/GameSearchRequest.php';
    require_once __DIR__.'/../Fixtures/Controllers/GameSearchController.php';

    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-games-search',
                methods: ['GET'],
                uri: 'api/games',
                domain: null,
                name: 'games.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'Tests\\Fixtures\\Controllers\\GameSearchController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-form-request-runtime',
        'runtimeFingerprint' => 'fp-form-request-runtime',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'Tests\\Fixtures\\Controllers\\GameSearchController',
                    'method' => 'index',
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'required' => ['data'],
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-games-search',
                'actionKind' => 'controller_method',
                'actionKey' => 'Tests\\Fixtures\\Controllers\\GameSearchController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $operation = $document['paths']['/api/games']['get'];
    $parameters = collect($operation['parameters'])->keyBy('name');

    expect($parameters->keys()->all())->toBe(['limit', 'search'])
        ->and($parameters['limit']['in'])->toBe('query')
        ->and($parameters['limit']['required'])->toBeFalse()
        ->and($parameters['limit']['schema']['type'])->toContain('integer')
        ->and($parameters['limit']['example'])->toBe($operation['x-oxcribe']['examples']['happy_path']['request']['queryParams']['limit'])
        ->and($parameters['search']['in'])->toBe('query')
        ->and($parameters['search']['required'])->toBeFalse()
        ->and($parameters['search']['schema']['type'])->toContain('string')
        ->and($parameters['search']['example'])->toBe($operation['x-oxcribe']['examples']['happy_path']['request']['queryParams']['search']);
});

it('uses inferred legacy query shapes for generated examples and snippets', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-games-index',
                methods: ['GET'],
                uri: 'api/games',
                domain: null,
                name: 'games.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\Api\\DiscoveryController', 'games'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-query-examples',
        'runtimeFingerprint' => 'fp-query-examples',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\Api\\DiscoveryController',
                    'method' => 'games',
                    'request' => [
                        'query' => [
                            'search' => [
                                'type' => 'string',
                            ],
                            'limit' => [
                                'type' => ['integer', 'null'],
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'required' => ['data'],
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-games-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\Api\\DiscoveryController::games',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $operation = $document['paths']['/api/games']['get'];

    expect($operation['x-oxcribe']['examples']['happy_path']['request']['queryParams'])->toMatchArray([
        'limit' => $operation['x-oxcribe']['examples']['happy_path']['request']['queryParams']['limit'],
        'search' => $operation['x-oxcribe']['examples']['happy_path']['request']['queryParams']['search'],
    ])
        ->and($operation['x-oxcribe']['snippets']['happy_path']['curl'])->toContain('limit=')
        ->and($operation['x-oxcribe']['snippets']['happy_path']['curl'])->toContain('search=');
});

it('keeps evidence-backed 5xx responses and gives them error descriptions', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-organizations-store',
                methods: ['POST'],
                uri: 'api/organizations',
                domain: null,
                name: 'organizations.store',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\OrganizationController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-5xx-policy',
        'runtimeFingerprint' => 'fp-5xx-policy',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\OrganizationController',
                    'method' => 'store',
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                        ],
                        [
                            'status' => 500,
                            'kind' => 'json_object',
                            'explicit' => true,
                            'source' => 'response()->json',
                            'via' => 'response()->json',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'error' => [
                                        'type' => 'string',
                                    ],
                                ],
                                'required' => ['error'],
                            ],
                        ],
                        [
                            'status' => 503,
                            'kind' => 'json_object',
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-organizations-store',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\OrganizationController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $operation = $document['paths']['/api/organizations']['post'];

    expect($operation['responses']['500']['description'])->toBe('Internal server error')
        ->and($operation['responses'])->not->toHaveKey('503');
});

it('generates human summaries and default tags when route names are machine-like', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-games-index',
                methods: ['GET'],
                uri: 'api/games',
                domain: null,
                name: 'games.index',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\Api\\DiscoveryController', 'games'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-human-summary',
        'runtimeFingerprint' => 'fp-human-summary',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\Api\\DiscoveryController',
                    'method' => 'games',
                    'responses' => [
                        [
                            'status' => 200,
                            'kind' => 'json_object',
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-games-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\Api\\DiscoveryController::games',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/api/games']['get'])->toMatchArray([
        'summary' => 'List Games',
        'tags' => ['Games'],
    ]);
});

it('humanizes system and docs routes instead of exposing awkward machine labels', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-root',
                methods: ['GET'],
                uri: '/',
                domain: null,
                name: 'home',
                prefix: null,
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\RootController', 'show'),
            ),
            new RouteSnapshot(
                routeId: 'route-health',
                methods: ['GET'],
                uri: 'up',
                domain: null,
                name: 'up',
                prefix: null,
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\HealthController', 'show'),
            ),
            new RouteSnapshot(
                routeId: 'route-docs',
                methods: ['GET'],
                uri: 'docs/api.json',
                domain: null,
                name: 'docs.api',
                prefix: null,
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\DocsController', 'show'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-system-summary',
        'runtimeFingerprint' => 'fp-system-summary',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                ['fqcn' => 'App\\Http\\Controllers\\RootController', 'method' => 'show', 'responses' => [['status' => 200, 'kind' => 'json_object']]],
                ['fqcn' => 'App\\Http\\Controllers\\HealthController', 'method' => 'show', 'responses' => [['status' => 200, 'kind' => 'json_object']]],
                ['fqcn' => 'App\\Http\\Controllers\\DocsController', 'method' => 'show', 'responses' => [['status' => 200, 'kind' => 'json_object']]],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            ['routeId' => 'route-root', 'actionKind' => 'controller_method', 'actionKey' => 'App\\Http\\Controllers\\RootController::show', 'matchStatus' => 'matched'],
            ['routeId' => 'route-health', 'actionKind' => 'controller_method', 'actionKey' => 'App\\Http\\Controllers\\HealthController::show', 'matchStatus' => 'matched'],
            ['routeId' => 'route-docs', 'actionKind' => 'controller_method', 'actionKey' => 'App\\Http\\Controllers\\DocsController::show', 'matchStatus' => 'matched'],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/']['get'])->toMatchArray([
        'summary' => 'Root endpoint',
        'tags' => ['System'],
    ])
        ->and($document['paths'])->not->toHaveKey('/up')
        ->and($document['paths'])->not->toHaveKey('/docs/api.json');
});

it('builds an openapi document from the merged graph', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
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
            ),
            new RouteSnapshot(
                routeId: 'route-health-show',
                methods: ['GET'],
                uri: 'health',
                domain: null,
                name: 'health.show',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\HealthController', 'show'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-1',
        'runtimeFingerprint' => 'fp-1',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 4,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 4,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['application/json', 'application/vnd.api+json'],
                    ],
                    'resources' => [
                        [
                            'class' => 'UserCollection',
                            'fqcn' => 'App\\Http\\Resources\\UserCollection',
                            'collection' => false,
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\HealthController',
                    'method' => 'show',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ],
            'resources' => [
                [
                    'fqcn' => 'App\\Http\\Resources\\UserCollection',
                    'class' => 'UserCollection',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => [
                                    'ref' => 'App\\Http\\Resources\\UserResource',
                                ],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'total' => [
                                        'type' => 'integer',
                                    ],
                                ],
                                'required' => ['total'],
                            ],
                        ],
                        'required' => ['data', 'meta'],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Resources\\UserResource',
                    'class' => 'UserResource',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                            'profile' => [
                                'ref' => 'App\\Http\\Resources\\ProfileResource',
                                'nullable' => true,
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Resources\\ProfileResource',
                    'class' => 'ProfileResource',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'bio' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['bio'],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-health-show',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\HealthController::show',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $expectedTitle = (string) config('oxcribe.openapi.info.title');

    expect($document)->toMatchArray([
        'openapi' => '3.1.0',
        'info' => [
            'title' => $expectedTitle,
            'version' => '0.1.0',
        ],
    ])
        ->and($document['paths']['/users']['get']['operationId'])->toBe('users.index_get')
        ->and($document['paths']['/users']['get']['responses'])->toHaveKey('200')
        ->and($document['paths']['/users']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/UserCollection',
        ])
        ->and($document['paths']['/users']['get']['security'])->toBe([['bearerAuth' => []]])
        ->and($document['paths']['/users']['get']['requestBody']['content'])->toHaveKeys([
            'application/json',
            'application/vnd.api+json',
        ])
        ->and($document['paths']['/users']['get']['requestBody']['content']['application/json']['schema'])->toMatchArray([
            'type' => 'object',
        ])
        ->and($document['components']['schemas']['UserResource']['properties']['profile']['anyOf'][0])->toMatchArray([
            '$ref' => '#/components/schemas/ProfileResource',
        ])
        ->and($document['components']['schemas']['UserCollection']['properties']['data']['items'])->toMatchArray([
            '$ref' => '#/components/schemas/UserResource',
        ])
        ->and($document['paths']['/health']['get'])->not->toHaveKey('security')
        ->and($document['paths']['/health']['get'])->not->toHaveKey('requestBody')
        ->and($document['components']['securitySchemes']['bearerAuth'])->toMatchArray([
            'type' => 'http',
            'scheme' => 'bearer',
        ]);
});

it('generates fallback summaries and field descriptions when code does not provide them', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-users-store',
                methods: ['POST'],
                uri: 'users',
                domain: null,
                name: null,
                prefix: 'api',
                middleware: ['api', 'auth:sanctum'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserStoreController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-generated-description',
        'runtimeFingerprint' => 'fp-generated-description',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => ['filesParsed' => 1, 'skipped' => 0, 'durationMs' => 0],
            'diagnosticCounts' => ['info' => 0, 'warn' => 0, 'error' => 0],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => ['filesParsed' => 1, 'skipped' => 0, 'durationMs' => 0],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserStoreController',
                    'method' => 'store',
                    'http' => [
                        'status' => 201,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['application/json'],
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'email',
                                'kind' => 'scalar',
                                'scalarType' => 'string',
                                'format' => 'email',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-store',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserStoreController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/users']['post'])->toMatchArray([
        'summary' => 'Create User',
    ])
        ->and($document['paths']['/users']['post']['description'])->toContain('Requires authentication.')
        ->and($document['paths']['/users']['post']['requestBody']['content']['application/json']['schema']['properties']['email']['description'])
        ->toBe('Email address.');
});

it('builds anonymous collection response envelopes from collection resource usage', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-users-search',
                methods: ['GET'],
                uri: 'users/search',
                domain: null,
                name: 'users.search',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserSearchController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-collection',
        'runtimeFingerprint' => 'fp-collection',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserSearchController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'resources' => [
                        [
                            'class' => 'UserResource',
                            'fqcn' => 'App\\Http\\Resources\\UserResource',
                            'collection' => true,
                        ],
                    ],
                ],
            ],
            'resources' => [
                [
                    'fqcn' => 'App\\Http\\Resources\\UserResource',
                    'class' => 'UserResource',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-search',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserSearchController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/users/search']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
        'type' => 'object',
        'required' => ['data'],
    ])
        ->and($document['paths']['/users/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($document['paths']['/users/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items'])->toMatchArray([
            '$ref' => '#/components/schemas/UserResource',
        ]);
});

it('preserves request field metadata in the merged controller payload', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-posts-store',
                methods: ['POST'],
                uri: 'posts',
                domain: null,
                name: 'posts.store',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\PostController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-2',
        'runtimeFingerprint' => 'fp-2',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 4,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 4,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\PostController',
                    'method' => 'store',
                    'request' => [
                        'contentTypes' => ['application/json'],
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'title',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'required' => true,
                                'optional' => false,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'query',
                                'path' => 'include',
                                'kind' => 'csv',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'allowedValues' => ['author', 'comments'],
                                'source' => 'spatie/laravel-query-builder',
                                'via' => 'allowedIncludes',
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-posts-store',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\PostController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);

    expect($graph->operations)->toHaveCount(1)
        ->and($graph->operations[0]->controller)->toMatchArray([
            'fqcn' => 'App\\Http\\Controllers\\PostController',
            'method' => 'store',
        ])
        ->and($graph->operations[0]->controller['request']['fields'])->toMatchArray([
            [
                'location' => 'body',
                'path' => 'title',
                'kind' => 'scalar',
                'type' => 'string',
                'scalarType' => 'string',
                'required' => true,
                'optional' => false,
                'source' => 'spatie/laravel-data',
                'via' => 'data',
            ],
            [
                'location' => 'query',
                'path' => 'include',
                'kind' => 'csv',
                'type' => 'string',
                'scalarType' => 'string',
                'allowedValues' => ['author', 'comments'],
                'source' => 'spatie/laravel-query-builder',
                'via' => 'allowedIncludes',
            ],
        ]);
});

it('consumes request field overlays when building openapi', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-posts-overlay',
                methods: ['POST'],
                uri: 'api/posts/overlay',
                domain: null,
                name: 'posts.overlay',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\OverlayController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-overlay',
        'runtimeFingerprint' => 'fp-overlay',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 3,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 3,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\OverlayController',
                    'method' => 'store',
                    'request' => [
                        'contentTypes' => ['application/json', 'multipart/form-data'],
                        'body' => [
                            'preview' => [],
                        ],
                        'query' => [
                            'include' => [],
                            'fields' => [],
                        ],
                        'files' => [
                            'gallery_images' => [],
                        ],
                        'fields' => [
                            [
                                'location' => 'query',
                                'path' => 'include',
                                'kind' => 'csv',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'allowedValues' => ['author.profile', 'comments.user'],
                                'source' => 'spatie/laravel-query-builder',
                                'via' => 'allowedIncludes',
                            ],
                            [
                                'location' => 'query',
                                'path' => 'fields',
                                'kind' => 'object',
                                'type' => 'object',
                                'allowedValues' => ['posts'],
                                'source' => 'spatie/laravel-query-builder',
                                'via' => 'allowedFields',
                            ],
                            [
                                'location' => 'query',
                                'path' => 'fields.posts',
                                'kind' => 'csv',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'allowedValues' => ['id', 'title'],
                                'source' => 'spatie/laravel-query-builder',
                                'via' => 'allowedFields',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'reviewers',
                                'kind' => 'collection',
                                'type' => 'array',
                                'itemType' => 'App\\Data\\ReviewerData',
                                'required' => true,
                                'optional' => false,
                                'isArray' => true,
                                'collection' => true,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'reviewers[].name',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'required' => true,
                                'optional' => false,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'reviewers[].approval',
                                'kind' => 'object',
                                'type' => 'App\\Data\\SeoData',
                                'required' => false,
                                'optional' => true,
                                'nullable' => true,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'reviewers[].approval.slug',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'required' => true,
                                'optional' => false,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'preview',
                                'kind' => 'object',
                                'type' => 'App\\Data\\SeoData',
                                'required' => false,
                                'optional' => true,
                                'nullable' => true,
                                'source' => 'spatie/laravel-data',
                                'via' => 'data',
                            ],
                            [
                                'location' => 'files',
                                'path' => 'gallery_images',
                                'kind' => 'collection',
                                'type' => 'array',
                                'itemType' => 'file',
                                'isArray' => true,
                                'collection' => true,
                                'source' => 'spatie/laravel-medialibrary',
                                'via' => 'media-library',
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-posts-overlay',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\OverlayController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    $operation = $document['paths']['/api/posts/overlay']['post'];
    $parameters = collect($operation['parameters'])->keyBy('name');

    expect($parameters['include']['x-oxcribe'])->toMatchArray([
        'allowedValues' => ['author.profile', 'comments.user'],
    ])
        ->and($parameters['fields']['x-oxcribe'])->toMatchArray([
            'allowedValues' => ['posts'],
            'allowedValuesByGroup' => [
                'posts' => ['id', 'title'],
            ],
        ])
        ->and($operation['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('reviewers')
        ->and($operation['requestBody']['content']['application/json']['schema']['required'] ?? [])->not->toContain('preview')
        ->and($operation['requestBody']['content']['application/json']['schema']['properties']['preview']['type'])->toBe(['object', 'null'])
        ->and($operation['requestBody']['content']['application/json']['schema']['properties']['reviewers'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($operation['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['required'])->toBe(['name'])
        ->and($operation['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['properties']['approval']['type'])->toBe(['object', 'null'])
        ->and($operation['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['properties']['approval']['properties']['slug']['type'])->toBe('string')
        ->and($operation['requestBody']['content']['multipart/form-data']['schema']['properties']['gallery_images'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($operation['requestBody']['content']['multipart/form-data']['schema']['properties']['gallery_images']['items'])->toMatchArray([
            'type' => 'string',
            'format' => 'binary',
        ]);
});

it('attaches generated request and response examples plus snippets to openapi operations', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-auth-login',
                methods: ['POST'],
                uri: 'login',
                domain: null,
                name: 'login.store',
                prefix: null,
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\Auth\\LoginController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-login',
        'runtimeFingerprint' => 'fp-login',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 2,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 2,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\Auth\\LoginController',
                    'method' => 'store',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['application/json'],
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'email',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'format' => 'email',
                                'required' => true,
                                'optional' => false,
                                'source' => 'validation.rule',
                                'via' => 'email',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'password',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'required' => true,
                                'optional' => false,
                                'source' => 'validation.rule',
                                'via' => 'confirmed',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'remember',
                                'kind' => 'scalar',
                                'type' => 'boolean',
                                'scalarType' => 'boolean',
                                'required' => false,
                                'optional' => true,
                                'source' => 'field_name',
                                'via' => 'remember',
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'kind' => 'json_object',
                            'status' => 200,
                            'explicit' => true,
                            'contentType' => 'application/json',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'token' => [
                                        'type' => 'string',
                                    ],
                                    'user' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                            ],
                                            'name' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'required' => ['email', 'name'],
                                    ],
                                ],
                                'required' => ['token', 'user'],
                            ],
                        ],
                    ],
                    'overrides' => [
                        'examples' => [
                            '200' => [
                                'summary' => 'Manual login response',
                                'value' => [
                                    'token' => 'manual-token',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-auth-login',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\Auth\\LoginController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    $operation = $document['paths']['/login']['post'];
    $requestExamples = $operation['requestBody']['content']['application/json']['examples'];
    $responseExamples = $operation['responses']['200']['content']['application/json']['examples'];

    expect($requestExamples)->toHaveKeys(['minimal_valid', 'happy_path', 'realistic_full'])
        ->and($requestExamples['minimal_valid']['summary'])->toBe('Minimal valid')
        ->and($requestExamples['minimal_valid']['value'])->toMatchArray([
            'email' => $operation['x-oxcribe']['examples']['minimal_valid']['context']['person']['email'],
            'password' => $operation['x-oxcribe']['examples']['minimal_valid']['context']['auth']['password'],
        ])
        ->and($requestExamples['minimal_valid']['value'])->not->toHaveKey('remember')
        ->and($requestExamples['happy_path']['value'])->toMatchArray([
            'email' => $operation['x-oxcribe']['examples']['happy_path']['context']['person']['email'],
            'password' => $operation['x-oxcribe']['examples']['happy_path']['context']['auth']['password'],
        ])
        ->and($responseExamples)->toHaveKeys(['minimal_valid', 'happy_path', 'realistic_full', 'default'])
        ->and($responseExamples['happy_path']['value'])->toMatchArray([
            'token' => $operation['x-oxcribe']['examples']['happy_path']['context']['auth']['token'],
            'user' => [
                'email' => $operation['x-oxcribe']['examples']['happy_path']['context']['person']['email'],
                'name' => $operation['x-oxcribe']['examples']['happy_path']['context']['person']['fullName'],
            ],
        ])
        ->and($responseExamples['default'])->toMatchArray([
            'summary' => 'Manual login response',
            'value' => [
                'token' => 'manual-token',
            ],
        ])
        ->and($operation['x-oxcribe']['examples']['happy_path']['request']['body'])->toMatchArray([
            'email' => $operation['x-oxcribe']['examples']['happy_path']['context']['person']['email'],
            'password' => $operation['x-oxcribe']['examples']['happy_path']['context']['auth']['password'],
        ])
        ->and($operation['x-oxcribe']['snippets']['happy_path'])->toMatchArray([
            'curl' => $operation['x-oxcribe']['snippets']['happy_path']['curl'],
            'fetch' => $operation['x-oxcribe']['snippets']['happy_path']['fetch'],
            'axios' => $operation['x-oxcribe']['snippets']['happy_path']['axios'],
        ])
        ->and($operation['x-oxcribe']['snippets']['happy_path']['curl'])->toContain('/login')
        ->and($operation['x-oxcribe']['snippets']['happy_path']['curl'])->toContain('"email"')
        ->and($operation['x-oxcribe']['snippets']['happy_path']['fetch'])->toContain('fetch(')
        ->and($operation['x-oxcribe']['snippets']['happy_path']['axios'])->toContain('axios(');
});

it('emits additive named scenarios for collection-heavy operations', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-orders-store',
                methods: ['POST'],
                uri: 'orders',
                domain: null,
                name: 'orders.store',
                prefix: null,
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\OrderController', 'store'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-orders',
        'runtimeFingerprint' => 'fp-orders',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 2,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 2,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\OrderController',
                    'method' => 'store',
                    'http' => [
                        'status' => 201,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['application/json'],
                        'fields' => [
                            [
                                'location' => 'body',
                                'path' => 'items',
                                'kind' => 'array',
                                'type' => 'array',
                                'required' => true,
                                'optional' => false,
                                'source' => 'validation.rule',
                                'via' => 'array',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'items.*.sku',
                                'kind' => 'scalar',
                                'type' => 'string',
                                'scalarType' => 'string',
                                'required' => true,
                                'optional' => false,
                                'source' => 'validation.rule',
                                'via' => 'string',
                            ],
                            [
                                'location' => 'body',
                                'path' => 'items.*.quantity',
                                'kind' => 'scalar',
                                'type' => 'integer',
                                'scalarType' => 'integer',
                                'required' => true,
                                'optional' => false,
                                'source' => 'validation.rule',
                                'via' => 'integer',
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'kind' => 'json_object',
                            'status' => 201,
                            'explicit' => true,
                            'contentType' => 'application/json',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'items' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'sku' => ['type' => 'string'],
                                                    ],
                                                    'required' => ['sku'],
                                                ],
                                            ],
                                        ],
                                        'required' => ['items'],
                                    ],
                                ],
                                'required' => ['data'],
                            ],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-orders-store',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\OrderController::store',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    $scenarios = $document['paths']['/orders']['post']['x-oxcribe']['scenarios'];

    expect($scenarios)->toHaveKey('happy_path')
        ->and($scenarios['happy_path'])->toHaveKeys(['single_item', 'multiple_items'])
        ->and($scenarios['happy_path']['single_item'])->toMatchArray([
            'key' => 'single_item',
            'label' => 'Single item',
        ])
        ->and($scenarios['happy_path']['single_item']['request']['body']['items'])->toHaveCount(1)
        ->and($scenarios['happy_path']['multiple_items']['request']['body']['items'])->toHaveCount(3);
});

it('hardens openapi output with route filters, path parameters, query parameters, and multipart bodies', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-users-show',
                methods: ['POST'],
                uri: 'api/users/{user}/posts/{post}',
                domain: null,
                name: 'users.posts.store',
                prefix: 'api',
                middleware: ['api', 'auth.basic'],
                where: [
                    'user' => '[0-9]+',
                    'post' => '[A-Za-z0-9\\-]+',
                ],
                defaults: [
                    'post' => 'draft',
                ],
                bindings: [
                    new RouteBinding('user', 'implicit_model', 'App\\Models\\User', true),
                    new RouteBinding('post', 'implicit_model', 'App\\Models\\Post', true),
                ],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserPostController', 'store'),
            ),
            new RouteSnapshot(
                routeId: 'route-boost-browser-logs',
                methods: ['POST'],
                uri: '_boost/browser-logs',
                domain: null,
                name: 'boost.logs',
                prefix: '_boost',
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('closure'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-2',
        'runtimeFingerprint' => 'fp-2',
        'status' => 'partial',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => true,
            'stats' => [
                'filesParsed' => 6,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 1,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 6,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserPostController',
                    'method' => 'store',
                    'http' => [
                        'status' => 201,
                        'explicit' => true,
                    ],
                    'request' => [
                        'contentTypes' => ['multipart/form-data'],
                        'body' => [
                            'title' => [],
                            'filters' => [
                                'state' => [],
                            ],
                        ],
                        'query' => [
                            'include' => [],
                            'filters' => [
                                'state' => [],
                            ],
                        ],
                        'files' => [
                            'avatar' => [],
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-show',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserPostController::store',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-boost-browser-logs',
                'actionKind' => 'closure',
                'matchStatus' => 'runtime_only',
                'reasonCode' => 'closure_action',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths'])->toHaveKey('/api/users/{user}/posts/{post}')
        ->and($document['paths'])->not->toHaveKey('/_boost/browser-logs')
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['security'])->toBe([['basicAuth' => []]])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'])->toHaveCount(4)
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][0])->toMatchArray([
            'name' => 'user',
            'in' => 'path',
            'required' => true,
            'schema' => [
                'type' => 'integer',
            ],
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][0]['x-oxcribe']['binding'])->toMatchArray([
            'parameter' => 'user',
            'kind' => 'implicit_model',
            'targetFqcn' => 'App\\Models\\User',
            'isImplicit' => true,
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][1])->toMatchArray([
            'name' => 'post',
            'in' => 'path',
            'required' => true,
            'schema' => [
                'type' => 'string',
                'pattern' => '[A-Za-z0-9\\-]+',
            ],
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][1]['x-oxcribe']['default'])->toBe('draft')
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][2])->toMatchArray([
            'name' => 'include',
            'in' => 'query',
            'required' => false,
            'schema' => [
                'type' => 'string',
            ],
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['parameters'][3])->toMatchArray([
            'name' => 'filters',
            'in' => 'query',
            'required' => false,
            'style' => 'deepObject',
            'explode' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'state' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['requestBody']['content']['multipart/form-data']['schema']['type'])->toBe('object')
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['title']['description'])->toBe('String value for title.')
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['filters'])->toMatchArray([
            'type' => 'object',
            'description' => 'Object payload for filters.',
            'properties' => [
                'state' => [
                    'description' => 'String value for state.',
                ],
            ],
        ])
        ->and($document['paths']['/api/users/{user}/posts/{post}']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['avatar'])->toMatchArray([
            'type' => 'string',
            'format' => 'binary',
            'description' => 'String value for avatar.',
        ])
        ->and($document['components']['securitySchemes'])->toHaveKey('basicAuth')
        ->and($document['x-oxcribe']['operationCount'])->toBe(1);
});

it('records spatie authorization middleware in the operation metadata', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-admin-users',
                methods: ['GET'],
                uri: 'admin/users',
                domain: null,
                name: 'admin.users',
                prefix: 'api',
                middleware: ['api', 'auth:sanctum', 'role:admin,web', 'permission:manage users'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\AdminUserController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-3',
        'runtimeFingerprint' => 'fp-3',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 2,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 2,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\AdminUserController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-admin-users',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\AdminUserController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/admin/users']['get']['x-oxcribe']['authorization'])->toMatchArray([
        [
            'kind' => 'role',
            'values' => ['admin'],
            'guard' => 'web',
            'guards' => ['web'],
            'schemeCandidates' => ['cookieAuth'],
            'source' => 'role:admin,web',
            'subject' => null,
            'ability' => null,
            'resolution' => 'guard',
        ],
        [
            'kind' => 'permission',
            'values' => ['manage users'],
            'guard' => null,
            'guards' => [],
            'schemeCandidates' => ['bearerAuth'],
            'source' => 'permission:manage users',
            'subject' => null,
            'ability' => null,
            'resolution' => 'inferred',
        ],
    ])
        ->and($document['paths']['/admin/users']['get']['security'])->toBe([
            ['bearerAuth' => []],
            ['cookieAuth' => []],
        ]);
});

it('maps guard-specific schemes and ability scopes into openapi security requirements', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-reports-index',
                methods: ['GET'],
                uri: 'reports',
                domain: null,
                name: 'reports.index',
                prefix: 'api',
                middleware: ['api', 'auth:web', 'abilities:reports.read,reports.export'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\ReportController', 'index'),
            ),
            new RouteSnapshot(
                routeId: 'route-admin-settings',
                methods: ['GET'],
                uri: 'admin/settings',
                domain: null,
                name: 'admin.settings',
                prefix: 'api',
                middleware: ['api', 'role:admin,web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\AdminSettingsController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-security-scopes',
        'runtimeFingerprint' => 'fp-security-scopes',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 2,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 2,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\ReportController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\AdminSettingsController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-reports-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\ReportController::index',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-admin-settings',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\AdminSettingsController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $config = config('oxcribe.openapi');
    $config['security']['middleware']['auth:web'] = ['oauth2'];
    $config['security']['guard_schemes'] = [
        'web' => ['cookieAuth'],
    ];
    $config['security']['scope_scheme'] = 'oauth2';
    $config['security']['schemes']['oauth2'] = [
        'type' => 'oauth2',
        'flows' => [
            'clientCredentials' => [
                'tokenUrl' => 'https://example.test/oauth/token',
                'scopes' => [
                    'reports.export' => 'Export reports',
                    'reports.read' => 'Read reports',
                ],
            ],
        ],
    ];

    $document = app(OpenApiDocumentFactory::class)->make($graph, $config);

    $effectiveProfile = AuthProfile::fromMiddleware(
        array_merge(
            $graph->operations[0]->authProfile()->authenticationMiddleware(),
            $graph->operations[0]->authProfile()->authorizationMiddleware(),
        ),
        [
            'auth' => (array) config('oxcribe.auth', []),
            'openapi' => [
                'security' => array_replace_recursive((array) config('oxcribe.openapi.security', []), $config['security']),
            ],
        ],
    );

    expect($effectiveProfile->securityRequirements())->toBe([
        ['oauth2' => []],
    ])
        ->and($document['paths']['/reports']['get']['security'])->toBe([
            [
                'oauth2' => ['reports.export', 'reports.read'],
            ],
        ])
        ->and($document['paths']['/admin/settings']['get']['security'])->toBe([
            [
                'cookieAuth' => [],
            ],
        ])
        ->and($document['components']['securitySchemes'])->toHaveKeys(['oauth2', 'cookieAuth']);

});

it('consumes controller response overlays when building openapi responses', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-stats-show',
                methods: ['GET'],
                uri: 'stats',
                domain: null,
                name: 'stats.show',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\StatsController', 'show'),
            ),
            new RouteSnapshot(
                routeId: 'route-jobs-store',
                methods: ['POST'],
                uri: 'jobs',
                domain: null,
                name: 'jobs.store',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\JobController', 'store'),
            ),
            new RouteSnapshot(
                routeId: 'route-sessions-destroy',
                methods: ['DELETE'],
                uri: 'sessions/{session}',
                domain: null,
                name: 'sessions.destroy',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\SessionController', 'destroy'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-response-v2',
        'runtimeFingerprint' => 'fp-response-v2',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 3,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 3,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\StatsController',
                    'method' => 'show',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'json_object',
                            'status' => 200,
                            'explicit' => true,
                            'contentType' => 'application/json',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'count' => [
                                        'type' => 'integer',
                                    ],
                                    'user' => [
                                        'ref' => 'App\\Http\\Resources\\UserResource',
                                    ],
                                ],
                                'required' => ['count', 'user'],
                            ],
                            'source' => 'response()->json',
                            'via' => 'response()->json',
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\JobController',
                    'method' => 'store',
                    'http' => [
                        'status' => 202,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'json_array',
                            'status' => 202,
                            'explicit' => true,
                            'contentType' => 'application/json',
                            'bodySchema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'integer',
                                ],
                            ],
                            'source' => 'response()',
                            'via' => 'response()',
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\SessionController',
                    'method' => 'destroy',
                    'http' => [
                        'status' => 204,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'no_content',
                            'status' => 204,
                            'explicit' => true,
                            'source' => 'response()->noContent',
                            'via' => 'response()->noContent',
                        ],
                    ],
                ],
            ],
            'resources' => [
                [
                    'fqcn' => 'App\\Http\\Resources\\UserResource',
                    'class' => 'UserResource',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-stats-show',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\StatsController::show',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-jobs-store',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\JobController::store',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-sessions-destroy',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\SessionController::destroy',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/stats']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
        'type' => 'object',
        'required' => ['count', 'user'],
    ])
        ->and($document['paths']['/stats']['get']['responses']['200']['content']['application/json']['schema']['properties']['count'])->toMatchArray([
            'type' => 'integer',
        ])
        ->and($document['paths']['/stats']['get']['responses']['200']['content']['application/json']['schema']['properties']['user'])->toMatchArray([
            '$ref' => '#/components/schemas/UserResource',
        ])
        ->and($document['paths']['/stats']['get']['responses']['200']['x-oxcribe']['response'])->toMatchArray([
            'kind' => 'json_object',
            'explicit' => true,
            'source' => 'response()->json',
            'via' => 'response()->json',
        ])
        ->and($document['paths']['/jobs']['post']['responses']['202'])->toMatchArray([
            'description' => 'Accepted',
        ])
        ->and($document['paths']['/jobs']['post']['responses']['202']['content']['application/json']['schema'])->toMatchArray([
            'type' => 'array',
            'items' => [
                'type' => 'integer',
                'description' => 'Integer value for items.',
            ],
        ])
        ->and($document['paths']['/sessions/{session}']['delete']['responses']['204'])->toMatchArray([
            'description' => 'No content',
        ])
        ->and($document['paths']['/sessions/{session}']['delete']['responses']['204'])->not->toHaveKey('content');
});

it('emits runtime auth metadata alongside security requirements', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-secure-reports',
                methods: ['GET'],
                uri: 'secure/reports',
                domain: null,
                name: 'secure.reports.index',
                prefix: 'api',
                middleware: ['auth:sanctum', 'verified', 'password.confirm', 'signed:relative', 'throttle:60,1,uploads'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\SecureReportController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-auth-runtime',
        'runtimeFingerprint' => 'fp-auth-runtime',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\SecureReportController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
            ],
            'models' => [],
            'resources' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-secure-reports',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\SecureReportController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));
    $auth = $document['paths']['/secure/reports']['get']['x-oxcribe']['auth'];

    expect($document['paths']['/secure/reports']['get']['security'])->toBe([
        ['bearerAuth' => []],
    ])
        ->and($auth['requiresAuthentication'])->toBeTrue()
        ->and($auth['requiresVerifiedUser'])->toBeTrue()
        ->and($auth['requiresPasswordConfirmation'])->toBeTrue()
        ->and($auth['requiresSignedUrls'])->toBeTrue()
        ->and($auth['schemeCandidates'])->toBe(['bearerAuth'])
        ->and($auth['runtimeConstraints'])->toBe([
            [
                'kind' => 'verified',
                'values' => [],
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'verified',
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'password_confirm',
                'values' => [],
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'password.confirm',
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'signed',
                'values' => ['relative'],
                'guards' => [],
                'schemeCandidates' => [],
                'source' => 'signed:relative',
                'resolution' => 'direct',
                'metadata' => [
                    'mode' => 'relative',
                ],
            ],
            [
                'kind' => 'throttle',
                'values' => ['60', '1', 'uploads'],
                'guards' => [],
                'schemeCandidates' => [],
                'source' => 'throttle:60,1,uploads',
                'resolution' => 'direct',
                'metadata' => [
                    'maxAttempts' => 60,
                    'decayMinutes' => 1,
                    'prefix' => 'uploads',
                ],
            ],
        ]);
});

it('emits static authorization hints without changing security requirements', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-posts-show',
                methods: ['GET'],
                uri: 'posts/{post}',
                domain: null,
                name: 'posts.show',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\PostController', 'show'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-auth-static',
        'runtimeFingerprint' => 'fp-auth-static',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\PostController',
                    'method' => 'show',
                    'authorization' => [
                        [
                            'kind' => 'authorize',
                            'ability' => 'view',
                            'targetKind' => 'route_parameter',
                            'target' => 'App\\Models\\Post',
                            'parameter' => 'post',
                            'source' => '$this->authorize',
                            'resolution' => 'parameter',
                            'enforcesFailureResponse' => true,
                        ],
                    ],
                ],
            ],
            'models' => [],
            'resources' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-posts-show',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\PostController::show',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/posts/{post}']['get']['x-oxcribe']['authorizationStatic'])->toBe([
        [
            'kind' => 'authorize',
            'ability' => 'view',
            'targetKind' => 'route_parameter',
            'target' => 'App\\Models\\Post',
            'parameter' => 'post',
            'source' => '$this->authorize',
            'resolution' => 'parameter',
            'enforcesFailureResponse' => true,
        ],
    ])
        ->and($document['paths']['/posts/{post}']['get'])->not->toHaveKey('security')
        ->and($document['paths']['/posts/{post}']['get']['x-oxcribe'])->not->toHaveKey('auth');
});

it('builds non-json response overlays without falling back to resource schemas', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-login-redirect',
                methods: ['POST'],
                uri: 'login',
                domain: null,
                name: 'login.store',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\LoginController', 'store'),
            ),
            new RouteSnapshot(
                routeId: 'route-reports-download',
                methods: ['GET'],
                uri: 'reports/export',
                domain: null,
                name: 'reports.export',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\ReportController', 'download'),
            ),
            new RouteSnapshot(
                routeId: 'route-feed-stream',
                methods: ['GET'],
                uri: 'feed/stream',
                domain: null,
                name: 'feed.stream',
                prefix: 'api',
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\FeedController', 'stream'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-response-non-json',
        'runtimeFingerprint' => 'fp-response-non-json',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 3,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 3,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\LoginController',
                    'method' => 'store',
                    'http' => [
                        'status' => 302,
                        'explicit' => true,
                    ],
                    'resources' => [
                        [
                            'class' => 'UserResource',
                            'fqcn' => 'App\\Http\\Resources\\UserResource',
                            'collection' => false,
                        ],
                    ],
                    'responses' => [
                        [
                            'kind' => 'redirect',
                            'status' => 302,
                            'explicit' => true,
                            'headers' => [
                                'Location' => '/dashboard',
                            ],
                            'redirect' => [
                                'targetKind' => 'url',
                                'target' => '/dashboard',
                            ],
                            'source' => 'redirect()',
                            'via' => 'redirect()',
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\ReportController',
                    'method' => 'download',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'download',
                            'status' => 200,
                            'explicit' => false,
                            'contentType' => 'text/csv',
                            'headers' => [
                                'Content-Disposition' => 'attachment; filename=\"report.csv\"',
                            ],
                            'download' => [
                                'disposition' => 'attachment',
                                'filename' => 'report.csv',
                            ],
                            'source' => 'response()->download',
                            'via' => 'response()->download',
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\FeedController',
                    'method' => 'stream',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'stream',
                            'status' => 200,
                            'explicit' => true,
                            'contentType' => 'text/plain',
                            'headers' => [
                                'X-Accel-Buffering' => 'no',
                            ],
                            'source' => 'response()->stream',
                            'via' => 'response()->stream',
                        ],
                    ],
                ],
            ],
            'resources' => [
                [
                    'fqcn' => 'App\\Http\\Resources\\UserResource',
                    'class' => 'UserResource',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-login-redirect',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\LoginController::store',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-reports-download',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\ReportController::download',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-feed-stream',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\FeedController::stream',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/login']['post']['responses']['302'])->toMatchArray([
        'description' => 'Successful response',
        'headers' => [
            'Location' => [
                'schema' => [
                    'type' => 'string',
                ],
                'example' => '/dashboard',
            ],
        ],
    ])
        ->and($document['paths']['/login']['post']['responses']['302']['x-oxcribe']['response']['redirect'])->toMatchArray([
            'targetKind' => 'url',
            'target' => '/dashboard',
        ])
        ->and($document['paths']['/login']['post']['responses']['302'])->not->toHaveKey('content')
        ->and($document['paths']['/reports/export']['get']['responses']['200']['content']['text/csv']['schema'])->toMatchArray([
            'type' => 'string',
            'format' => 'binary',
        ])
        ->and($document['paths']['/reports/export']['get']['responses']['200']['x-oxcribe']['response']['download'])->toMatchArray([
            'disposition' => 'attachment',
            'filename' => 'report.csv',
        ])
        ->and($document['paths']['/reports/export']['get']['responses']['200']['headers']['Content-Disposition']['example'])->toContain('report.csv')
        ->and($document['paths']['/feed/stream']['get']['responses']['200']['content']['text/plain']['schema'])->toMatchArray([
            'type' => 'string',
        ])
        ->and($document['paths']['/feed/stream']['get']['responses']['200']['headers']['X-Accel-Buffering']['example'])->toBe('no');
});

it('builds inertia response overlays with html transport and inertia metadata', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
                routeId: 'route-dashboard-show',
                methods: ['GET'],
                uri: 'dashboard',
                domain: null,
                name: 'dashboard.show',
                prefix: 'web',
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\DashboardController', 'show'),
            ),
            new RouteSnapshot(
                routeId: 'route-team-switch',
                methods: ['POST'],
                uri: 'teams/switch',
                domain: null,
                name: 'teams.switch',
                prefix: 'web',
                middleware: ['web'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\TeamController', 'switch'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-response-inertia',
        'runtimeFingerprint' => 'fp-response-inertia',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 2,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 2,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\DashboardController',
                    'method' => 'show',
                    'responses' => [
                        [
                            'kind' => 'inertia',
                            'status' => 200,
                            'explicit' => false,
                            'contentType' => 'text/html',
                            'inertia' => [
                                'component' => 'Dashboard/Index',
                                'propsSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'stats' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'count' => [
                                                    'type' => 'integer',
                                                ],
                                            ],
                                            'required' => ['count'],
                                        ],
                                    ],
                                    'required' => ['stats'],
                                ],
                            ],
                            'source' => 'Inertia::render',
                            'via' => 'Inertia::render',
                        ],
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\TeamController',
                    'method' => 'switch',
                    'responses' => [
                        [
                            'kind' => 'redirect',
                            'status' => 409,
                            'explicit' => true,
                            'headers' => [
                                'X-Inertia-Location' => '/teams/current',
                            ],
                            'redirect' => [
                                'targetKind' => 'inertia_location',
                                'target' => '/teams/current',
                            ],
                            'source' => 'Inertia::location',
                            'via' => 'Inertia::location',
                        ],
                    ],
                ],
            ],
            'resources' => [],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-dashboard-show',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\DashboardController::show',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-team-switch',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\TeamController::switch',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/dashboard']['get']['responses']['200']['content']['text/html']['schema'])->toMatchArray([
        'type' => 'string',
    ])
        ->and($document['paths']['/dashboard']['get']['responses']['200']['x-oxcribe']['inertia']['component'])->toBe('Dashboard/Index')
        ->and($document['paths']['/dashboard']['get']['responses']['200']['x-oxcribe']['inertia']['propsSchema'])->toMatchArray([
            'type' => 'object',
            'required' => ['stats'],
        ])
        ->and($document['paths']['/dashboard']['get']['responses']['200']['x-oxcribe']['inertia']['propsSchema']['properties']['stats']['properties']['count'])->toMatchArray([
            'type' => 'integer',
        ])
        ->and($document['paths']['/teams/switch']['post']['responses']['409'])->not->toHaveKey('content')
        ->and($document['paths']['/teams/switch']['post']['responses']['409']['headers']['X-Inertia-Location']['example'])->toBe('/teams/current')
        ->and($document['paths']['/teams/switch']['post']['responses']['409']['x-oxcribe']['response']['redirect'])->toMatchArray([
            'targetKind' => 'inertia_location',
            'target' => '/teams/current',
        ]);
});

it('applies controller overrides to the final openapi operation', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '12.0.0',
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [
            new RouteSnapshot(
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
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-overrides-openapi',
        'runtimeFingerprint' => 'fp-overrides-openapi',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 1,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 1,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [
                [
                    'fqcn' => 'App\\Http\\Controllers\\UserController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                    'responses' => [
                        [
                            'kind' => 'json_object',
                            'status' => 200,
                            'explicit' => true,
                            'contentType' => 'application/json',
                            'bodySchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'required' => ['data'],
                            ],
                        ],
                    ],
                    'overrides' => [
                        'summary' => 'List active users',
                        'description' => 'Administrative list of active users.',
                        'operationId' => 'users.list',
                        'tags' => ['Users', 'Overrides'],
                        'deprecated' => true,
                        'security' => [
                            ['cookieAuth' => []],
                        ],
                        'examples' => [
                            '200' => [
                                'summary' => 'Users example',
                                'value' => [
                                    'data' => ['alice'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Users payload from override',
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
                            'description' => 'User listing docs',
                        ],
                        'extensions' => [
                            'x-internal' => [
                                'owner' => 'platform',
                            ],
                        ],
                        'matchedSources' => [
                            'config:overrides.defaults',
                            '/tmp/oxcribe.overrides.php#routes[0]',
                        ],
                    ],
                ],
            ],
            'models' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [
            [
                'routeId' => 'route-users-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $graph = app(OperationGraphMerger::class)->merge($runtime, $response);
    $document = app(OpenApiDocumentFactory::class)->make($graph, config('oxcribe.openapi'));

    expect($document['paths']['/users']['get'])->toMatchArray([
        'summary' => 'List active users',
        'description' => 'Administrative list of active users.',
        'operationId' => 'users.list',
        'tags' => ['Users', 'Overrides'],
        'deprecated' => true,
        'security' => [
            ['cookieAuth' => []],
        ],
        'externalDocs' => [
            'url' => 'https://example.test/docs/users',
            'description' => 'User listing docs',
        ],
        'x-internal' => [
            'owner' => 'platform',
        ],
    ])
        ->and($document['paths']['/users']['get']['requestBody'])->toMatchArray([
            'required' => false,
        ])
        ->and($document['paths']['/users']['get']['responses']['200']['description'])->toBe('Users payload from override')
        ->and($document['paths']['/users']['get']['responses']['200']['content']['application/json']['examples']['default'])->toMatchArray([
            'summary' => 'Users example',
            'value' => [
                'data' => ['alice'],
            ],
        ])
        ->and($document['paths']['/users']['get']['x-oxcribe']['product'])->toBe([
            'surface' => 'admin',
        ])
        ->and($document['paths']['/users']['get']['x-oxcribe']['overrides']['matchedSources'])->toBe([
            'config:overrides.defaults',
            '/tmp/oxcribe.overrides.php#routes[0]',
        ])
        ->and($document['components']['securitySchemes'])->toMatchArray([
            'cookieAuth' => [
                'type' => 'apiKey',
                'in' => 'cookie',
                'name' => 'laravel_session',
            ],
        ]);
});
