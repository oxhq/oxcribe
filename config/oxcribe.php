<?php

declare(strict_types=1);

return [
    'oxinfer' => [
        'binary' => env('OXINFER_BINARY', 'oxinfer'),
        'install_path' => env('OXINFER_INSTALL_PATH', 'bin/oxinfer'),
        'working_directory' => env('OXINFER_WORKING_DIRECTORY'),
        'timeout' => (int) env('OXINFER_TIMEOUT', 120),
        'release' => [
            'repository' => env('OXINFER_RELEASE_REPOSITORY', 'oxhq/oxinfer'),
            'base_url' => env('OXINFER_RELEASE_BASE_URL', 'https://github.com'),
            'version' => env('OXINFER_RELEASE_VERSION'),
        ],
    ],

    'analysis' => [
        'composer' => 'composer.json',
        'composer_lock' => 'composer.lock',
        'scan' => [
            'targets' => ['app', 'routes'],
            'globs' => ['app/**/*.php', 'routes/**/*.php'],
            'vendor_whitelist' => [],
        ],
        'limits' => [
            'max_workers' => 8,
            'max_files' => 500,
            'max_depth' => 2,
        ],
        'cache' => [
            'enabled' => true,
            'kind' => 'sha256+mtime',
        ],
        'features' => [
            'http_status' => true,
            'request_usage' => true,
            'resource_usage' => true,
            'with_pivot' => true,
            'attribute_make' => true,
            'scopes_used' => true,
            'polymorphic' => true,
            'broadcast_channels' => true,
        ],
        'packages' => [
            'spatie' => [
                'laravelData' => 'spatie/laravel-data',
                'laravelQueryBuilder' => 'spatie/laravel-query-builder',
                'laravelPermission' => 'spatie/laravel-permission',
                'laravelMedialibrary' => 'spatie/laravel-medialibrary',
                'laravelTranslatable' => 'spatie/laravel-translatable',
            ],
        ],
    ],

    'overrides' => [
        'enabled' => env('OXCRIBE_OVERRIDES', true),
        'files' => [
            '.oxcribe.php',
            'oxcribe.overrides.php',
        ],
        'defaults' => [
            'tags' => [],
            'security' => [],
            'examples' => [],
        ],
        'routes' => [],
    ],

    'auth' => [
        'default_scheme' => env('OXCRIBE_AUTH_DEFAULT_SCHEME', 'bearerAuth'),
        'middleware_schemes' => [
            'auth' => ['bearerAuth'],
            'auth:api' => ['bearerAuth'],
            'auth:sanctum' => ['bearerAuth'],
            'auth:passport' => ['bearerAuth'],
            'auth.basic' => ['basicAuth'],
            'auth.basic.once' => ['basicAuth'],
            'auth.session' => ['cookieAuth'],
        ],
        'guard_schemes' => [
            'web' => ['cookieAuth'],
            'api' => ['bearerAuth'],
            'sanctum' => ['bearerAuth'],
            'passport' => ['bearerAuth'],
            'session' => ['cookieAuth'],
        ],
        'guard_aliases' => [
            'session' => 'web',
        ],
        'authorization_middleware' => [
            'role',
            'permission',
            'role_or_permission',
            'can',
            'ability',
            'abilities',
        ],
    ],

    'openapi' => [
        'version' => '3.1.0',
        'info' => [
            'title' => env('APP_NAME', 'Laravel API'),
            'version' => env('APP_VERSION', '0.1.0'),
        ],
        'route_filters' => [
            'exclude_uri_prefixes' => [
                '_boost',
                '_debugbar',
                '_ignition',
                '_telescope',
            ],
        ],
        'security' => [
            'default_scheme' => 'bearerAuth',
            'scope_scheme' => env('OXCRIBE_OPENAPI_SCOPE_SCHEME'),
            'schemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                ],
                'basicAuth' => [
                    'type' => 'http',
                    'scheme' => 'basic',
                ],
                'cookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => env('SESSION_COOKIE', 'laravel_session'),
                ],
            ],
            'middleware' => [
                'auth' => ['bearerAuth'],
                'auth:api' => ['bearerAuth'],
                'auth:sanctum' => ['bearerAuth'],
                'auth:passport' => ['bearerAuth'],
                'auth.basic' => ['basicAuth'],
                'auth.basic.once' => ['basicAuth'],
                'auth.session' => ['cookieAuth'],
            ],
            'guard_schemes' => [
                'web' => ['cookieAuth'],
                'api' => ['bearerAuth'],
                'sanctum' => ['bearerAuth'],
                'passport' => ['bearerAuth'],
                'session' => ['cookieAuth'],
            ],
        ],
    ],

    'docs' => [
        'enabled' => env('OXCRIBE_DOCS_ENABLED', false),
        'route' => env('OXCRIBE_DOCS_ROUTE', 'oxcribe/docs'),
        'openapi_route' => env('OXCRIBE_OPENAPI_ROUTE', 'oxcribe/openapi.json'),
        'payload_route' => env('OXCRIBE_DOCS_PAYLOAD_ROUTE', 'oxcribe/docs/payload.json'),
        'project_root' => env('OXCRIBE_DOCS_PROJECT_ROOT'),
    ],

    'publish' => [
        'base_url' => env('OXCLOUD_BASE_URL'),
        'token' => env('OXCLOUD_TOKEN'),
        'timeout' => (int) env('OXCLOUD_TIMEOUT', 30),
        'default_version' => env('OXCLOUD_DEFAULT_VERSION'),
    ],
];
