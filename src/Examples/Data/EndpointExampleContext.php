<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class EndpointExampleContext
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $routeName,
        public ?string $actionKey,
        public string $operationKind,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'routeName' => $this->routeName,
            'actionKey' => $this->actionKey,
            'operationKind' => $this->operationKind,
        ];
    }
}
