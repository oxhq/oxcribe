<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

use JsonException;

final readonly class AnalysisRequest
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        public string $contractVersion,
        public string $requestId,
        public string $runtimeFingerprint,
        public array $manifest,
        public RuntimeSnapshot $runtime,
    ) {}

    public function toArray(): array
    {
        return [
            'contractVersion' => $this->contractVersion,
            'requestId' => $this->requestId,
            'runtimeFingerprint' => $this->runtimeFingerprint,
            'manifest' => $this->manifest,
            'runtime' => $this->runtime->toArray(),
        ];
    }

    public function toWireArray(): array
    {
        return [
            'contractVersion' => $this->contractVersion,
            'requestId' => $this->requestId,
            'runtimeFingerprint' => $this->runtimeFingerprint,
            'manifest' => $this->manifest,
            'runtime' => $this->runtime->toWireArray(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toArray(), $flags);
    }

    /**
     * @throws JsonException
     */
    public function toWireJson(bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toWireArray(), $flags);
    }
}
