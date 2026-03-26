<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RouteMatch
{
    public function __construct(
        public string $routeId,
        public string $actionKind,
        public string $matchStatus,
        public ?string $actionKey = null,
        public ?string $reasonCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            routeId: (string) $payload['routeId'],
            actionKind: (string) $payload['actionKind'],
            matchStatus: (string) $payload['matchStatus'],
            actionKey: isset($payload['actionKey']) ? (string) $payload['actionKey'] : null,
            reasonCode: isset($payload['reasonCode']) ? (string) $payload['reasonCode'] : null,
        );
    }
}
