<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteBinding;
use Oxhq\Oxcribe\Data\RouteMatch;
use Oxhq\Oxcribe\Examples\OperationExampleSpecFactory;

it('builds an operation example spec from merged runtime and static metadata', function () {
    $factory = new OperationExampleSpecFactory;

    $operation = new MergedOperation(
        routeId: 'route-users-store',
        methods: ['POST'],
        uri: 'api/users/{account}',
        domain: null,
        name: 'users.store',
        prefix: 'api',
        middleware: ['api', 'auth:sanctum'],
        where: [
            'account' => '[0-9]+',
        ],
        defaults: [],
        bindings: [
            new RouteBinding('account', 'implicit', 'App\\Models\\Account', true),
        ],
        action: new RouteAction('controller_method', 'App\\Http\\Controllers\\UserController', 'store'),
        routeMatch: new RouteMatch(
            routeId: 'route-users-store',
            actionKind: 'controller_method',
            matchStatus: 'matched',
            actionKey: 'App\\Http\\Controllers\\UserController::store',
        ),
        controller: [
            'fqcn' => 'App\\Http\\Controllers\\UserController',
            'method' => 'store',
            'request' => [
                'fields' => [
                    [
                        'location' => 'body',
                        'path' => 'name',
                        'type' => 'string',
                        'required' => true,
                        'nullable' => false,
                    ],
                    [
                        'location' => 'body',
                        'path' => 'email',
                        'type' => 'string',
                        'format' => 'email',
                        'required' => true,
                        'nullable' => false,
                    ],
                    [
                        'location' => 'query',
                        'path' => 'role',
                        'type' => 'string',
                        'allowedValues' => ['admin', 'user'],
                        'required' => false,
                        'nullable' => false,
                    ],
                ],
            ],
            'responses' => [
                [
                    'status' => 201,
                    'kind' => 'json_object',
                    'bodySchema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                        ],
                        'required' => ['id', 'name', 'email'],
                    ],
                ],
                [
                    'status' => 422,
                    'kind' => 'json_object',
                ],
            ],
        ],
    );

    $spec = $factory->make($operation)->toArray();

    expect($spec['endpoint'])->toMatchArray([
        'method' => 'POST',
        'path' => '/api/users/{account}',
        'routeName' => 'users.store',
        'actionKey' => 'App\\Http\\Controllers\\UserController::store',
        'operationKind' => 'users.store',
    ])
        ->and($spec['pathParams'])->toHaveCount(1)
        ->and($spec['pathParams'][0])->toMatchArray([
            'name' => 'account',
            'path' => 'path.account',
            'semanticType' => 'foreign_key_id',
            'baseType' => 'integer',
        ])
        ->and($spec['queryParams'])->toHaveCount(1)
        ->and($spec['queryParams'][0])->toMatchArray([
            'name' => 'role',
            'path' => 'query.role',
            'semanticType' => 'role',
        ])
        ->and($spec['requestFields'])->toHaveCount(2)
        ->and($spec['requestFields'][0])->toMatchArray([
            'name' => 'email',
            'path' => 'body.email',
            'semanticType' => 'email',
            'format' => 'email',
        ])
        ->and($spec['requestFields'][1])->toMatchArray([
            'name' => 'name',
            'path' => 'body.name',
            'semanticType' => 'full_name',
        ])
        ->and($spec['responseFields'])->toHaveCount(3)
        ->and($spec['responseFields'][0])->toMatchArray([
            'name' => 'email',
            'path' => 'response.email',
            'semanticType' => 'email',
        ])
        ->and($spec['responseStatuses'])->toBe([201, 422]);
});
