<?php

declare(strict_types=1);

return [
    'routes' => [
        [
            'uri' => 'internal/health',
            'methods' => ['GET'],
            'summary' => 'Internal health endpoint',
            'tags' => ['internal'],
            'x-oxcribe' => [
                'visibility' => 'internal',
            ],
        ],
    ],
];
