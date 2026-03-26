<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

use Oxhq\Oxcribe\Examples\ExampleMode;

final readonly class GeneratedOperationExample
{
    public function __construct(
        public ExampleMode $mode,
        public EndpointExampleContext $endpoint,
        public ScenarioContext $context,
        public GeneratedRequestExample $request,
        public GeneratedResponseExample $response,
        public SnippetSet $snippets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'endpoint' => $this->endpoint->toArray(),
            'context' => $this->context->toArray(),
            'request' => $this->request->toArray(),
            'response' => $this->response->toArray(),
            'snippets' => $this->snippets->toArray(),
        ];
    }
}
