<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Oxhq\Oxcribe\OxcribeManager;

final class OpenApiDocumentController
{
    public function __invoke(OxcribeManager $manager): JsonResponse
    {
        if (! (bool) config('oxcribe.docs.enabled', false)) {
            abort(404);
        }

        $projectRoot = $this->configuredProjectRoot();

        return response()->json($manager->exportOpenApi($projectRoot));
    }

    private function configuredProjectRoot(): ?string
    {
        $configured = trim((string) config('oxcribe.docs.project_root', ''));

        return $configured !== '' ? $configured : null;
    }
}

if (! class_exists(\Garaekz\Oxcribe\Http\Controllers\OpenApiDocumentController::class, false)) {
    class_alias(OpenApiDocumentController::class, \Garaekz\Oxcribe\Http\Controllers\OpenApiDocumentController::class);
}
