<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
use Oxhq\Oxcribe\Support\PackageVersion;

beforeEach(function (): void {
    config()->set('app.name', 'Oxcloud Test App');
    config()->set('app.url', 'https://app.example.test');
});

it('fails when oxcloud publish config is missing', function () {
    config()->set('oxcribe.publish.base_url', null);
    config()->set('oxcribe.publish.token', null);

    $this->artisan('oxcribe:publish')
        ->expectsOutput('Missing OXCLOUD_BASE_URL / oxcribe.publish.base_url.')
        ->assertFailed();
});

it('publishes openapi and docs payload to oxcloud', function () {
    config()->set('oxcribe.publish.base_url', 'https://oxcloud.example.test');
    config()->set('oxcribe.publish.token', 'test-token');
    config()->set('oxcribe.publish.timeout', 15);
    config()->set('app.version', '2026.03.25');

    stubOxcribeAnalyzeDependencies();

    Http::fake([
        'https://oxcloud.example.test/api/publish/v1' => Http::response([
            'version' => '2026.03.25',
            'projectUrl' => 'https://oxcloud.example.test/docs/acme/platform',
            'versionUrl' => 'https://oxcloud.example.test/docs/acme/platform/2026.03.25',
        ]),
    ]);

    $this->artisan('oxcribe:publish')
        ->expectsOutput('Published 2026.03.25 to Oxcribe Cloud.')
        ->expectsOutput('Version URL: https://oxcloud.example.test/docs/acme/platform/2026.03.25')
        ->expectsOutput('Explorer URL: https://oxcloud.example.test/docs/acme/platform/2026.03.25/explorer')
        ->expectsOutput('Changelog URL: https://oxcloud.example.test/docs/acme/platform/2026.03.25/changelog')
        ->expectsOutput('Project URL: https://oxcloud.example.test/docs/acme/platform')
        ->expectsOutput('Next: open the version URL and review docs, explorer, and changelog.')
        ->expectsOutput('Then: decide whether to keep it public, require review, or share it by link/domain.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $payload = decodePublishRequestPayload($request);

        return $request->url() === 'https://oxcloud.example.test/api/publish/v1'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Content-Encoding', 'gzip')
            && ($payload['contractVersion'] ?? null) === 'oxcloud.publish.v1'
            && ($payload['version'] ?? null) === '2026.03.25'
            && ($payload['source']['appName'] ?? null) === 'Oxcloud Test App'
            && ($payload['source']['appUrl'] ?? null) === 'https://app.example.test'
            && ($payload['source']['framework'] ?? null) === 'laravel'
            && ($payload['source']['packageVersion'] ?? null) === PackageVersion::label()
            && ($payload['openapi']['openapi'] ?? null) === '3.1.0'
            && ($payload['docsPayload']['contractVersion'] ?? null) === 'oxcribe.docs.v1';
    });
});

it('resolves publish version precedence from option, config, app version, then dev', function () {
    config()->set('oxcribe.publish.base_url', 'https://oxcloud.example.test');
    config()->set('oxcribe.publish.token', 'test-token');
    config()->set('oxcribe.publish.default_version', 'from-config');
    config()->set('app.version', 'from-app');

    stubOxcribeAnalyzeDependencies();

    Http::fake(['*' => Http::response(['versionUrl' => 'https://oxcloud.example.test/docs/acme/platform/from-option'])]);

    $this->artisan('oxcribe:publish', ['--publish-version' => 'from-option'])->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => (decodePublishRequestPayload($request)['version'] ?? null) === 'from-option');

    Http::fake(['*' => Http::response(['versionUrl' => 'https://oxcloud.example.test/docs/acme/platform/from-config'])]);
    $this->artisan('oxcribe:publish')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => (decodePublishRequestPayload($request)['version'] ?? null) === 'from-config');

    config()->set('oxcribe.publish.default_version', null);
    Http::fake(['*' => Http::response(['versionUrl' => 'https://oxcloud.example.test/docs/acme/platform/from-app'])]);
    $this->artisan('oxcribe:publish')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => (decodePublishRequestPayload($request)['version'] ?? null) === 'from-app');

    config()->set('app.version', null);
    Http::fake(['*' => Http::response(['versionUrl' => 'https://oxcloud.example.test/docs/acme/platform/dev'])]);
    $this->artisan('oxcribe:publish')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => (decodePublishRequestPayload($request)['version'] ?? null) === 'dev');
});

/**
 * @return array<string, mixed>
 */
function decodePublishRequestPayload(Request $request): array
{
    if ($request->hasHeader('Content-Encoding', 'gzip')) {
        $decoded = gzdecode($request->body());

        return is_string($decoded)
            ? (json_decode($decoded, true) ?: [])
            : [];
    }

    return $request->data();
}

function stubOxcribeAnalyzeDependencies(): void
{
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: '13.0.0',
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
        'requestId' => 'req-publish',
        'runtimeFingerprint' => 'fp-publish',
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
}
