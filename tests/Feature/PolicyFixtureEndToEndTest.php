<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use Oxhq\Oxcribe\Tests\Support\FixtureRuntimeSnapshotFactory;

it('runs an end-to-end policy fixture through analyze and export-openapi', function () {
    $fixtureRoot = realpath(__DIR__.'/../Fixtures/PolicyLaravelApp');
    expect($fixtureRoot)->not->toBeFalse();

    configureFixtureOxinfer($fixtureRoot);

    app()->instance(RuntimeSnapshotFactory::class, new FixtureRuntimeSnapshotFactory(
        app: app(),
        router: app('router'),
        routeSnapshotExtractor: app(RouteSnapshotExtractor::class),
        packageInventoryDetector: app(PackageInventoryDetector::class),
        fixtureRoot: $fixtureRoot,
        routeNamePrefix: 'policy-fixture.',
        routeGroupPrefix: '',
    ));
    app()->forgetInstance(OxcribeManager::class);

    $analysisPath = tempnam(sys_get_temp_dir(), 'oxcribe-policy-analysis-');
    $openapiPath = tempnam(sys_get_temp_dir(), 'oxcribe-policy-openapi-');

    expect($analysisPath)->not->toBeFalse()
        ->and($openapiPath)->not->toBeFalse();

    $this->artisan('oxcribe:analyze', ['--write' => $analysisPath, '--pretty' => true])->assertExitCode(0);
    $this->artisan('oxcribe:export-openapi', ['--write' => $openapiPath, '--pretty' => true])->assertExitCode(0);

    $analysis = json_decode((string) file_get_contents($analysisPath), true, 512, JSON_THROW_ON_ERROR);
    $document = json_decode((string) file_get_contents($openapiPath), true, 512, JSON_THROW_ON_ERROR);

    expect($analysis['status'])->toBe('ok')
        ->and($analysis['routeMatches'])->toHaveCount(2)
        ->and($analysis['delta']['controllers'])->toHaveCount(2);

    $controllers = collect($analysis['delta']['controllers'])->keyBy(
        static fn (array $controller): string => sprintf('%s::%s', $controller['fqcn'], $controller['method'])
    );

    expect($controllers)->toHaveKeys([
        'App\\Http\\Controllers\\PostPolicyController::show',
        'App\\Http\\Controllers\\PostPolicyController::preview',
    ]);

    $show = $controllers['App\\Http\\Controllers\\PostPolicyController::show'];
    $showSources = collect($show['authorization'] ?? [])->pluck('source')->values()->all();
    $showStatuses = collect($show['responses'] ?? [])->pluck('status')->map(static fn (mixed $status): int => (int) $status)->all();

    expect($showSources)->toEqualCanonicalizing([
        '$this->authorize',
        '$this->authorizeResource',
        'FormRequest::authorize',
        'Gate::allows',
        'Gate::authorize',
    ])
        ->and($showStatuses)->toContain(200, 403, 404, 409, 422);

    $preview = $controllers['App\\Http\\Controllers\\PostPolicyController::preview'];

    expect($preview['authorization'])->toHaveCount(1)
        ->and($preview['authorization'][0])->toMatchArray([
            'kind' => 'allows',
            'source' => 'Gate::allows',
            'enforcesFailureResponse' => false,
        ])
        ->and(collect($preview['responses'])->pluck('status')->map(static fn (mixed $status): int => (int) $status)->all())->toBe([200]);

    expect($document['paths']['/policy/posts/{policyPost}']['get']['security'])->toBe([
        ['bearerAuth' => []],
    ])
        ->and($document['paths']['/policy/posts/{policyPost}']['get']['x-oxcribe']['authorizationStatic'])->toHaveCount(5)
        ->and(collect($document['paths']['/policy/posts/{policyPost}']['get']['x-oxcribe']['authorizationStatic'])->pluck('source')->values()->all())->toEqualCanonicalizing([
            '$this->authorize',
            '$this->authorizeResource',
            'FormRequest::authorize',
            'Gate::allows',
            'Gate::authorize',
        ])
        ->and($document['paths']['/policy/posts/{policyPost}']['get']['responses'])->toHaveKeys(['200', '403', '404', '409', '422']);

    expect($document['paths']['/policy/posts/{policyPost}/preview']['get']['security'])->toBe([
        ['bearerAuth' => []],
    ])
        ->and($document['paths']['/policy/posts/{policyPost}/preview']['get']['x-oxcribe']['authorizationStatic'])->toHaveCount(1)
        ->and($document['paths']['/policy/posts/{policyPost}/preview']['get']['responses'])->toHaveKeys(['200'])
        ->and($document['paths']['/policy/posts/{policyPost}/preview']['get']['responses'])->not->toHaveKey('403');
});
