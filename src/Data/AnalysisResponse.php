<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

use InvalidArgumentException;
use JsonException;

final readonly class AnalysisResponse
{
    public const CONTRACT_VERSION = 'oxcribe.oxinfer.v2';

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $delta
     * @param  list<RouteMatch>  $routeMatches
     * @param  list<Diagnostic>  $diagnostics
     */
    public function __construct(
        public string $contractVersion,
        public string $requestId,
        public string $runtimeFingerprint,
        public string $status,
        public array $meta,
        public array $delta,
        public array $routeMatches,
        public array $diagnostics,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $contractVersion = (string) ($payload['contractVersion'] ?? '');
        if ($contractVersion !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported analysis response contract version [%s]; expected [%s].',
                $contractVersion,
                self::CONTRACT_VERSION,
            ));
        }

        return new self(
            contractVersion: $contractVersion,
            requestId: (string) $payload['requestId'],
            runtimeFingerprint: (string) $payload['runtimeFingerprint'],
            status: (string) $payload['status'],
            meta: (array) $payload['meta'],
            delta: (array) $payload['delta'],
            routeMatches: array_map(
                static fn (array $routeMatch): RouteMatch => RouteMatch::fromArray($routeMatch),
                (array) $payload['routeMatches'],
            ),
            diagnostics: array_map(
                static fn (array $diagnostic): Diagnostic => Diagnostic::fromArray($diagnostic),
                (array) $payload['diagnostics'],
            ),
        );
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($payload);
    }
}
