<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\RouteAction;
use Oxhq\Oxcribe\Data\RouteMatch;
use Oxhq\Oxcribe\Examples\OperationKindResolver;

it('resolves auth, crud, custom, and paginated operation kinds', function () {
    $resolver = new OperationKindResolver;

    $login = exampleMergedOperation(
        methods: ['POST'],
        uri: 'login',
        name: 'login.store',
    );

    $companiesStore = exampleMergedOperation(
        methods: ['POST'],
        uri: 'api/companies',
        name: 'companies.store',
    );

    $postsPreview = exampleMergedOperation(
        methods: ['GET'],
        uri: 'api/posts/{post}/preview',
        name: 'posts.preview',
    );

    $paginated = exampleMergedOperation(
        methods: ['GET'],
        uri: 'api/users',
        name: 'users.index',
        controller: [
            'responses' => [
                [
                    'status' => 200,
                    'bodySchema' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'meta' => ['type' => 'object'],
                            'links' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ],
    );

    expect($resolver->resolve($login))->toBe('auth.login')
        ->and($resolver->resolve($companiesStore))->toBe('companies.store')
        ->and($resolver->resolve($postsPreview))->toBe('posts.preview')
        ->and($resolver->resolve($paginated))->toBe('index.paginated');
});

/**
 * @param  list<string>  $methods
 * @param  array<string, mixed>|null  $controller
 */
function exampleMergedOperation(array $methods, string $uri, ?string $name = null, ?array $controller = null): MergedOperation
{
    return new MergedOperation(
        routeId: 'route-1',
        methods: $methods,
        uri: $uri,
        domain: null,
        name: $name,
        prefix: 'api',
        middleware: ['api'],
        where: [],
        defaults: [],
        bindings: [],
        action: new RouteAction('controller_method', 'App\\Http\\Controllers\\ExampleController', 'handle'),
        routeMatch: new RouteMatch(
            routeId: 'route-1',
            actionKind: 'controller_method',
            matchStatus: 'matched',
            actionKey: 'App\\Http\\Controllers\\ExampleController::handle',
        ),
        controller: $controller ?? [
            'fqcn' => 'App\\Http\\Controllers\\ExampleController',
            'method' => 'handle',
        ],
    );
}
