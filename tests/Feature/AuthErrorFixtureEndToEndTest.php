<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use Oxhq\Oxcribe\Tests\Support\FixtureRuntimeSnapshotFactory;

it('runs an end-to-end auth and framework error fixture through analyze and export-openapi', function () {
    $fixtureRoot = realpath(__DIR__.'/../Fixtures/AuthErrorLaravelApp');
    expect($fixtureRoot)->not->toBeFalse();

    configureFixtureOxinfer($fixtureRoot);

    app()->instance(RuntimeSnapshotFactory::class, new FixtureRuntimeSnapshotFactory(
        app: app(),
        router: app('router'),
        routeSnapshotExtractor: app(RouteSnapshotExtractor::class),
        packageInventoryDetector: app(PackageInventoryDetector::class),
        fixtureRoot: $fixtureRoot,
        routeNamePrefix: 'auth-error-fixture.',
        routeFile: 'routes/api.php',
        routeGroupMiddleware: ['api'],
        routeGroupPrefix: '',
    ));
    app()->forgetInstance(OxcribeManager::class);

    $analysisPath = tempnam(sys_get_temp_dir(), 'oxcribe-auth-error-analysis-');
    $openapiPath = tempnam(sys_get_temp_dir(), 'oxcribe-auth-error-openapi-');

    expect($analysisPath)->not->toBeFalse()
        ->and($openapiPath)->not->toBeFalse();

    $this->artisan('oxcribe:analyze', ['--write' => $analysisPath, '--pretty' => true])->assertExitCode(0);
    $this->artisan('oxcribe:export-openapi', ['--write' => $openapiPath, '--pretty' => true])->assertExitCode(0);

    $analysis = json_decode((string) file_get_contents($analysisPath), true, 512, JSON_THROW_ON_ERROR);
    $document = json_decode((string) file_get_contents($openapiPath), true, 512, JSON_THROW_ON_ERROR);

    expect($analysis['status'])->toBe('ok')
        ->and($analysis['routeMatches'])->toHaveCount(3)
        ->and($analysis['delta']['controllers'])->toHaveCount(3)
        ->and($analysis['delta']['resources'])->toHaveCount(1);

    $controllers = collect($analysis['delta']['controllers'])->keyBy(
        static fn (array $controller): string => sprintf('%s::%s', $controller['fqcn'], $controller['method'])
    );

    expect($controllers)->toHaveKeys([
        'App\\Http\\Controllers\\SecureReportController::index',
        'App\\Http\\Controllers\\SecureReportController::errors',
        'App\\Http\\Controllers\\SecureReportController::additionalResource',
    ]);

    $errorResponses = collect($controllers['App\\Http\\Controllers\\SecureReportController::errors']['responses'])
        ->keyBy(static fn (array $response): string => (string) $response['status']);

    expect($errorResponses)->toHaveKeys(['200', '400', '403', '404', '422'])
        ->and($errorResponses['200'])->toMatchArray([
            'kind' => 'json_object',
            'source' => 'response()->json',
        ])
        ->and($errorResponses['400'])->toMatchArray([
            'kind' => 'json_object',
            'source' => 'abort()',
        ])
        ->and($errorResponses['403'])->toMatchArray([
            'kind' => 'json_object',
            'source' => 'throw new AuthorizationException',
        ])
        ->and($errorResponses['404'])->toMatchArray([
            'kind' => 'json_object',
            'source' => 'throw new ModelNotFoundException',
        ])
        ->and($errorResponses['422'])->toMatchArray([
            'kind' => 'json_object',
            'source' => 'ValidationException::withMessages',
        ]);

    $additionalBodySchema = $controllers['App\\Http\\Controllers\\SecureReportController::additionalResource']['responses'][0]['bodySchema'];

    expect($additionalBodySchema['type'])->toBe('object')
        ->and($additionalBodySchema['properties'])->toHaveKeys(['id', 'title', 'meta', 'links'])
        ->and($additionalBodySchema['properties']['meta']['type'])->toBe('object')
        ->and($additionalBodySchema['properties']['links']['type'])->toBe('object');

    expect($document['paths']['/secure/reports']['get']['security'])->toBe([
        ['bearerAuth' => []],
    ])
        ->and($document['paths']['/secure/reports']['get']['x-oxcribe']['auth'])->toMatchArray([
            'requiresAuthentication' => true,
            'requiresAuthorization' => true,
            'requiresVerifiedUser' => true,
            'requiresPasswordConfirmation' => true,
            'requiresSignedUrls' => true,
            'schemeCandidates' => ['bearerAuth'],
        ])
        ->and($document['paths']['/secure/reports']['get']['x-oxcribe']['auth']['throttleConstraints'])->toBe([
            [
                'source' => 'throttle:60,1,uploads',
                'values' => ['60', '1', 'uploads'],
                'metadata' => [
                    'maxAttempts' => 60,
                    'decayMinutes' => 1,
                    'prefix' => 'uploads',
                ],
            ],
        ]);

    expect($document['paths']['/secure/reports/errors']['get']['responses'])->toHaveKeys(['200', '400', '403', '404', '422'])
        ->and($document['paths']['/secure/reports/errors']['get']['responses']['422']['content']['application/json']['schema']['properties']['errors']['properties'])->toHaveKeys(['email', 'name']);

    $additionalSchema = $document['paths']['/secure/reports/additional']['get']['responses']['200']['content']['application/json']['schema'];

    expect($additionalSchema['type'])->toBe('object')
        ->and($additionalSchema['properties'])->toHaveKeys(['id', 'title', 'meta', 'links'])
        ->and($additionalSchema['properties']['links']['properties']['self'])->toMatchArray([
            'type' => 'string',
            'format' => 'uri',
        ]);
});
