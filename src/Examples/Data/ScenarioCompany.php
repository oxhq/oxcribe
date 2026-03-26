<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class ScenarioCompany
{
    public function __construct(
        public string $name,
        public string $email,
        public string $website,
        public string $domain,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'website' => $this->website,
            'domain' => $this->domain,
        ];
    }
}
