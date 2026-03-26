<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RouteAction
{
    public function __construct(
        public string $kind,
        public ?string $fqcn = null,
        public ?string $method = null,
    ) {}

    public function signature(): string
    {
        return match ($this->kind) {
            'controller_method', 'invokable_controller' => sprintf('%s::%s', $this->fqcn, $this->method),
            default => $this->kind,
        };
    }

    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'fqcn' => $this->fqcn,
            'method' => $this->method,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
