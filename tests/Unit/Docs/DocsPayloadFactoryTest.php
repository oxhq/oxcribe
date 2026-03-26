<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Docs\DocsPayloadFactory;

it('normalizes openapi documents into a stable viewer payload', function () {
    $factory = new DocsPayloadFactory;

    $payload = $factory->make([
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'Acme API',
            'version' => '2026.03',
        ],
        'paths' => [
            '/login' => [
                'post' => [
                    'operationId' => 'login.store_post',
                    'summary' => 'Login',
                    'tags' => ['Auth'],
                    'parameters' => [],
                    'requestBody' => [
                        'required' => true,
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                        ],
                    ],
                    'x-oxcribe' => [
                        'routeId' => 'route-auth-login',
                        'examples' => [
                            'happy_path' => [
                                'request' => [
                                    'body' => ['email' => 'ana.lopez@acme.test'],
                                ],
                            ],
                        ],
                        'snippets' => [
                            'happy_path' => [
                                'curl' => 'curl -X POST https://api.example.test/login',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                ],
            ],
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                ],
            ],
        ],
        'x-oxcribe' => [
            'operationCount' => 1,
            'diagnosticCount' => 0,
        ],
    ], [
        'defaultBaseUrl' => 'https://api.example.test',
    ]);

    expect($payload)->toMatchArray([
        'contractVersion' => 'oxcribe.docs.v1',
        'info' => [
            'title' => 'Acme API',
            'version' => '2026.03',
            'openapi' => '3.1.0',
        ],
        'meta' => [
            'defaultBaseUrl' => 'https://api.example.test',
            'operationCount' => 1,
            'diagnosticCount' => 0,
            'viewer' => 'universal',
        ],
    ])
        ->and($payload['operations'])->toHaveCount(1)
        ->and($payload['operations'][0])->toMatchArray([
            'id' => 'login.store_post',
            'method' => 'POST',
            'path' => '/login',
            'summary' => 'Login',
            'tags' => ['Auth'],
            'runtime' => [
                'routeId' => 'route-auth-login',
            ],
            'examples' => [
                'happy_path' => [
                    'request' => [
                        'body' => [
                            'email' => 'ana.lopez@acme.test',
                        ],
                    ],
                ],
            ],
            'snippets' => [
                'happy_path' => [
                    'curl' => 'curl -X POST https://api.example.test/login',
                ],
            ],
        ])
        ->and($payload['components']['schemas'])->toHaveKey('User')
        ->and($payload['components']['securitySchemes'])->toHaveKey('bearerAuth');
});
