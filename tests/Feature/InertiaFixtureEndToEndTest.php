<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use Oxhq\Oxcribe\Tests\Support\FixtureRuntimeSnapshotFactory;

it('runs an end-to-end inertia fixture through analyze and export-openapi', function () {
    $fixtureRoot = realpath(__DIR__.'/../Fixtures/InertiaLaravelApp');
    expect($fixtureRoot)->not->toBeFalse();

    configureFixtureOxinfer($fixtureRoot);

    app()->instance(RuntimeSnapshotFactory::class, new FixtureRuntimeSnapshotFactory(
        app: app(),
        router: app('router'),
        routeSnapshotExtractor: app(RouteSnapshotExtractor::class),
        packageInventoryDetector: app(PackageInventoryDetector::class),
        fixtureRoot: $fixtureRoot,
        routeNamePrefix: 'inertia-fixture.',
        routeFile: 'routes/web.php',
        routeGroupMiddleware: ['web'],
        routeGroupPrefix: '',
    ));
    app()->forgetInstance(OxcribeManager::class);

    $analysisPath = tempnam(sys_get_temp_dir(), 'oxcribe-inertia-analysis-');
    $openapiPath = tempnam(sys_get_temp_dir(), 'oxcribe-inertia-openapi-');

    expect($analysisPath)->not->toBeFalse()
        ->and($openapiPath)->not->toBeFalse();

    $this->artisan('oxcribe:analyze', ['--write' => $analysisPath, '--pretty' => true])->assertExitCode(0);
    $this->artisan('oxcribe:export-openapi', ['--write' => $openapiPath, '--pretty' => true])->assertExitCode(0);

    $analysis = json_decode((string) file_get_contents($analysisPath), true, 512, JSON_THROW_ON_ERROR);
    $document = json_decode((string) file_get_contents($openapiPath), true, 512, JSON_THROW_ON_ERROR);

    expect($analysis['status'])->toBe('ok')
        ->and($analysis['routeMatches'])->toHaveCount(3)
        ->and($analysis['delta']['controllers'])->toHaveCount(3);

    $controllers = collect($analysis['delta']['controllers'])->keyBy(
        static fn (array $controller): string => sprintf('%s::%s', $controller['fqcn'], $controller['method'])
    );

    expect($controllers)->toHaveKeys([
        'App\\Http\\Controllers\\DashboardController::__invoke',
        'App\\Http\\Controllers\\TeamsPageController::show',
        'App\\Http\\Controllers\\TeamsController::store',
    ])
        ->and($controllers['App\\Http\\Controllers\\DashboardController::__invoke']['responses'][0]['kind'])->toBe('inertia')
        ->and($controllers['App\\Http\\Controllers\\DashboardController::__invoke']['responses'][0]['inertia']['component'])->toBe('Dashboard/Index')
        ->and($controllers['App\\Http\\Controllers\\DashboardController::__invoke']['responses'][0]['inertia']['propsSchema']['properties']['stats']['properties']['count']['type'])->toBe('integer')
        ->and($controllers['App\\Http\\Controllers\\TeamsPageController::show']['responses'][0]['kind'])->toBe('inertia')
        ->and($controllers['App\\Http\\Controllers\\TeamsPageController::show']['responses'][0]['inertia']['component'])->toBe('Teams/Show')
        ->and($controllers['App\\Http\\Controllers\\TeamsController::store']['responses'][0]['kind'])->toBe('redirect')
        ->and($controllers['App\\Http\\Controllers\\TeamsController::store']['responses'][0]['redirect'])->toMatchArray([
            'targetKind' => 'inertia_location',
            'target' => '/teams/current',
        ]);

    expect($document['paths']['/dashboard']['get']['responses']['200']['content']['text/html']['schema'])->toMatchArray([
        'type' => 'string',
    ])
        ->and($document['paths']['/dashboard']['get']['responses']['200']['x-oxcribe']['inertia']['component'])->toBe('Dashboard/Index')
        ->and($document['paths']['/dashboard']['get']['responses']['200']['x-oxcribe']['inertia']['propsSchema']['properties']['stats']['properties']['count']['type'])->toBe('integer')
        ->and($document['paths']['/teams/show']['get']['responses']['200']['content']['text/html']['schema'])->toMatchArray([
            'type' => 'string',
        ])
        ->and($document['paths']['/teams/show']['get']['responses']['200']['x-oxcribe']['inertia']['component'])->toBe('Teams/Show')
        ->and($document['paths']['/teams/switch']['post']['responses']['409'])->not->toHaveKey('content')
        ->and($document['paths']['/teams/switch']['post']['responses']['409']['headers']['X-Inertia-Location']['example'])->toBe('/teams/current')
        ->and($document['paths']['/teams/switch']['post']['responses']['409']['x-oxcribe']['response']['redirect'])->toMatchArray([
            'targetKind' => 'inertia_location',
            'target' => '/teams/current',
        ]);
});
