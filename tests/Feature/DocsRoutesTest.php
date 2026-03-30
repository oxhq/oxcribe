<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Bridge\AnalysisRequestFactory;
use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\AnalysisResponse;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\ManifestFactory;

it('serves the local docs page, openapi document, and docs payload from the package routes', function () {
    $expectedTitle = (string) config('oxcribe.openapi.info.title');

    config()->set('oxcribe.docs.enabled', true);
    config()->set('oxcribe.docs.route', 'oxcribe/docs');
    config()->set('oxcribe.docs.openapi_route', 'oxcribe/openapi.json');
    config()->set('oxcribe.docs.payload_route', 'oxcribe/docs/payload.json');

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
                middleware: ['api'],
                where: [],
                defaults: [],
                bindings: [],
                action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserController', 'index'),
            ),
        ],
    );

    $response = AnalysisResponse::fromArray([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-docs',
        'runtimeFingerprint' => 'fp-docs',
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

    app()->instance(RuntimeSnapshotFactory::class, new class($runtime) implements RuntimeSnapshotFactory
    {
        public function __construct(private readonly RuntimeSnapshot $runtime) {}

        public function make(): RuntimeSnapshot
        {
            return $this->runtime;
        }
    });

    app()->instance(OxinferClient::class, new class($response) implements OxinferClient
    {
        public function __construct(private readonly AnalysisResponse $response) {}

        public function analyze(AnalysisRequest $request): AnalysisResponse
        {
            return $this->response;
        }
    });

    app()->instance(AnalysisRequestFactory::class, new AnalysisRequestFactory(
        manifestFactory: app(ManifestFactory::class),
        config: config('oxcribe'),
    ));

    $this->get('/oxcribe/docs')
        ->assertOk()
        ->assertSee('id="oxcribe-docs-app"', false)
        ->assertSee('/oxcribe/openapi.json', false)
        ->assertSee('/oxcribe/docs/payload.json', false)
        ->assertSee('/oxcribe/assets/docs-viewer.css', false)
        ->assertSee('/oxcribe/assets/docs-viewer.js', false)
        ->assertDontSee('https://unpkg.com/vue@3/dist/vue.global.prod.js', false);

    $this->get('/oxcribe/assets/docs-viewer.css')
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');

    $this->get('/oxcribe/assets/docs-viewer.js')
        ->assertOk()
        ->assertHeader('content-type', 'application/javascript; charset=UTF-8')
        ->assertSee('Oxcribe Local Viewer');

    $this->get('/oxcribe/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('paths./users.get.operationId', 'users.index_get')
        ->assertJsonPath('x-oxcribe.operationCount', 1);

    $this->get('/oxcribe/docs/payload.json')
        ->assertOk()
        ->assertJsonPath('contractVersion', 'oxcribe.docs.v1')
        ->assertJsonPath('info.title', $expectedTitle)
        ->assertJsonPath('operations.0.path', '/users')
        ->assertJsonPath('operations.0.method', 'GET')
        ->assertJsonPath('meta.operationCount', 1)
        ->assertJsonPath('meta.viewer', 'universal');
});
