<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class FieldConstraints
{
    /**
     * @param  list<string>  $enum
     * @param  array<string, string>|null  $exists
     */
    public function __construct(
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public int|float|null $multipleOf = null,
        public ?string $pattern = null,
        public array $enum = [],
        public ?array $exists = null,
        public ?string $confirmedWith = null,
        public bool $accepted = false,
        public ?string $format = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
            'multipleOf' => $this->multipleOf,
            'pattern' => $this->pattern,
            'enum' => array_values($this->enum),
            'exists' => $this->exists,
            'confirmedWith' => $this->confirmedWith,
            'accepted' => $this->accepted,
            'format' => $this->format,
        ];
    }
}
