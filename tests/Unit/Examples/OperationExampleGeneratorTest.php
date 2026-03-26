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
