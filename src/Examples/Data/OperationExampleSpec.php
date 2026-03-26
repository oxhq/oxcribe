<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class OperationExampleSpec
{
    /**
     * @param  list<ExampleField>  $pathParams
     * @param  list<ExampleField>  $queryParams
     * @param  list<ExampleField>  $requestFields
     * @param  list<ExampleField>  $responseFields
     * @param  list<int>  $responseStatuses
     */
    public function __construct(
        public EndpointExampleContext $endpoint,
        public array $pathParams = [],
        public array $queryParams = [],
        public array $requestFields = [],
        public array $responseFields = [],
        public array $responseStatuses = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $statuses = array_values(array_unique($this->responseStatuses));
        sort($statuses);

        return [
            'endpoint' => $this->endpoint->toArray(),
            'pathParams' => array_map(static fn (ExampleField $field): array => $field->toArray(), $this->pathParams),
            'queryParams' => array_map(static fn (ExampleField $field): array => $field->toArray(), $this->queryParams),
            'requestFields' => array_map(static fn (ExampleField $field): array => $field->toArray(), $this->requestFields),
            'responseFields' => array_map(static fn (ExampleField $field): array => $field->toArray(), $this->responseFields),
            'responseStatuses' => $statuses,
        ];
    }
}
