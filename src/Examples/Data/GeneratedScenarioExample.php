<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class GeneratedScenarioExample
{
    public function __construct(
        public ExampleScenario $scenario,
        public GeneratedOperationExample $example,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            $this->scenario->toArray(),
            [
                'endpoint' => $this->example->endpoint->toArray(),
                'context' => $this->example->context->toArray(),
                'request' => $this->example->request->toArray(),
                'response' => $this->example->response->toArray(),
                'snippets' => $this->example->snippets->toArray(),
            ],
        );
    }
}
