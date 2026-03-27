<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\FieldClassifier;

it('classifies company fields, password confirmation, enums, and bindings', function () {
    $classifier = new FieldClassifier;

    $companyName = $classifier->classify(
        path: 'name',
        location: 'body',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('POST', '/api/companies', 'companies.store', 'App\\Http\\Controllers\\CompanyController::store', 'companies.store'),
    );

    $password = $classifier->classify(
        path: 'password',
        location: 'body',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('POST', '/register', 'register.store', 'App\\Http\\Controllers\\RegisterController::store', 'auth.register'),
        knownPaths: ['body.password', 'body.password_confirmation'],
    );

    $role = $classifier->classify(
        path: 'role',
        location: 'body',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
            'allowedValues' => ['admin', 'user'],
        ],
        endpoint: new EndpointExampleContext('POST', '/api/users', 'users.store', 'App\\Http\\Controllers\\UserController::store', 'users.store'),
    );

    $boundParam = $classifier->classify(
        path: 'post',
        location: 'path',
        metadata: [
            'type' => 'integer',
            'required' => true,
            'nullable' => false,
            'bindingTarget' => 'App\\Models\\Post',
        ],
        endpoint: new EndpointExampleContext('GET', '/api/posts/{post}', 'posts.show', 'App\\Http\\Controllers\\PostController::show', 'posts.show'),
    );

    $gameName = $classifier->classify(
        path: 'name',
        location: 'response',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('GET', '/api/games', 'games.index', 'App\\Http\\Controllers\\DiscoveryController::games', 'games.index'),
    );

    $image = $classifier->classify(
        path: 'image',
        location: 'response',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('GET', '/api/games', 'games.index', 'App\\Http\\Controllers\\DiscoveryController::games', 'games.index'),
    );

    $search = $classifier->classify(
        path: 'search',
        location: 'query',
        metadata: [
            'type' => 'string',
            'required' => false,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('GET', '/api/games', 'games.index', 'App\\Http\\Controllers\\DiscoveryController::games', 'games.index'),
    );

    $limit = $classifier->classify(
        path: 'limit',
        location: 'query',
        metadata: [
            'type' => 'integer',
            'required' => false,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('GET', '/api/games', 'games.index', 'App\\Http\\Controllers\\DiscoveryController::games', 'games.index'),
    );

    $broadcastId = $classifier->classify(
        path: 'broadcastId',
        location: 'path',
        metadata: [
            'type' => 'integer',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('GET', '/api/broadcasts/{broadcastId}', 'broadcasts.show', 'App\\Http\\Controllers\\BroadcastController::show', 'broadcasts.show'),
    );

    $domain = $classifier->classify(
        path: 'domain',
        location: 'body',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('POST', '/api/organizations', 'organizations.store', 'App\\Http\\Controllers\\OrganizationController::store', 'organizations.store'),
    );

    $request = $classifier->classify(
        path: 'request',
        location: 'body',
        metadata: [
            'type' => 'string',
            'required' => true,
            'nullable' => false,
        ],
        endpoint: new EndpointExampleContext('POST', '/api/workspaces', 'workspaces.store', 'App\\Http\\Controllers\\WorkspaceController::store', 'workspaces.store'),
    );

    expect($companyName->semanticType)->toBe('company_name')
        ->and($companyName->hints->confidence)->toBeGreaterThan(0.5)
        ->and($password->semanticType)->toBe('password')
        ->and($password->constraints->confirmedWith)->toBe('password_confirmation')
        ->and($role->semanticType)->toBe('role')
        ->and($role->constraints->enum)->toBe(['admin', 'user'])
        ->and($boundParam->semanticType)->toBe('foreign_key_id')
        ->and($gameName->semanticType)->toBe('title')
        ->and($image->semanticType)->toBe('url')
        ->and($search->semanticType)->toBe('search_term')
        ->and($limit->semanticType)->toBe('page_size')
        ->and($broadcastId->semanticType)->toBe('foreign_key_id')
        ->and($domain->semanticType)->toBe('domain')
        ->and($request->semanticType)->toBe('request_payload');
});
