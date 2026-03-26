<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class ExampleScenario
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $description = null,
        public ?int $arrayCount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'arrayCount' => $this->arrayCount,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
