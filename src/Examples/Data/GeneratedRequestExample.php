<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class GeneratedRequestExample
{
    /**
     * @param  array<string, mixed>  $pathParams
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public array $pathParams = [],
        public array $queryParams = [],
        public mixed $body = null,
        public array $headers = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pathParams' => $this->pathParams,
            'queryParams' => $this->queryParams,
            'body' => $this->body,
            'headers' => $this->headers,
        ];
    }
}
