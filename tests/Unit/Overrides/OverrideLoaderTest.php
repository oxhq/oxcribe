<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Overrides\OverrideLoader;

it('loads config defaults and project override files in order', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-overrides-'.bin2hex(random_bytes(4));
    mkdir($projectRoot, 0777, true);

    $overrideFile = $projectRoot.'/oxcribe.overrides.php';
    file_put_contents($overrideFile, <<<'PHP'
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
            'description' => 'List users from project overrides.',
            'tags' => ['Users'],
            'operationId' => 'users.index',
            'deprecated' => true,
            'responses' => [
                '200' => [
                    'description' => 'Users payload',
                ],
            ],
        ],
        [
            'match' => [
                'routeId' => 'route-hidden',
            ],
            'include' => false,
        ],
    ],
];
PHP);

    config()->set('oxcribe.overrides.enabled', true);
    config()->set('oxcribe.overrides.files', ['oxcribe.overrides.php']);
    config()->set('oxcribe.overrides.defaults', [
        'tags' => ['Config'],
        'security' => [
            ['bearerAuth' => []],
        ],
    ]);
    config()->set('oxcribe.visibility', [
        'mode' => 'all',
        'include_middleware' => [],
        'exclude_middleware' => [],
    ]);
    config()->set('oxcribe.overrides.routes', [
        [
            'match' => [
                'routeId' => 'route-configured',
            ],
            'summary' => 'Configured summary',
        ],
    ]);

    $set = app(OverrideLoader::class)->load($projectRoot);

    expect($set->sources)->toContain('config', str_replace('\\', '/', $overrideFile))
        ->and($set->rules)->toHaveCount(5)
        ->and($set->rules[0]->summary)->toBeNull()
        ->and($set->rules[0]->tags)->toBe(['Config'])
        ->and($set->rules[1]->routeId)->toBe('route-configured')
        ->and($set->rules[2]->summary)->toBeNull()
        ->and($set->rules[2]->tags)->toBe(['Project'])
        ->and($set->rules[3]->operationId)->toBe('users.index')
        ->and($set->rules[3]->description)->toBe('List users from project overrides.')
        ->and($set->rules[3]->deprecated)->toBeTrue()
        ->and($set->rules[3]->responses)->toMatchArray([
            '200' => [
                'description' => 'Users payload',
            ],
        ])
        ->and($set->rules[4]->include)->toBeFalse();
});

it('builds middleware-based visibility rules from config', function () {
    config()->set('oxcribe.overrides.enabled', true);
    config()->set('oxcribe.overrides.files', []);
    config()->set('oxcribe.overrides.defaults', []);
    config()->set('oxcribe.overrides.routes', []);
    config()->set('oxcribe.visibility', [
        'mode' => 'only_marked',
        'include_middleware' => ['oxcribe.publish'],
        'exclude_middleware' => ['oxcribe.private'],
    ]);

    $set = app(OverrideLoader::class)->load(sys_get_temp_dir());

    expect($set->sources)->toContain('config:visibility', 'config')
        ->and($set->rules)->toHaveCount(3)
        ->and($set->rules[0]->uri)->toBe('*')
        ->and($set->rules[0]->include)->toBeFalse()
        ->and($set->rules[1]->middleware)->toBe(['oxcribe.publish'])
        ->and($set->rules[2]->middleware)->toBe(['oxcribe.private'])
        ->and($set->rules[2]->include)->toBeFalse();
});
