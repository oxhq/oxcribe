<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class ScenarioAuth
{
    public function __construct(
        public string $password,
        public string $token,
        public string $apiKey,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'password' => $this->password,
            'token' => $this->token,
            'apiKey' => $this->apiKey,
        ];
    }
}
