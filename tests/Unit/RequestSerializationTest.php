<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Oxhq\Oxcribe\Support\RequestSerializer;

it('serializes a request into a predictable payload', function () {
    if (! class_exists(RequestSerializer::class)) {
        $this->markTestSkipped('RequestSerializer has not been created yet.');
    }

    $request = Request::create(
        '/oxcribe/requests/serialize?include=body',
        'POST',
        ['subject' => 'test', 'count' => 2],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode(['body' => 'payload', 'nested' => ['x' => 1]], JSON_THROW_ON_ERROR)
    );

    $serialized = app(RequestSerializer::class)->serialize($request);

    expect($serialized)->toBeArray()
        ->and($serialized)->toMatchArray([
            'method' => 'POST',
            'path' => 'oxcribe/requests/serialize',
            'query' => ['include' => 'body'],
        ])
        ->and($serialized['headers'])->toBeArray()
        ->and($serialized['input'])->toBeArray();
});

it('preserves richer query parameter shapes', function () {
    $request = Request::create(
        '/oxcribe/search?include=body&tags[0]=alpha&tags[1]=beta&filters[state]=open',
        'GET',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
        ],
    );

    $serialized = app(RequestSerializer::class)->serialize($request);

    expect($serialized['query'])->toMatchArray([
        'include' => 'body',
        'tags' => ['alpha', 'beta'],
        'filters' => ['state' => 'open'],
    ]);
});
