<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Http\Controllers;

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
            'title' => (string) config('app.name', 'Laravel API').' Docs',
        ]);
    }
}
