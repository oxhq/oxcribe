<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\FieldConstraints;
use Oxhq\Oxcribe\Examples\Data\FieldHints;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\Examples\ExampleMode;
use Oxhq\Oxcribe\Examples\OperationExampleGenerator;

it('generates coherent auth login examples and snippets', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'POST',
            path: '/api/login',
            routeName: 'login.store',
            actionKey: 'App\\Http\\Controllers\\Auth\\LoginController::__invoke',
            operationKind: 'auth.login',
        ),
        requestFields: [
            exampleField('body.email', 'body', 'string', 'email', true, false, format: 'email'),
            exampleField('body.password', 'body', 'string', 'password', true, false),
            exampleField('body.remember', 'body', 'boolean', 'boolean', false, false, confidence: 0.9),
        ],
        responseFields: [
            exampleField('response.token', 'response', 'string', 'token', true, false),
            exampleField('response.user.email', 'response', 'string', 'email', true, false, format: 'email'),
            exampleField('response.user.name', 'response', 'string', 'full_name', true, false),
        ],
        responseStatuses: [200, 422],
    );

    $example = $generator->generate($spec, 'project-auth', ExampleMode::HappyPath)->toArray();

    expect($example['request']['body'])->toMatchArray([
        'email' => $example['context']['person']['email'],
        'password' => $example['context']['auth']['password'],
        'remember' => true,
    ])
        ->and($example['response'])->toMatchArray([
            'status' => 200,
            'body' => [
                'token' => $example['context']['auth']['token'],
                'user' => [
                    'email' => $example['context']['person']['email'],
                    'name' => $example['context']['person']['fullName'],
                ],
            ],
        ])
        ->and($example['snippets']['curl'])->toContain('/api/login')
        ->and($example['snippets']['curl'])->toContain('"email"')
        ->and($example['snippets']['fetch'])->toContain('fetch(')
        ->and($example['snippets']['axios'])->toContain('axios(');
});

it('supports minimal and full collection-aware examples for store operations', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'POST',
            path: '/api/posts/{post}/publish',
            routeName: 'posts.publish',
            actionKey: 'App\\Http\\Controllers\\PostController::publish',
            operationKind: 'posts.publish',
        ),
        pathParams: [
            exampleField('path.post', 'path', 'integer', 'foreign_key_id', true, false),
        ],
        queryParams: [
            exampleField('query.role', 'query', 'string', 'role', false, false, allowedValues: ['admin', 'editor'], confidence: 0.9),
        ],
        requestFields: [
            exampleField('body.title', 'body', 'string', 'string', true, false),
            exampleField('body.reviewers[]', 'body', 'array', 'array', true, false, collection: true),
            exampleField('body.reviewers[].email', 'body', 'string', 'email', true, false, format: 'email'),
            exampleField('body.reviewers[].name', 'body', 'string', 'full_name', true, false),
            exampleField('body.notes', 'body', 'string', 'string', false, true, confidence: 0.6),
        ],
        responseFields: [
            exampleField('response.data.id', 'response', 'integer', 'foreign_key_id', true, false),
            exampleField('response.data.title', 'response', 'string', 'string', true, false),
        ],
        responseStatuses: [202],
    );

    $minimal = $generator->generate($spec, 'project-posts', ExampleMode::MinimalValid)->toArray();
    $full = $generator->generate($spec, 'project-posts', ExampleMode::RealisticFull)->toArray();

    expect($minimal['request']['pathParams'])->toHaveKey('post')
        ->and($minimal['request']['queryParams'])->toBe([])
        ->and($minimal['request']['body'])->toHaveKeys(['title', 'reviewers'])
        ->and($minimal['request']['body'])->not->toHaveKey('notes')
        ->and($minimal['request']['body']['reviewers'])->toHaveCount(1)
        ->and($full['request']['queryParams'])->toBe(['role' => 'admin'])
        ->and($full['request']['body'])->toHaveKey('notes')
        ->and($full['request']['body']['reviewers'])->toHaveCount(2)
        ->and($full['response']['status'])->toBe(202)
        ->and($full['snippets']['curl'])->toContain('/api/posts/')
        ->and($full['snippets']['curl'])->toContain('?role=admin');
});

it('derives named scenarios for collection-heavy operations', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'POST',
            path: '/api/orders',
            routeName: 'orders.store',
            actionKey: 'App\\Http\\Controllers\\OrderController::store',
            operationKind: 'orders.store',
        ),
        requestFields: [
            exampleField('body.items[]', 'body', 'array', 'array', true, false, collection: true),
            exampleField('body.items[].sku', 'body', 'string', 'string', true, false),
            exampleField('body.items[].quantity', 'body', 'integer', 'quantity', true, false),
        ],
        responseFields: [
            exampleField('response.data.items[]', 'response', 'array', 'array', true, false, collection: true),
            exampleField('response.data.items[].sku', 'response', 'string', 'string', true, false),
        ],
        responseStatuses: [201],
    );

    $scenarios = $generator->generateScenarios($spec, 'project-orders');

    $single = $scenarios['happy_path']['single_item'];
    $multiple = $scenarios['happy_path']['multiple_items'];

    expect($scenarios)->toHaveKeys(['minimal_valid', 'happy_path', 'realistic_full'])
        ->and($scenarios['happy_path'])->toHaveKeys(['single_item', 'multiple_items'])
        ->and($single->toArray())->toMatchArray([
            'key' => 'single_item',
            'label' => 'Single item',
        ])
        ->and($single->example->request->body['items'])->toHaveCount(1)
        ->and($single->example->request->body['items'][0])->toHaveKeys(['sku', 'quantity'])
        ->and($multiple->example->request->body['items'])->toHaveCount(3);
});

it('generates catalog-friendly titles, genres, and media urls', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'GET',
            path: '/api/games',
            routeName: 'games.index',
            actionKey: 'App\\Http\\Controllers\\Api\\DiscoveryController::games',
            operationKind: 'games.index',
        ),
        responseFields: [
            exampleField('response.data[].name', 'response', 'string', 'title', true, false, collection: false),
            exampleField('response.data[].genre', 'response', 'string', 'genre', true, false, collection: false),
            exampleField('response.data[].image', 'response', 'string', 'url', true, false, collection: false),
        ],
        responseStatuses: [200],
    );

    $example = $generator->generate($spec, 'project-games', ExampleMode::HappyPath)->toArray();
    $first = $example['response']['body']['data'][0];

    expect($first['name'])->toContain(' ')
        ->and($first['name'])->not->toContain('@')
        ->and($first['genre'])->toBeString()
        ->and($first['image'])->toContain('https://images.example.test/');
});

it('generates richer directory and filter examples for product-like payloads', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'GET',
            path: '/api/creators/{creator}/creator-information',
            routeName: 'creators.information.index',
            actionKey: 'App\\Http\\Controllers\\CreatorInformationController::index',
            operationKind: 'creators.information.index',
        ),
        queryParams: [
            exampleField('query.search', 'query', 'string', 'search_term', false, false, confidence: 0.9),
            exampleField('query.limit', 'query', 'integer', 'page_size', false, false, confidence: 0.9),
        ],
        responseFields: [
            exampleField('response.data[].icon', 'response', 'string', 'icon_name', true, false),
            exampleField('response.data[].label', 'response', 'string', 'label', true, false),
            exampleField('response.data[].type', 'response', 'string', 'kind', true, false),
            exampleField('response.data[].value', 'response', 'string', 'attribute_value', true, false),
            exampleField('response.data[].color', 'response', 'string', 'color', false, false),
            exampleField('response.data[].primary_platform', 'response', 'string', 'platform', false, false),
            exampleField('response.data[].language', 'response', 'string', 'language', false, false),
        ],
        responseStatuses: [200],
    );

    $example = $generator->generate($spec, 'project-creators', ExampleMode::HappyPath)->toArray();
    $first = $example['response']['body']['data'][0];

    expect($example['request']['queryParams']['search'])->not->toStartWith('example_')
        ->and($example['request']['queryParams']['limit'])->toBeIn([10, 12, 20, 25, 50])
        ->and($first['icon'])->toBeIn(['twitch', 'youtube', 'discord', 'calendar', 'location', 'globe'])
        ->and($first['label'])->not->toStartWith('example_')
        ->and($first['type'])->not->toStartWith('example_')
        ->and($first['value'])->not->toStartWith('example_')
        ->and($first['color'])->toStartWith('#')
        ->and($first['primary_platform'])->toBeIn(['Twitch', 'YouTube', 'TikTok', 'Kick', 'Discord'])
        ->and($first['language'])->toBeIn(['English', 'Spanish', 'Portuguese', 'French']);
});

it('generates richer highlights, social links, status, and error strings', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'GET',
            path: '/api/creators',
            routeName: 'creators.index',
            actionKey: 'App\\Http\\Controllers\\CreatorController::index',
            operationKind: 'creators.index',
        ),
        responseFields: [
            exampleField('response.data[].highlights[]', 'response', 'array', 'highlight', true, false, collection: true),
            exampleField('response.data[].social_links[]', 'response', 'array', 'url', true, false, collection: true),
            exampleField('response.data[].status', 'response', 'string', 'status', true, false),
            exampleField('response.errors[]', 'response', 'array', 'error_message', true, false, collection: true),
        ],
        responseStatuses: [200],
    );

    $example = $generator->generate($spec, 'project-richness', ExampleMode::HappyPath)->toArray();
    $first = $example['response']['body']['data'][0];

    expect($first['highlights'][0])->not->toStartWith('example_')
        ->and($first['social_links'][0])->toContain('https://')
        ->and($first['status'])->toBeIn(['active', 'live', 'draft', 'scheduled'])
        ->and($example['response']['body']['errors'][0])->not->toStartWith('example_');
});

it('generates richer collection, domain, request payload, and note examples without placeholders', function () {
    $generator = new OperationExampleGenerator;

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'POST',
            path: '/api/organizations/{organization}/users',
            routeName: 'organizations.users.store',
            actionKey: 'App\\Http\\Controllers\\OrganizationUserController::store',
            operationKind: 'organizations.users.store',
        ),
        pathParams: [
            exampleField('path.broadcastId', 'path', 'integer', 'foreign_key_id', true, false),
        ],
        requestFields: [
            exampleField('body.domain', 'body', 'string', 'domain', true, false),
            exampleField('body.request', 'body', 'string', 'request_payload', true, false),
            exampleField('body.note', 'body', 'string', 'note', false, true),
            exampleField('body.workspaces', 'body', 'string', 'string', true, false, collection: true),
            exampleField('body.platform_accounts', 'body', 'string', 'string', false, true, collection: true),
            exampleField('body.properties', 'body', 'string', 'json_blob', false, true),
            exampleField('body.role', 'body', 'string', 'role', true, false),
        ],
        responseStatuses: [201],
    );

    $example = $generator->generate($spec, 'project-premium', ExampleMode::RealisticFull)->toArray();

    expect($example['request']['pathParams']['broadcastId'])->toBeInt()
        ->and($example['request']['body']['domain'])->toEndWith('.gg')
        ->and($example['request']['body']['request'])->not->toStartWith('example_')
        ->and($example['request']['body']['note'])->not->toStartWith('example_')
        ->and($example['request']['body']['properties'])->toStartWith('{')
        ->and($example['request']['body']['role'])->toBeIn(['member', 'editor', 'admin'])
        ->and($example['request']['body']['workspaces'])->toBeArray()
        ->and($example['request']['body']['workspaces'][0])->toBeInt()
        ->and($example['request']['body']['platform_accounts'])->toBeArray()
        ->and($example['request']['body']['platform_accounts'][0])->toHaveKeys(['platform', 'handle']);
});

/**
 * @param  list<string>  $allowedValues
 */
function exampleField(
    string $path,
    string $location,
    string $baseType,
    string $semanticType,
    bool $required,
    bool $nullable,
    ?string $format = null,
    array $allowedValues = [],
    bool $collection = false,
    float $confidence = 0.95,
): ExampleField {
    $name = preg_replace('/^.*[.\[]([A-Za-z0-9_]+)\]?$/', '$1', $path) ?: $path;

    return new ExampleField(
        name: $name,
        path: $path,
        location: $location,
        baseType: $baseType,
        semanticType: $semanticType,
        required: $required,
        nullable: $nullable,
        collection: $collection,
        itemType: $collection ? 'string' : null,
        constraints: new FieldConstraints(
            enum: $allowedValues,
            format: $format,
        ),
        hints: new FieldHints(
            confidence: $confidence,
            source: ['test'],
            via: ['fixture'],
        ),
        format: $format,
        allowedValues: $allowedValues,
    );
}
