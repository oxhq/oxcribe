<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\ExampleMode;
use Oxhq\Oxcribe\Examples\ScenarioContextFactory;

it('builds deterministic scenario context from project and endpoint seeds', function () {
    $factory = new ScenarioContextFactory;
    $endpoint = new EndpointExampleContext(
        method: 'POST',
        path: '/api/login',
        routeName: 'login.store',
        actionKey: 'App\\Http\\Controllers\\Auth\\LoginController::__invoke',
        operationKind: 'auth.login',
    );

    $first = $factory->make('project-a', $endpoint, ExampleMode::HappyPath)->toArray();
    $second = $factory->make('project-a', $endpoint, ExampleMode::HappyPath)->toArray();
    $third = $factory->make('project-b', $endpoint, ExampleMode::HappyPath)->toArray();

    expect($first)->toBe($second)
        ->and($first['seed'])->not->toBe($third['seed'])
        ->and($first['person']['email'])->toEndWith('.test')
        ->and($first['auth']['password'])->toStartWith('Str0ng!Pass')
        ->and($first['company']['website'])->toStartWith('https://');
});
