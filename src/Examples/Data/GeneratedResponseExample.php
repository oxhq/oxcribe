<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class GeneratedResponseExample
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public ?int $status,
        public mixed $body = null,
        public array $headers = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'body' => $this->body,
            'headers' => $this->headers,
        ];
    }
}
