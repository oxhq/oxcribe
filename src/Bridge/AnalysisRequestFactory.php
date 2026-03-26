<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Bridge;

use Illuminate\Support\Str;
use JsonException;
use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\ManifestFactory;

final class AnalysisRequestFactory
{
    public const CONTRACT_VERSION = 'oxcribe.oxinfer.v2';

    public function __construct(
        private readonly ManifestFactory $manifestFactory,
        private readonly array $config,
    ) {}

    /**
     * @throws JsonException
     */
    public function make(RuntimeSnapshot $runtime, ?string $projectRoot = null): AnalysisRequest
    {
        $resolvedProjectRoot = $projectRoot ?? $runtime->app->basePath;
        $manifest = $this->manifestFactory->make($resolvedProjectRoot, $this->config);
        $runtimeFingerprint = hash(
            'sha256',
            json_encode($runtime->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return new AnalysisRequest(
            contractVersion: self::CONTRACT_VERSION,
            requestId: (string) Str::uuid(),
            runtimeFingerprint: $runtimeFingerprint,
            manifest: $manifest,
            runtime: $runtime,
        );
    }
}
