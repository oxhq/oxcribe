<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\PackageInventorySnapshot;
use Oxhq\Oxcribe\Data\PackageSnapshot;
use Oxhq\Oxcribe\Data\SpatiePackageSnapshot;

it('builds a runtime snapshot with application metadata and routes', function () {
    app()->instance(PackageInventoryDetector::class, new class implements PackageInventoryDetector
    {
        public function detect(string $projectRoot): PackageInventorySnapshot
        {
            return new PackageInventorySnapshot(
                spatie: new SpatiePackageSnapshot(
                    laravelData: PackageSnapshot::installed('spatie/laravel-data', '4.0.0', 'composer.lock'),
                    laravelQueryBuilder: PackageSnapshot::missing('spatie/laravel-query-builder'),
                    laravelPermission: PackageSnapshot::missing('spatie/laravel-permission'),
                    laravelMedialibrary: PackageSnapshot::missing('spatie/laravel-medialibrary'),
                    laravelTranslatable: PackageSnapshot::missing('spatie/laravel-translatable'),
                ),
            );
        }
    });

    Route::get('/oxcribe/runtime', static fn () => 'ok')
        ->name('oxcribe.runtime')
        ->middleware(['api']);

    $snapshot = app(RuntimeSnapshotFactory::class)->make();

    expect($snapshot->app->basePath)->toBe(base_path())
        ->and($snapshot->app->laravelVersion)->toBe(app()->version())
        ->and($snapshot->app->phpVersion)->toBe(PHP_VERSION)
        ->and($snapshot->routes)->not->toBeEmpty();

    $runtimeRoute = collect($snapshot->routes)->first(
        fn ($route) => $route->name === 'oxcribe.runtime'
    );

    expect($runtimeRoute)->not->toBeNull()
        ->and($runtimeRoute->uri)->toBe('oxcribe/runtime')
        ->and($runtimeRoute->methods)->toContain('GET')
        ->and($runtimeRoute->middleware)->toContain('api')
        ->and($snapshot->packages->spatie->laravelData->installed)->toBeTrue()
        ->and($snapshot->packages->spatie->laravelData->version)->toBe('4.0.0');
});
