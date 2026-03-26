<?php

declare(strict_types=1);

use Garaekz\Oxcribe\Auth\AuthProfile;
use Garaekz\Oxcribe\Data\MergedOperation;
use Garaekz\Oxcribe\Data\RouteAction;
use Garaekz\Oxcribe\Data\RouteMatch;

function authMergedOperation(array $middleware): MergedOperation
{
    return new MergedOperation(
        routeId: 'route-auth-test',
        methods: ['GET'],
        uri: 'auth/test',
        domain: null,
        name: 'auth.test',
        prefix: 'api',
        middleware: $middleware,
        where: [],
        defaults: [],
        bindings: [],
        action: new RouteAction('controller_method', 'App\\Http\\Controllers\\AuthTestController', 'index'),
        routeMatch: new RouteMatch(
            routeId: 'route-auth-test',
            actionKind: 'controller_method',
            matchStatus: 'matched',
            actionKey: 'App\\Http\\Controllers\\AuthTestController::index',
        ),
        controller: [
            'fqcn' => 'App\\Http\\Controllers\\AuthTestController',
            'method' => 'index',
        ],
    );
}

it('normalizes authentication middleware into guards and scheme candidates', function () {
    $profile = authMergedOperation([
        'auth:api,web',
        'auth.basic.once',
        'auth.session',
    ])->authProfile();

    expect($profile)->toBeInstanceOf(AuthProfile::class)
        ->and($profile->requiresAuthentication)->toBeTrue()
        ->and($profile->requiresAuthorization)->toBeFalse()
        ->and($profile->authenticationMiddleware())->toBe([
            'auth:api,web',
            'auth.basic.once',
            'auth.session',
        ])
        ->and($profile->guardCandidates)->toBe(['api', 'web'])
        ->and($profile->schemeCandidates)->toBe(['bearerAuth', 'cookieAuth', 'basicAuth'])
        ->and($profile->toArray())->toMatchArray([
            'requiresAuthentication' => true,
            'requiresAuthorization' => false,
            'defaultScheme' => 'bearerAuth',
            'guardCandidates' => ['api', 'web'],
            'schemeCandidates' => ['bearerAuth', 'cookieAuth', 'basicAuth'],
            'securityRequirements' => [
                ['bearerAuth' => []],
                ['cookieAuth' => []],
                ['basicAuth' => []],
            ],
        ]);
});

it('normalizes authorization middleware with guard aliases and ability metadata', function () {
    $profile = authMergedOperation([
        'auth:sanctum',
        'role:admin,session',
        'permission:manage users',
        'can:update,App\\Models\\Post',
        'abilities:create-post|edit-post',
    ])->authProfile();

    expect($profile->requiresAuthentication)->toBeTrue()
        ->and($profile->requiresAuthorization)->toBeTrue()
        ->and($profile->authorizationMiddleware())->toBe([
            'role:admin,session',
            'permission:manage users',
            'can:update,App\\Models\\Post',
            'abilities:create-post|edit-post',
        ])
        ->and($profile->guardCandidates)->toBe(['sanctum', 'web'])
        ->and($profile->schemeCandidates)->toBe(['bearerAuth', 'cookieAuth'])
        ->and($profile->authorizationConstraints())->toMatchArray([
            [
                'kind' => 'role',
                'values' => ['admin'],
                'guard' => 'web',
                'guards' => ['web'],
                'schemeCandidates' => ['cookieAuth'],
                'source' => 'role:admin,session',
                'subject' => null,
                'ability' => null,
                'resolution' => 'guard',
            ],
            [
                'kind' => 'permission',
                'values' => ['manage users'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'permission:manage users',
                'subject' => null,
                'ability' => null,
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'can',
                'values' => ['update'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'can:update,App\\Models\\Post',
                'subject' => 'App\\Models\\Post',
                'ability' => 'update',
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'abilities',
                'values' => ['create-post', 'edit-post'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'abilities:create-post|edit-post',
                'subject' => null,
                'ability' => null,
                'resolution' => 'inferred',
            ],
        ]);
});

it('falls back to the default scheme for authorization only routes', function () {
    $profile = authMergedOperation([
        'role:admin',
    ])->authProfile();

    expect($profile->requiresAuthentication)->toBeTrue()
        ->and($profile->requiresAuthorization)->toBeTrue()
        ->and($profile->guardCandidates)->toBe([])
        ->and($profile->schemeCandidates)->toBe(['bearerAuth'])
        ->and($profile->authorizationConstraints())->toBe([
            [
                'kind' => 'role',
                'values' => ['admin'],
                'guard' => null,
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'role:admin',
                'subject' => null,
                'ability' => null,
                'resolution' => 'default',
            ],
        ]);
});

it('captures runtime auth and traffic middleware without distorting security', function () {
    $profile = authMergedOperation([
        'auth:sanctum',
        'verified',
        'password.confirm',
        'signed:relative',
        'throttle:60,1,uploads',
        'throttle:api',
    ])->authProfile();

    expect($profile->requiresAuthentication)->toBeTrue()
        ->and($profile->requiresAuthorization)->toBeFalse()
        ->and($profile->runtimeMiddleware())->toBe([
            'verified',
            'password.confirm',
            'signed:relative',
            'throttle:60,1,uploads',
            'throttle:api',
        ])
        ->and($profile->requiresVerifiedUser())->toBeTrue()
        ->and($profile->requiresPasswordConfirmation())->toBeTrue()
        ->and($profile->requiresSignedUrls())->toBeTrue()
        ->and($profile->throttleConstraints())->toBe([
            [
                'source' => 'throttle:60,1,uploads',
                'values' => ['60', '1', 'uploads'],
                'metadata' => [
                    'maxAttempts' => 60,
                    'decayMinutes' => 1,
                    'prefix' => 'uploads',
                ],
            ],
            [
                'source' => 'throttle:api',
                'values' => ['api'],
                'metadata' => [
                    'limiter' => 'api',
                ],
            ],
        ])
        ->and($profile->toArray()['schemeCandidates'])->toBe(['bearerAuth'])
        ->and($profile->toArray()['requiresVerifiedUser'])->toBeTrue()
        ->and($profile->toArray()['requiresPasswordConfirmation'])->toBeTrue()
        ->and($profile->toArray()['requiresSignedUrls'])->toBeTrue()
        ->and($profile->toArray()['runtimeConstraints'])->toBe([
            [
                'kind' => 'verified',
                'values' => [],
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'verified',
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'password_confirm',
                'values' => [],
                'guards' => [],
                'schemeCandidates' => ['bearerAuth'],
                'source' => 'password.confirm',
                'resolution' => 'inferred',
            ],
            [
                'kind' => 'signed',
                'values' => ['relative'],
                'guards' => [],
                'schemeCandidates' => [],
                'source' => 'signed:relative',
                'resolution' => 'direct',
                'metadata' => [
                    'mode' => 'relative',
                ],
            ],
            [
                'kind' => 'throttle',
                'values' => ['60', '1', 'uploads'],
                'guards' => [],
                'schemeCandidates' => [],
                'source' => 'throttle:60,1,uploads',
                'resolution' => 'direct',
                'metadata' => [
                    'maxAttempts' => 60,
                    'decayMinutes' => 1,
                    'prefix' => 'uploads',
                ],
            ],
            [
                'kind' => 'throttle',
                'values' => ['api'],
                'guards' => [],
                'schemeCandidates' => [],
                'source' => 'throttle:api',
                'resolution' => 'direct',
                'metadata' => [
                    'limiter' => 'api',
                ],
            ],
        ]);
});
