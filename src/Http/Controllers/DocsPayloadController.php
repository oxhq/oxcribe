<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Http\Controllers;

use Garaekz\Oxcribe\OxcribeManager;
use Illuminate\Http\JsonResponse;

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
