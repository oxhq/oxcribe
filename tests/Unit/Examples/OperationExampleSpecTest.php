<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\FieldConstraints;
use Oxhq\Oxcribe\Examples\Data\FieldHints;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\Examples\Data\ScenarioAuth;
use Oxhq\Oxcribe\Examples\Data\ScenarioCompany;
use Oxhq\Oxcribe\Examples\Data\ScenarioContext;
use Oxhq\Oxcribe\Examples\Data\ScenarioPerson;
use Oxhq\Oxcribe\Examples\ExampleMode;

it('serializes the smart examples ir deterministically', function () {
    $email = new ExampleField(
        name: 'email',
        path: 'body.email',
        location: 'body',
        baseType: 'string',
        semanticType: 'email',
        required: true,
        nullable: false,
        collection: false,
        itemType: null,
        constraints: new FieldConstraints(
            maxLength: 255,
            format: 'email',
        ),
        hints: new FieldHints(
            confidence: 0.98,
            source: ['field_name', 'validation_rule'],
            via: ['rule:email'],
        ),
        format: 'email',
    );

    $role = new ExampleField(
        name: 'role',
        path: 'body.role',
        location: 'body',
        baseType: 'string',
        semanticType: 'role',
        required: true,
        nullable: false,
        collection: false,
        itemType: null,
        constraints: new FieldConstraints(
            enum: ['admin', 'user'],
        ),
        hints: new FieldHints(
            confidence: 0.84,
            source: ['validation_rule'],
            via: ['rule:in'],
        ),
        allowedValues: ['admin', 'user'],
    );

    $spec = new OperationExampleSpec(
        endpoint: new EndpointExampleContext(
            method: 'POST',
            path: '/api/users',
            routeName: 'users.store',
            actionKey: 'App\\Http\\Controllers\\UserController::store',
            operationKind: 'users.store',
        ),
        requestFields: [$email, $role],
        responseStatuses: [201, 422, 201],
    );

    $context = new ScenarioContext(
        seed: 'project:users.store:happy_path',
        mode: ExampleMode::HappyPath,
        person: new ScenarioPerson(
            firstName: 'Ana',
            lastName: 'Lopez',
            fullName: 'Ana Lopez',
            email: 'ana.lopez@acme.test',
            phone: '+526641234567',
            username: 'ana_lopez',
        ),
        company: new ScenarioCompany(
            name: 'Acme Logistics',
            email: 'contact@acme.test',
            website: 'https://acme.test',
            domain: 'acme.test',
        ),
        auth: new ScenarioAuth(
            password: 'Str0ng!Pass2026',
            token: 'tok_test_8f4a1c29b2',
            apiKey: 'oxc_live_3baf9c1d8a',
        ),
        resources: [
            'user' => [
                'id' => 123,
            ],
        ],
    );

    expect($spec->toArray())->toBe([
        'endpoint' => [
            'method' => 'POST',
            'path' => '/api/users',
            'routeName' => 'users.store',
            'actionKey' => 'App\\Http\\Controllers\\UserController::store',
            'operationKind' => 'users.store',
        ],
        'pathParams' => [],
        'queryParams' => [],
        'requestFields' => [
            [
                'name' => 'email',
                'path' => 'body.email',
                'location' => 'body',
                'baseType' => 'string',
                'semanticType' => 'email',
                'required' => true,
                'nullable' => false,
                'collection' => false,
                'itemType' => null,
                'format' => 'email',
                'allowedValues' => [],
                'constraints' => [
                    'minLength' => null,
                    'maxLength' => 255,
                    'minimum' => null,
                    'maximum' => null,
                    'multipleOf' => null,
                    'pattern' => null,
                    'enum' => [],
                    'exists' => null,
                    'confirmedWith' => null,
                    'accepted' => false,
                    'format' => 'email',
                ],
                'hints' => [
                    'confidence' => 0.98,
                    'source' => ['field_name', 'validation_rule'],
                    'via' => ['rule:email'],
                ],
            ],
            [
                'name' => 'role',
                'path' => 'body.role',
                'location' => 'body',
                'baseType' => 'string',
                'semanticType' => 'role',
                'required' => true,
                'nullable' => false,
                'collection' => false,
                'itemType' => null,
                'format' => null,
                'allowedValues' => ['admin', 'user'],
                'constraints' => [
                    'minLength' => null,
                    'maxLength' => null,
                    'minimum' => null,
                    'maximum' => null,
                    'multipleOf' => null,
                    'pattern' => null,
                    'enum' => ['admin', 'user'],
                    'exists' => null,
                    'confirmedWith' => null,
                    'accepted' => false,
                    'format' => null,
                ],
                'hints' => [
                    'confidence' => 0.84,
                    'source' => ['validation_rule'],
                    'via' => ['rule:in'],
                ],
            ],
        ],
        'responseFields' => [],
        'responseStatuses' => [201, 422],
    ])
        ->and($context->toArray())->toBe([
            'seed' => 'project:users.store:happy_path',
            'mode' => 'happy_path',
            'person' => [
                'firstName' => 'Ana',
                'lastName' => 'Lopez',
                'fullName' => 'Ana Lopez',
                'email' => 'ana.lopez@acme.test',
                'phone' => '+526641234567',
                'username' => 'ana_lopez',
            ],
            'company' => [
                'name' => 'Acme Logistics',
                'email' => 'contact@acme.test',
                'website' => 'https://acme.test',
                'domain' => 'acme.test',
            ],
            'auth' => [
                'password' => 'Str0ng!Pass2026',
                'token' => 'tok_test_8f4a1c29b2',
                'apiKey' => 'oxc_live_3baf9c1d8a',
            ],
            'resources' => [
                'user' => [
                    'id' => 123,
                ],
            ],
        ]);
});
