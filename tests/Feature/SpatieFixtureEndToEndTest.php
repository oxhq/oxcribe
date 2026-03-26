<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use Oxhq\Oxcribe\Tests\Support\FixtureRuntimeSnapshotFactory;

it('runs an end-to-end spatie fixture through analyze and export-openapi', function () {
    $fixtureRoot = realpath(__DIR__.'/../Fixtures/SpatieLaravelApp');
    expect($fixtureRoot)->not->toBeFalse();

    configureFixtureOxinfer($fixtureRoot);

    app()->instance(RuntimeSnapshotFactory::class, new FixtureRuntimeSnapshotFactory(
        app: app(),
        router: app('router'),
        routeSnapshotExtractor: app(RouteSnapshotExtractor::class),
        packageInventoryDetector: app(PackageInventoryDetector::class),
        fixtureRoot: $fixtureRoot,
        routeNamePrefix: 'spatie-fixture.',
    ));
    app()->forgetInstance(OxcribeManager::class);

    $analysisPath = tempnam(sys_get_temp_dir(), 'oxcribe-analysis-');
    $openapiPath = tempnam(sys_get_temp_dir(), 'oxcribe-openapi-');

    expect($analysisPath)->not->toBeFalse()
        ->and($openapiPath)->not->toBeFalse();

    $this->artisan('oxcribe:analyze', ['--write' => $analysisPath, '--pretty' => true])->assertExitCode(0);
    $this->artisan('oxcribe:export-openapi', ['--write' => $openapiPath, '--pretty' => true])->assertExitCode(0);

    $analysis = json_decode((string) file_get_contents($analysisPath), true, 512, JSON_THROW_ON_ERROR);
    $document = json_decode((string) file_get_contents($openapiPath), true, 512, JSON_THROW_ON_ERROR);

    expect($analysis['status'])->toBe('ok')
        ->and($analysis['routeMatches'])->toHaveCount(12)
        ->and($analysis['delta']['controllers'])->toHaveCount(12)
        ->and($analysis['delta']['resources'])->toHaveCount(6);

    $controllers = collect($analysis['delta']['controllers'])->keyBy(
        static fn (array $controller): string => sprintf('%s::%s', $controller['fqcn'], $controller['method'])
    );

    expect($controllers)->toHaveKeys([
        'App\\Http\\Controllers\\PostController::index',
        'App\\Http\\Controllers\\PostController::store',
        'App\\Http\\Controllers\\PostController::show',
        'App\\Http\\Controllers\\SearchController::index',
        'App\\Http\\Controllers\\PublishController::store',
        'App\\Http\\Controllers\\AdvancedPublishController::__invoke',
        'App\\Http\\Controllers\\AdvancedSearchController::index',
        'App\\Http\\Controllers\\MediaController::store',
        'App\\Http\\Controllers\\MediaController::gallery',
        'App\\Http\\Controllers\\MediaAttachmentsController::store',
        'App\\Http\\Controllers\\PageController::update',
        'App\\Http\\Controllers\\SeriesController::update',
    ]);

    expect($controllers['App\\Http\\Controllers\\SearchController::index']['request']['query'])->toMatchArray([
        'fields' => [
            'authors' => [
                'email' => [],
                'name' => [],
            ],
            'posts' => [
                'id' => [],
                'status' => [],
                'summary' => [],
                'title' => [],
            ],
        ],
        'filter' => [
            'author' => [],
            'published' => [],
            'status' => [],
            'trashed' => [],
        ],
        'include' => [
            'author' => [
                'profile' => [],
            ],
            'comments' => [
                'user' => [],
            ],
            'tags' => [],
        ],
        'sort' => [
            'published_at' => [],
            'status' => [],
            'title' => [],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\PostController::index']['request']['query'])->toMatchArray([
        'fields' => [
            'posts' => [
                'summary' => [],
                'title' => [],
            ],
        ],
        'filter' => [
            'status' => [],
            'trashed' => [],
        ],
        'include' => [
            'author' => [],
        ],
        'sort' => [
            'published_at' => [],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\PostController::store']['request']['body'])->toMatchArray([
        'title' => [],
        'summary' => [],
        'seo' => [
            'slug' => [],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\PublishController::store']['request']['body'])->toMatchArray([
        'seo' => [
            'slug' => [],
        ],
        'reviewer' => [
            'name' => [],
            'approval' => [
                'slug' => [],
            ],
        ],
        'notes' => [],
    ]);

    expect($controllers['App\\Http\\Controllers\\AdvancedPublishController::__invoke']['request']['body'])->toMatchArray([
        'title' => [],
        'summary' => [],
        'seo' => [
            'slug' => [],
        ],
        'featured' => [],
        'approvalHistory' => [
            '_item' => [
                'name' => [],
                'approval' => [
                    'slug' => [],
                ],
            ],
        ],
        'preview' => [
            'slug' => [],
        ],
        'reviewers' => [
            '_item' => [
                'name' => [],
                'approval' => [
                    'slug' => [],
                ],
            ],
        ],
        'teaser' => [
            'slug' => [],
        ],
        'reviewer' => [
            'name' => [],
            'approval' => [
                'slug' => [],
            ],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\AdvancedSearchController::index']['request']['query'])->toMatchArray([
        'fields' => [
            'authors' => [
                'email' => [],
                'name' => [],
            ],
            'media' => [
                'name' => [],
            ],
            'posts' => [
                'id' => [],
                'status' => [],
                'summary' => [],
                'title' => [],
            ],
        ],
        'filter' => [
            'ownedBy' => [],
            'published_after' => [],
            'state' => [],
            'tagged' => [],
            'trashed' => [],
        ],
        'include' => [
            'author' => [
                'profile' => [],
            ],
            'comments' => [
                'user' => [],
            ],
            'tags' => [
                'media' => [],
            ],
        ],
        'sort' => [
            'published_at' => [],
            'status' => [],
            'title' => [],
            'updated_at' => [],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\MediaController::store']['request']['contentTypes'])->toBe([
        'multipart/form-data',
    ])
        ->and($controllers['App\\Http\\Controllers\\MediaController::store']['request']['files'])->toMatchArray([
            'avatar' => [],
            'cover' => [],
            'gallery' => [],
        ])
        ->and($controllers['App\\Http\\Controllers\\MediaController::gallery']['request']['contentTypes'])->toBe([
            'multipart/form-data',
        ])
        ->and($controllers['App\\Http\\Controllers\\MediaController::gallery']['request']['files'])->toMatchArray([
            'attachments' => [],
            'hero_image' => [],
        ]);

    expect($controllers['App\\Http\\Controllers\\MediaAttachmentsController::store']['request']['contentTypes'])->toBe([
        'multipart/form-data',
    ])
        ->and($controllers['App\\Http\\Controllers\\MediaAttachmentsController::store']['request']['files'])->toMatchArray([
            'attachments' => [],
            'gallery_images' => [
                '_item' => [],
            ],
            'preview_pdf' => [],
            'thumbnail' => [],
        ]);

    expect($controllers['App\\Http\\Controllers\\PageController::update']['request']['body'])->toMatchArray([
        'title' => [],
        'seo' => [
            'slug' => [],
        ],
    ]);

    expect($controllers['App\\Http\\Controllers\\SeriesController::update']['request']['body'])->toMatchArray([
        'title' => [],
        'subtitle' => [],
        'seo' => [
            'slug' => [],
        ],
    ]);

    $advancedPublishFields = collect($controllers['App\\Http\\Controllers\\AdvancedPublishController::__invoke']['request']['fields'] ?? [])
        ->keyBy(static fn (array $field): string => sprintf('%s:%s', $field['location'], $field['path']));
    $advancedSearchFields = collect($controllers['App\\Http\\Controllers\\AdvancedSearchController::index']['request']['fields'] ?? [])
        ->keyBy(static fn (array $field): string => sprintf('%s:%s', $field['location'], $field['path']));
    $mediaAttachmentFields = collect($controllers['App\\Http\\Controllers\\MediaAttachmentsController::store']['request']['fields'] ?? [])
        ->keyBy(static fn (array $field): string => sprintf('%s:%s', $field['location'], $field['path']));

    expect($advancedPublishFields['body:preview'])->toMatchArray([
        'kind' => 'object',
        'type' => 'App\\Data\\SeoData',
        'required' => false,
        'optional' => true,
        'source' => 'spatie/laravel-data',
    ])
        ->and($advancedPublishFields['body:preview']['wrappers'])->toContain('Optional')
        ->and($advancedPublishFields['body:teaser'])->toMatchArray([
            'kind' => 'object',
            'type' => 'App\\Data\\SeoData',
            'optional' => true,
            'source' => 'spatie/laravel-data',
        ])
        ->and($advancedPublishFields['body:teaser']['wrappers'])->toContain('Lazy')
        ->and($advancedPublishFields['body:reviewers'])->toMatchArray([
            'kind' => 'collection',
            'type' => 'array',
            'itemType' => 'App\\Data\\ReviewerData',
            'isArray' => true,
            'collection' => true,
        ])
        ->and($advancedPublishFields)->toHaveKey('body:reviewers[].approval.slug')
        ->and($advancedSearchFields['query:include'])->toMatchArray([
            'kind' => 'csv',
            'type' => 'string',
            'scalarType' => 'string',
            'allowedValues' => ['author.profile', 'comments.user', 'tags.media'],
            'source' => 'spatie/laravel-query-builder',
        ])
        ->and($advancedSearchFields['query:fields.posts'])->toMatchArray([
            'kind' => 'csv',
            'allowedValues' => ['id', 'status', 'summary', 'title'],
            'source' => 'spatie/laravel-query-builder',
        ])
        ->and($mediaAttachmentFields['files:gallery_images'])->toMatchArray([
            'kind' => 'collection',
            'type' => 'array',
            'itemType' => 'file',
            'isArray' => true,
            'collection' => true,
            'source' => 'spatie/laravel-medialibrary',
        ]);

    $models = collect($analysis['delta']['models'])->keyBy('fqcn');
    $resources = collect($analysis['delta']['resources'])->keyBy('fqcn');

    expect($resources)->toHaveKeys([
        'App\\Http\\Resources\\PageResource',
        'App\\Http\\Resources\\PostCollection',
        'App\\Http\\Resources\\PostResource',
        'App\\Http\\Resources\\SeoResource',
        'App\\Http\\Resources\\SeriesResource',
        'App\\Http\\Resources\\TagResource',
    ])
        ->and($resources['App\\Http\\Resources\\PostResource']['schema'])->toMatchArray([
            'type' => 'object',
        ])
        ->and($resources['App\\Http\\Resources\\PostResource']['schema']['properties']['seo'])->toMatchArray([
            'ref' => 'App\\Http\\Resources\\SeoResource',
            'nullable' => true,
        ])
        ->and($resources['App\\Http\\Resources\\PostResource']['schema']['properties']['tags'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($resources['App\\Http\\Resources\\PostCollection']['schema']['properties']['data'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($resources['App\\Http\\Resources\\PostCollection']['schema']['properties']['data']['items'])->toMatchArray([
            'ref' => 'App\\Http\\Resources\\PostResource',
        ]);

    expect($models['App\\Models\\Post']['attributes'])->toContain(
        ['name' => 'title', 'via' => 'spatie/laravel-translatable'],
        ['name' => 'summary', 'via' => 'spatie/laravel-translatable'],
    )
        ->and($models['App\\Models\\Page']['attributes'])->toContain(
            ['name' => 'title', 'via' => 'spatie/laravel-translatable'],
        )
        ->and($models['App\\Models\\Series']['attributes'])->toContain(
            ['name' => 'title', 'via' => 'spatie/laravel-translatable'],
            ['name' => 'subtitle', 'via' => 'spatie/laravel-translatable'],
            ['name' => 'description', 'via' => 'spatie/laravel-translatable'],
        );

    expect($document['paths'])->toHaveKeys([
        '/api/posts/search',
        '/api/posts/{post}',
        '/api/posts/{post}/publish',
        '/api/posts/{post}/publish-advanced',
        '/api/posts/advanced-search',
        '/api/media',
        '/api/media/attachments',
        '/api/media/gallery',
        '/api/pages/{page}',
        '/api/series/{series}',
    ]);

    expect($document['components']['schemas'])->toHaveKeys([
        'PageResource',
        'PostCollection',
        'PostResource',
        'SeoResource',
        'SeriesResource',
        'TagResource',
    ])
        ->and($document['components']['schemas']['PostResource']['properties']['seo']['anyOf'][0])->toMatchArray([
            '$ref' => '#/components/schemas/SeoResource',
        ])
        ->and($document['components']['schemas']['PostResource']['properties']['tags']['items'])->toMatchArray([
            '$ref' => '#/components/schemas/TagResource',
        ])
        ->and($document['components']['schemas']['PostCollection']['properties']['data']['items'])->toMatchArray([
            '$ref' => '#/components/schemas/PostResource',
        ]);

    expect($document['paths']['/api/posts/search']['get']['x-oxcribe']['authorization'])->toBe([
        [
            'kind' => 'role',
            'values' => ['editor', 'writer'],
            'guard' => 'api',
            'guards' => ['api'],
            'schemeCandidates' => ['bearerAuth'],
            'source' => 'role:editor|writer,api',
            'subject' => null,
            'ability' => null,
            'resolution' => 'guard',
        ],
    ])
        ->and($document['paths']['/api/posts/search']['get']['parameters'])->toHaveCount(4)
        ->and($document['paths']['/api/posts/search']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            'type' => 'object',
            'required' => ['data'],
        ])
        ->and($document['paths']['/api/posts/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items'])->toMatchArray([
            '$ref' => '#/components/schemas/PostResource',
        ]);

    $searchParameters = collect($document['paths']['/api/posts/search']['get']['parameters'])->keyBy('name');
    $advancedSearchParameters = collect($document['paths']['/api/posts/advanced-search']['get']['parameters'])->keyBy('name');

    expect($searchParameters['filter'])->toMatchArray([
        'name' => 'filter',
        'in' => 'query',
        'required' => false,
        'style' => 'deepObject',
        'explode' => true,
    ])
        ->and($searchParameters['fields'])->toMatchArray([
            'name' => 'fields',
            'in' => 'query',
            'required' => false,
            'style' => 'deepObject',
            'explode' => true,
        ])
        ->and($searchParameters['include'])->toMatchArray([
            'name' => 'include',
            'in' => 'query',
            'required' => false,
        ])
        ->and($searchParameters['include']['x-oxcribe'])->toMatchArray([
            'allowedValues' => ['author.profile', 'comments.user', 'tags'],
        ])
        ->and($searchParameters['sort'])->toMatchArray([
            'name' => 'sort',
            'in' => 'query',
            'required' => false,
        ])
        ->and($searchParameters['sort']['x-oxcribe'])->toMatchArray([
            'allowedValues' => ['published_at', 'status', 'title'],
            'supportsDescending' => true,
        ])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'permission',
                'values' => ['posts.publish'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'permission:posts.publish',
                'subject' => null,
                'ability' => null,
                'resolution' => 'inferred',
            ],
        ])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['responses'])->toHaveKey('202')
        ->and($document['paths']['/api/posts/{post}/publish']['post']['responses']['202']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/PostResource',
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'role_or_permission',
                'values' => ['publisher', 'posts.publish'],
                'guard' => 'api',
                'guards' => ['api'],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'role_or_permission:publisher|posts.publish,api',
                'subject' => null,
                'ability' => null,
                'resolution' => 'guard',
            ],
        ])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['parameters'][0]['x-oxcribe']['binding'])->toMatchArray([
            'parameter' => 'post',
            'kind' => 'implicit_model',
            'targetFqcn' => 'App\\Models\\Post',
            'isImplicit' => true,
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['parameters'][0]['x-oxcribe']['binding'])->toMatchArray([
            'parameter' => 'post',
            'kind' => 'implicit_model',
            'targetFqcn' => 'App\\Models\\Post',
            'isImplicit' => true,
        ])
        ->and($document['paths']['/api/posts']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/PostCollection',
        ])
        ->and($document['paths']['/api/posts/{post}']['get']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/PostResource',
        ])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['requestBody']['content']['application/json']['schema']['properties'])->toHaveKeys([
            'seo',
            'reviewer',
            'notes',
        ])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['seo'])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewer']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/posts/{post}/publish']['post']['requestBody']['content']['application/json']['schema']['properties']['notes']['type'])->toBe(['string', 'null'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties'])->toHaveKeys([
            'title',
            'summary',
            'seo',
            'featured',
            'preview',
            'teaser',
            'reviewers',
            'approvalHistory',
            'reviewer',
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewers'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('title')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('summary')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('seo')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('featured')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('reviewers')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toContain('approvalHistory')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['properties'])->toHaveKeys([
            'name',
            'approval',
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['required'] ?? [])->toBe(['name'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['properties']['approval']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewers']['items']['properties']['approval']['properties']['slug']['type'])->toBe('string')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['approvalHistory'])->toHaveKey('items')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['approvalHistory']['items']['properties'])->toHaveKeys([
            'name',
            'approval',
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['approvalHistory']['items']['required'] ?? [])->toBe(['name'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['approvalHistory']['items']['properties']['approval']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['reviewer']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['preview']['type'])->toBe('object')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['properties']['teaser']['properties'])->toHaveKey('slug')
        ->and($document['paths']['/api/posts/advanced-search']['get']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'role_or_permission',
                'values' => ['editor', 'writer'],
                'guard' => 'api',
                'guards' => ['api'],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'role_or_permission:editor|writer,api',
                'subject' => null,
                'ability' => null,
                'resolution' => 'guard',
            ],
        ])
        ->and($document['paths']['/api/posts/advanced-search']['get']['parameters'])->toHaveCount(4)
        ->and($advancedSearchParameters['include']['x-oxcribe'])->toMatchArray([
            'allowedValues' => ['author.profile', 'comments.user', 'tags.media'],
        ])
        ->and($advancedSearchParameters['fields']['x-oxcribe'])->toMatchArray([
            'allowedValues' => ['authors', 'media', 'posts'],
            'allowedValuesByGroup' => [
                'authors' => ['email', 'name'],
                'media' => ['name'],
                'posts' => ['id', 'status', 'summary', 'title'],
            ],
        ])
        ->and($document['paths']['/api/media/gallery']['post']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'role_or_permission',
                'values' => ['media-manager', 'media.manage'],
                'guard' => 'api',
                'guards' => ['api'],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'role_or_permission:media-manager|media.manage,api',
                'subject' => null,
                'ability' => null,
                'resolution' => 'guard',
            ],
        ])
        ->and($document['paths']['/api/media/gallery']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'])->toHaveKeys([
            'attachments',
            'hero_image',
        ])
        ->and($document['paths']['/api/media/attachments']['post']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'permission',
                'values' => ['media.upload'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'permission:media.upload',
                'subject' => null,
                'ability' => null,
                'resolution' => 'inferred',
            ],
        ])
        ->and($document['paths']['/api/media/attachments']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'])->toHaveKeys([
            'attachments',
            'gallery_images',
            'preview_pdf',
            'thumbnail',
        ])
        ->and($document['paths']['/api/media/attachments']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['gallery_images'])->toMatchArray([
            'type' => 'array',
        ])
        ->and($document['paths']['/api/media/attachments']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['gallery_images']['items'])->toMatchArray([
            'type' => 'string',
            'format' => 'binary',
        ])
        ->and($document['paths']['/api/pages/{page}']['patch']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/PageResource',
        ])
        ->and($document['paths']['/api/pages/{page}']['patch']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'role',
                'values' => ['editor'],
                'guard' => 'web',
                'guards' => ['web'],
                'schemeCandidates' => ['cookieAuth'],
                'source' => 'role:editor,web',
                'subject' => null,
                'ability' => null,
                'resolution' => 'guard',
            ],
        ])
        ->and($document['paths']['/api/pages/{page}']['patch']['requestBody']['content']['application/json']['schema']['properties']['seo']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/pages/{page}']['patch']['parameters'][0]['x-oxcribe']['binding'])->toMatchArray([
            'parameter' => 'page',
            'kind' => 'implicit_model',
            'targetFqcn' => 'App\\Models\\Page',
            'isImplicit' => true,
        ])
        ->and($document['paths']['/api/series/{series}']['patch']['x-oxcribe']['authorization'])->toBe([
            [
                'kind' => 'permission',
                'values' => ['series.edit'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'permission:series.edit',
                'subject' => null,
                'ability' => null,
                'resolution' => 'default',
            ],
        ])
        ->and($document['paths']['/api/series/{series}']['patch']['responses']['200']['content']['application/json']['schema'])->toMatchArray([
            '$ref' => '#/components/schemas/SeriesResource',
        ])
        ->and($document['paths']['/api/series/{series}']['patch']['requestBody']['content']['application/json']['schema']['properties']['seo']['type'])->toBe(['object', 'null'])
        ->and($document['paths']['/api/series/{series}']['patch']['parameters'][0]['x-oxcribe']['binding'])->toMatchArray([
            'parameter' => 'series',
            'kind' => 'implicit_model',
            'targetFqcn' => 'App\\Models\\Series',
            'isImplicit' => true,
        ])
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->not->toContain('preview')
        ->and($document['paths']['/api/posts/{post}/publish-advanced']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->not->toContain('teaser');
});
