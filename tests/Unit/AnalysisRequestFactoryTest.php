<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Bridge\AnalysisRequestFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\PackageInventorySnapshot;
use Oxhq\Oxcribe\Data\PackageSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Data\SpatiePackageSnapshot;

it('embeds package inventory facts in the analysis request payload', function () {
    $runtime = new RuntimeSnapshot(
        app: new AppSnapshot(
            basePath: base_path(),
            laravelVersion: app()->version(),
            phpVersion: PHP_VERSION,
            appEnv: 'testing',
        ),
        routes: [],
        packages: new PackageInventorySnapshot(
            spatie: new SpatiePackageSnapshot(
                laravelData: PackageSnapshot::installed('spatie/laravel-data', '4.0.0', 'composer.lock'),
                laravelQueryBuilder: PackageSnapshot::missing('spatie/laravel-query-builder'),
                laravelPermission: PackageSnapshot::missing('spatie/laravel-permission'),
                laravelMedialibrary: PackageSnapshot::missing('spatie/laravel-medialibrary'),
                laravelTranslatable: PackageSnapshot::missing('spatie/laravel-translatable'),
            ),
        ),
    );

    $request = app(AnalysisRequestFactory::class)->make($runtime);
    $payload = $request->toArray();
    $wirePayload = $request->toWireArray();
    $wireJsonPayload = json_decode($request->toWireJson(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['runtime']['packages'])->toMatchArray([
        'spatie' => [
            'laravelData' => [
                'name' => 'spatie/laravel-data',
                'installed' => true,
                'version' => '4.0.0',
                'constraint' => null,
                'source' => 'composer.lock',
                'dev' => false,
            ],
            'laravelQueryBuilder' => [
                'name' => 'spatie/laravel-query-builder',
                'installed' => false,
                'version' => null,
                'constraint' => null,
                'source' => null,
                'dev' => false,
            ],
            'laravelPermission' => [
                'name' => 'spatie/laravel-permission',
                'installed' => false,
                'version' => null,
                'constraint' => null,
                'source' => null,
                'dev' => false,
            ],
            'laravelMedialibrary' => [
                'name' => 'spatie/laravel-medialibrary',
                'installed' => false,
                'version' => null,
                'constraint' => null,
                'source' => null,
                'dev' => false,
            ],
            'laravelTranslatable' => [
                'name' => 'spatie/laravel-translatable',
                'installed' => false,
                'version' => null,
                'constraint' => null,
                'source' => null,
                'dev' => false,
            ],
        ],
    ])
        ->and($wirePayload['runtime']['packages'])->toBe([
            [
                'name' => 'spatie/laravel-data',
                'version' => '4.0.0',
            ],
        ])
        ->and($wireJsonPayload['runtime']['packages'])->toBe([
            [
                'name' => 'spatie/laravel-data',
                'version' => '4.0.0',
            ],
        ]);
});
