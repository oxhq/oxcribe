<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class Diagnostic
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $scope,
        public string $message,
        public ?string $routeId = null,
        public ?string $actionKey = null,
        public ?string $file = null,
        public ?int $line = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            code: (string) $payload['code'],
            severity: (string) $payload['severity'],
            scope: (string) $payload['scope'],
            message: (string) $payload['message'],
            routeId: isset($payload['routeId']) ? (string) $payload['routeId'] : null,
            actionKey: isset($payload['actionKey']) ? (string) $payload['actionKey'] : null,
            file: isset($payload['file']) ? (string) $payload['file'] : null,
            line: isset($payload['line']) ? (int) $payload['line'] : null,
        );
    }
}
