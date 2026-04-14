# Overrides V1

`oxcribe` can load additive OpenAPI overrides from two places:

- package config: `config/oxcribe.php` under `overrides`
- project files in the Laravel app root

Default project file names:

- `.oxcribe.php`
- `oxcribe.overrides.php`

## Config Shape

```php
return [
    'overrides' => [
        'enabled' => true,
        'files' => [
            '.oxcribe.php',
            'oxcribe.overrides.php',
        ],
        'defaults' => [
            'tags' => ['API'],
            'security' => [
                ['bearerAuth' => []],
            ],
        ],
        'routes' => [
            [
                'match' => [
                    'routeId' => 'route-users-index',
                ],
                'summary' => 'List users',
                'tags' => ['Users'],
            ],
            [
                'match' => [
                    'routeId' => 'route-health',
                ],
                'include' => false,
            ],
        ],
    ],
];
```

## Project File Shape

Project files return the same structure:

```php
<?php

return [
    'defaults' => [
        'tags' => ['Project'],
    ],
    'routes' => [
        [
            'match' => [
                'actionKey' => 'App\\Http\\Controllers\\UserController::index',
            ],
            'summary' => 'List users',
            'description' => 'Administrative list of users.',
            'operationId' => 'users.index',
            'tags' => ['Users'],
            'deprecated' => true,
            'security' => [
                ['bearerAuth' => []],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Users payload',
                ],
            ],
            'requestBody' => [
                'required' => false,
            ],
            'examples' => [
                '200' => [
                    'summary' => 'Example response',
                    'value' => ['data' => []],
                ],
            ],
            'x-oxcribe' => [
                'product' => [
                    'surface' => 'admin',
                ],
            ],
            'externalDocs' => [
                'url' => 'https://example.test/docs/users',
            ],
            'extensions' => [
                'x-internal' => [
                    'owner' => 'platform',
                ],
            ],
        ],
    ],
];
```

## Current Behavior

- `include: false` filters routes out of the graph.
- `match.middleware` can target one or more route middleware aliases. Wildcards are supported.
- `summary`, `description`, `operationId`, `tags`, `deprecated`, `security`, `responses`, `requestBody`, `examples`, `x-oxcribe`, `externalDocs`, `extensions`, and `matchedSources` are resolved and attached to the merged operation payload under `controller.overrides`.
- `OxcribeManager::graph()` and `OxcribeManager::exportOpenApi()` apply the override set before handing the graph to the factory.
- `ExportOpenApiCommand` accepts repeated `--override-file=*` arguments for ad hoc project files.
- `OpenApiDocumentFactory.php` consumes `controller.overrides` to override operation summary/description, operationId, tags, deprecated flag, security, response patches, requestBody patches, examples, `x-oxcribe`, external docs, and custom `x-*` extensions in the final OpenAPI document.

## Visibility By Middleware

`oxcribe` now supports first-class route visibility markers through middleware.

Package aliases:

- `oxcribe.publish`
- `ox.publish`
- `oxcribe.private`
- `ox.private`

Recommended config for an allowlist flow:

```php
return [
    'visibility' => [
        'mode' => 'only_marked',
    ],
];
```

Then mark only the routes you want to publish:

```php
Route::middleware(['api', 'auth:sanctum', 'oxcribe.publish'])->group(function (): void {
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
});
```

If you want the default to stay "publish everything" but hide a few routes, leave `mode` as `all` and mark exclusions:

```php
Route::middleware(['api', 'oxcribe.private'])->get('/internal/health', HealthController::class);
```

The same matcher is also available inside overrides:

```php
return [
    'routes' => [
        [
            'match' => [
                'middleware' => 'oxcribe.publish',
            ],
            'tags' => ['Public'],
        ],
        [
            'match' => [
                'middleware' => ['auth:*', 'oxcribe.private'],
            ],
            'include' => false,
        ],
    ],
];
```
