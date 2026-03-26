<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

use Oxhq\Oxcribe\Examples\ExampleMode;

final readonly class ScenarioContext
{
    /**
     * @param  array<string, mixed>  $resources
     */
    public function __construct(
        public string $seed,
        public ExampleMode $mode,
        public ?ScenarioPerson $person = null,
        public ?ScenarioCompany $company = null,
        public ?ScenarioAuth $auth = null,
        public array $resources = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'seed' => $this->seed,
            'mode' => $this->mode->value,
            'person' => $this->person?->toArray(),
            'company' => $this->company?->toArray(),
            'auth' => $this->auth?->toArray(),
            'resources' => $this->resources,
        ];
    }
}
