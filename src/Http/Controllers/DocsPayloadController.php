<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Oxhq\Oxcribe\OxcribeManager;

final class DocsPayloadController
{
    public function __invoke(OxcribeManager $manager): JsonResponse
    {
        if (! (bool) config('oxcribe.docs.enabled', false)) {
            abort(404);
        }

        $projectRoot = $this->configuredProjectRoot();

        return response()->json($manager->docsPayload($projectRoot));
    }

    private function configuredProjectRoot(): ?string
    {
        $configured = trim((string) config('oxcribe.docs.project_root', ''));

        return $configured !== '' ? $configured : null;
    }
}

if (! class_exists(\Garaekz\Oxcribe\Http\Controllers\DocsPayloadController::class, false)) {
    class_alias(DocsPayloadController::class, \Garaekz\Oxcribe\Http\Controllers\DocsPayloadController::class);
}
