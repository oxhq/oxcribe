<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Http\Controllers;

use Illuminate\Contracts\View\View;

final class DocsPageController
{
    public function __invoke(): View
    {
        if (! (bool) config('oxcribe.docs.enabled', false)) {
            abort(404);
        }

        return view('oxcribe::docs', [
            'payloadUrl' => route('oxcribe.docs.payload'),
            'openApiUrl' => route('oxcribe.openapi'),
            'viewerCssUrl' => route('oxcribe.docs.asset', ['asset' => 'docs-viewer.css']),
            'viewerJsUrl' => route('oxcribe.docs.asset', ['asset' => 'docs-viewer.js']),
            'title' => (string) config('app.name', 'Laravel API').' Docs',
        ]);
    }
}

if (! class_exists(\Garaekz\Oxcribe\Http\Controllers\DocsPageController::class, false)) {
    class_alias(DocsPageController::class, \Garaekz\Oxcribe\Http\Controllers\DocsPageController::class);
}
