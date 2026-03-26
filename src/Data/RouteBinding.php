<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RouteBinding
{
    public function __construct(
        public string $parameter,
        public string $kind,
        public ?string $targetFqcn,
        public bool $isImplicit,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'parameter' => $this->parameter,
            'kind' => $this->kind,
            'targetFqcn' => $this->targetFqcn,
            'isImplicit' => $this->isImplicit,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
