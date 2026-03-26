<?php

declare(strict_types=1);

use Garaekz\Oxcribe\Examples\Data\EndpointExampleContext;
use Garaekz\Oxcribe\Examples\FieldClassifier;

it('classifies company fields, password confirmation, enums, and bindings', function () {
    $classifier = new FieldClassifier();

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

    expect($companyName->semanticType)->toBe('company_name')
        ->and($companyName->hints->confidence)->toBeGreaterThan(0.5)
        ->and($password->semanticType)->toBe('password')
        ->and($password->constraints->confirmedWith)->toBe('password_confirmation')
        ->and($role->semanticType)->toBe('role')
        ->and($role->constraints->enum)->toBe(['admin', 'user'])
        ->and($boundParam->semanticType)->toBe('foreign_key_id');
});
