<?php

declare(strict_types=1);

use Oxhq\Oxcribe\OpenApi\Support\ResourceSchemaIndex;

it('converts nested $ref aliases inside inline schemas', function () {
    $index = new ResourceSchemaIndex([
        [
            'fqcn' => 'App\\Http\\Resources\\PostResource',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                ],
            ],
        ],
    ]);

    $schema = $index->schemaForNode([
        'type' => 'object',
        'required' => ['data'],
        'properties' => [
            'data' => [
                'type' => 'array',
                'items' => [
                    '$ref' => 'App\\Http\\Resources\\PostResource',
                ],
            ],
        ],
    ]);

    expect($schema)->toMatchArray([
        'type' => 'object',
        'required' => ['data'],
        'properties' => [
            'data' => [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/PostResource',
                ],
            ],
        ],
    ]);
});
