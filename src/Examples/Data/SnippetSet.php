<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class SnippetSet
{
    public function __construct(
        public string $curl,
        public string $fetch,
        public string $axios,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'curl' => $this->curl,
            'fetch' => $this->fetch,
            'axios' => $this->axios,
        ];
    }
}
