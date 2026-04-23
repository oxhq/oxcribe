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
use Oxhq\Oxcribe\Docs\DocsPayloadFactory;
use Oxhq\Oxcribe\Merge\OperationGraphMerger;
use Oxhq\Oxcribe\OpenApi\OpenApiDocumentFactory;
use Oxhq\Oxcribe\Overrides\OverrideApplier;
use Oxhq\Oxcribe\Overrides\OverrideLoader;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\ManifestFactory;

it('applies route overrides before exporting the graph', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-manager-'.bin2hex(random_bytes(4));
    mkdir($projectRoot, 0777, true);

    file_put_contents($projectRoot.'/oxcribe.overrides.php', <<<'PHP'
<?php

return [
    'defaults' => [
        'tags' => ['Config'],
    ],
    'routes' => [
        [
            'match' => [
                'routeId' => 'route-hidden',
            ],
            'include' => false,
        ],
        [
            'match' => [
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
            ],
            'summary' => 'List users',
            'tags' => ['Users'],
        ],
    ],
];
PHP);

    config()->set('oxcribe.overrides.enabled', true);
    config()->set('oxcribe.overrides.files', ['oxcribe.overrides.php']);
    config()->set('oxcribe.overrides.defaults', [
        'tags' => ['Global'],
    ]);
    config()->set('oxcribe.overrides.routes', []);

    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: $projectRoot,
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
                    'fqcn' => 'App\\Http\\Controllers\\UserController',
                    'method' => 'index',
                    'http' => [
                        'status' => 200,
                        'explicit' => true,
                    ],
                ],
                [
                    'fqcn' => 'App\\Http\\Controllers\\HiddenController',
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
                'routeId' => 'route-users-index',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
                'matchStatus' => 'matched',
            ],
            [
                'routeId' => 'route-hidden',
                'actionKind' => 'controller_method',
                'actionKey' => 'App\\Http\\Controllers\\HiddenController::index',
                'matchStatus' => 'matched',
            ],
        ],
        'diagnostics' => [],
    ]);

    $runtimeSnapshotFactory = new class($runtime) implements RuntimeSnapshotFactory
    {
        public function __construct(private readonly RuntimeSnapshot $runtime) {}

        public function make(): RuntimeSnapshot
        {
            return $this->runtime;
        }
    };

    $oxinferClient = new class($response) implements OxinferClient
    {
        public function __construct(private readonly AnalysisResponse $response) {}

        public function analyze(AnalysisRequest $request): AnalysisResponse
        {
            return $this->response;
        }
    };

    $manager = new OxcribeManager(
        runtimeSnapshotFactory: $runtimeSnapshotFactory,
        analysisRequestFactory: new AnalysisRequestFactory(
            manifestFactory: app(ManifestFactory::class),
            config: config('oxcribe'),
        ),
        oxinferClient: $oxinferClient,
        operationGraphMerger: app(OperationGraphMerger::class),
        overrideLoader: app(OverrideLoader::class),
        overrideApplier: app(OverrideApplier::class),
        openApiDocumentFactory: app(OpenApiDocumentFactory::class),
        docsPayloadFactory: app(DocsPayloadFactory::class),
        config: config('oxcribe'),
    );

    $graph = $manager->graph($projectRoot);

    expect($graph->operations)->toHaveCount(1)
        ->and($graph->operations[0]->routeId)->toBe('route-users-index')
        ->and($graph->operations[0]->controller['overrides'])->toMatchArray([
            'summary' => 'List users',
            'tags' => ['Global', 'Config', 'Users'],
            'matchedSources' => [
                'config:overrides.defaults',
                str_replace('\\', '/', $projectRoot.'/oxcribe.overrides.php').'#defaults',
                str_replace('\\', '/', $projectRoot.'/oxcribe.overrides.php').'#routes[1]',
            ],
        ]);
});
