<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class FieldHints
{
    /**
     * @param  list<string>  $source
     * @param  list<string>  $via
     */
    public function __construct(
        public float $confidence,
        public array $source = [],
        public array $via = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'source' => array_values($this->source),
            'via' => array_values($this->via),
        ];
    }
}
