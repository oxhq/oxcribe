<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Support\InstalledPackageDetector;

it('detects relevant spatie packages from composer lock and composer json', function () {
    $tempDir = sys_get_temp_dir().'/oxcribe-spatie-'.bin2hex(random_bytes(6));
    mkdir($tempDir, 0o755, true);

    try {
        file_put_contents($tempDir.'/composer.json', json_encode([
            'require' => [
                'php' => '^8.2',
                'spatie/laravel-data' => '^4.0',
                'spatie/laravel-medialibrary' => '^11.0',
            ],
            'require-dev' => [
                'spatie/laravel-translatable' => '^6.0',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        file_put_contents($tempDir.'/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'spatie/laravel-data',
                    'version' => '4.1.0.0',
                    'pretty_version' => '4.1.0',
                ],
            ],
            'packages-dev' => [
                [
                    'name' => 'spatie/laravel-query-builder',
                    'version' => '6.3.0.0',
                    'pretty_version' => '6.3.0',
                ],
                [
                    'name' => 'spatie/laravel-permission',
                    'version' => '6.12.0.0',
                    'pretty_version' => '6.12.0',
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $detector = new InstalledPackageDetector([
            'analysis' => [
                'composer' => 'composer.json',
                'composer_lock' => 'composer.lock',
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
        ]);

        $inventory = $detector->detect($tempDir);
        $spatie = $inventory->spatie;

        expect($spatie->laravelData->toArray())->toMatchArray([
            'name' => 'spatie/laravel-data',
            'installed' => true,
            'version' => '4.1.0',
            'constraint' => null,
            'source' => 'composer.lock',
            'dev' => false,
        ])
            ->and($spatie->laravelQueryBuilder->toArray())->toMatchArray([
                'name' => 'spatie/laravel-query-builder',
                'installed' => true,
                'version' => '6.3.0',
                'source' => 'composer.lock',
                'dev' => true,
            ])
            ->and($spatie->laravelPermission->toArray())->toMatchArray([
                'name' => 'spatie/laravel-permission',
                'installed' => true,
                'version' => '6.12.0',
                'source' => 'composer.lock',
                'dev' => true,
            ])
            ->and($spatie->laravelMedialibrary->toArray())->toMatchArray([
                'name' => 'spatie/laravel-medialibrary',
                'installed' => false,
                'constraint' => '^11.0',
                'source' => 'composer.json',
                'dev' => false,
            ])
            ->and($spatie->laravelTranslatable->toArray())->toMatchArray([
                'name' => 'spatie/laravel-translatable',
                'installed' => false,
                'constraint' => '^6.0',
                'source' => 'composer.json',
                'dev' => true,
            ]);
    } finally {
        $removeDirectory = static function (string $directory) use (&$removeDirectory): void {
            if (! is_dir($directory)) {
                return;
            }

            foreach (scandir($directory) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $directory.DIRECTORY_SEPARATOR.$item;
                if (is_dir($path)) {
                    $removeDirectory($path);

                    continue;
                }

                @unlink($path);
            }

            @rmdir($directory);
        };

        $removeDirectory($tempDir);
    }
});
