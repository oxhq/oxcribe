<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DocsAssetController
{
    private const ASSETS = [
        'docs-viewer.css' => ['path' => __DIR__.'/../../../resources/dist/docs-viewer.css', 'type' => 'text/css; charset=UTF-8'],
        'docs-viewer.js' => ['path' => __DIR__.'/../../../resources/dist/docs-viewer.js', 'type' => 'application/javascript; charset=UTF-8'],
    ];

    public function __invoke(Request $request, string $asset): Response
    {
        abort_unless(isset(self::ASSETS[$asset]), 404);

        $config = self::ASSETS[$asset];
        abort_unless(is_file($config['path']), 404);

        return response(file_get_contents($config['path']) ?: '', 200, [
            'Content-Type' => $config['type'],
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => sha1_file($config['path']) ?: sha1($asset),
        ]);
    }
}

if (! class_exists(\Garaekz\Oxcribe\Http\Controllers\DocsAssetController::class, false)) {
    class_alias(DocsAssetController::class, \Garaekz\Oxcribe\Http\Controllers\DocsAssetController::class);
}
