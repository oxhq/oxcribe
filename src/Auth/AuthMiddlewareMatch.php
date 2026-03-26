<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Auth;

final readonly class AuthMiddlewareMatch
{
    /**
     * @param  list<string>  $values
     * @param  list<string>  $guards
     * @param  list<string>  $schemeCandidates
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $source,
        public string $category,
        public string $kind,
        public array $values,
        public array $guards,
        public array $schemeCandidates,
        public ?string $subject = null,
        public ?string $ability = null,
        public string $resolution = 'direct',
        public array $metadata = [],
    ) {}

    /**
     * @return array{source: string, category: string, kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}
     */
    public function toArray(): array
    {
        return array_filter([
            'source' => $this->source,
            'category' => $this->category,
            'kind' => $this->kind,
            'values' => array_values($this->values),
            'guards' => array_values($this->guards),
            'schemeCandidates' => array_values($this->schemeCandidates),
            'subject' => $this->subject,
            'ability' => $this->ability,
            'resolution' => $this->resolution,
            'metadata' => $this->metadata !== [] ? $this->metadata : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
