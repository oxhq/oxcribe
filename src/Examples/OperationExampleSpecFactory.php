<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Examples;

use Garaekz\Oxcribe\Data\MergedOperation;
use Garaekz\Oxcribe\Data\RouteBinding;
use Garaekz\Oxcribe\Examples\Data\EndpointExampleContext;
use Garaekz\Oxcribe\Examples\Data\ExampleField;
use Garaekz\Oxcribe\Examples\Data\OperationExampleSpec;
use Garaekz\Oxcribe\OpenApi\Support\RequestFieldIndex;

final readonly class OperationExampleSpecFactory
{
    public function __construct(
        private OperationKindResolver $operationKindResolver = new OperationKindResolver(),
        private FieldClassifier $fieldClassifier = new FieldClassifier(),
    ) {
    }

    public function make(MergedOperation $operation): OperationExampleSpec
    {
        $endpoint = new EndpointExampleContext(
            method: strtoupper($operation->methods[0] ?? 'GET'),
            path: '/'.trim($operation->uri, '/'),
            routeName: $operation->name,
            actionKey: $operation->routeMatch->actionKey,
            operationKind: $this->operationKindResolver->resolve($operation),
        );

        $requestFieldIndex = RequestFieldIndex::fromController($operation->controller);

        return new OperationExampleSpec(
            endpoint: $endpoint,
            pathParams: $this->buildPathParams($operation, $endpoint),
            queryParams: $this->buildRequestFields($requestFieldIndex, 'query', $endpoint),
            requestFields: array_merge(
                $this->buildRequestFields($requestFieldIndex, 'body', $endpoint),
                $this->buildRequestFields($requestFieldIndex, 'files', $endpoint),
            ),
            responseFields: $this->buildResponseFields($operation, $endpoint),
            responseStatuses: $this->responseStatuses($operation),
        );
    }

    /**
     * @return list<ExampleField>
     */
    private function buildPathParams(MergedOperation $operation, EndpointExampleContext $endpoint): array
    {
        preg_match_all('/\{([^}]+)\}/', $operation->uri, $matches);
        $parameters = array_values(array_unique($matches[1] ?? []));
        $bindingMap = [];
        foreach ($operation->bindings as $binding) {
            if (! $binding instanceof RouteBinding) {
                continue;
            }
            $bindingMap[$binding->parameter] = $binding;
        }

        $fields = [];
        foreach ($parameters as $parameter) {
            $binding = $bindingMap[$parameter] ?? null;
            $pattern = is_string($operation->where[$parameter] ?? null) ? (string) $operation->where[$parameter] : '';
            $fields[] = $this->fieldClassifier->classify(
                path: $parameter,
                location: 'path',
                metadata: [
                    'type' => $this->looksNumericPattern($pattern) ? 'integer' : 'string',
                    'required' => true,
                    'nullable' => false,
                    'bindingTarget' => $binding?->targetFqcn,
                    'source' => $binding !== null ? 'route.binding' : 'route.parameter',
                    'via' => $binding !== null ? $binding->kind : 'uri',
                ],
                endpoint: $endpoint,
            );
        }

        return $fields;
    }

    /**
     * @return list<ExampleField>
     */
    private function buildRequestFields(RequestFieldIndex $index, string $location, EndpointExampleContext $endpoint): array
    {
        $knownPaths = array_map(
            static fn (array $field): string => $location.'.'.(string) ($field['path'] ?? ''),
            $index->allForLocation($location),
        );

        $fields = [];
        foreach ($index->allForLocation($location) as $field) {
            $path = trim((string) ($field['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $fields[] = $this->fieldClassifier->classify(
                path: $path,
                location: $location,
                metadata: $field,
                endpoint: $endpoint,
                knownPaths: $knownPaths,
            );
        }

        return $fields;
    }

    /**
     * @return list<ExampleField>
     */
    private function buildResponseFields(MergedOperation $operation, EndpointExampleContext $endpoint): array
    {
        $schema = $this->primaryResponseSchema($operation);
        if ($schema === null) {
            return [];
        }

        $fields = [];
        $this->flattenResponseSchema($schema, '', false, $endpoint, $fields);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<ExampleField>  $fields
     */
    private function flattenResponseSchema(array $schema, string $prefix, bool $required, EndpointExampleContext $endpoint, array &$fields): void
    {
        $type = $this->normalizedSchemaType($schema);
        $nullable = $this->schemaNullable($schema);

        if ($prefix !== '') {
            $metadata = [
                'type' => $type,
                'format' => is_string($schema['format'] ?? null) ? (string) $schema['format'] : null,
                'required' => $required,
                'nullable' => $nullable,
                'collection' => $type === 'array',
                'isArray' => $type === 'array',
                'itemType' => $this->responseItemType($schema),
                'source' => 'response.schema',
                'via' => 'bodySchema',
            ];
            if (is_string($schema['ref'] ?? null) && $schema['ref'] !== '') {
                $metadata['bindingTarget'] = $schema['ref'];
            }

            $fields[] = $this->fieldClassifier->classify(
                path: $prefix,
                location: 'response',
                metadata: $metadata,
                endpoint: $endpoint,
            );
        }

        if ($type === 'object') {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            $requiredKeys = array_values(array_filter(
                array_map('strval', (array) ($schema['required'] ?? [])),
                static fn (string $value): bool => $value !== '',
            ));
            sort($requiredKeys);
            ksort($properties);

            foreach ($properties as $name => $child) {
                if (! is_array($child)) {
                    continue;
                }

                $childPath = $prefix === '' ? (string) $name : $prefix.'.'.$name;
                $this->flattenResponseSchema($child, $childPath, in_array((string) $name, $requiredKeys, true), $endpoint, $fields);
            }

            return;
        }

        if ($type === 'array' && is_array($schema['items'] ?? null)) {
            $this->flattenResponseSchema((array) $schema['items'], $prefix.'[]', true, $endpoint, $fields);
        }
    }

    /**
     * @return list<int>
     */
    private function responseStatuses(MergedOperation $operation): array
    {
        $statuses = [];
        foreach ((array) ($operation->controller['responses'] ?? []) as $response) {
            if (! is_array($response)) {
                continue;
            }
            $status = $response['status'] ?? null;
            if (is_int($status) || is_numeric($status)) {
                $statuses[] = (int) $status;
            }
        }

        if ($statuses === []) {
            $status = $operation->controller['http']['status'] ?? null;
            if (is_int($status) || is_numeric($status)) {
                $statuses[] = (int) $status;
            }
        }

        $statuses = array_values(array_unique($statuses));
        sort($statuses);

        return $statuses;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryResponseSchema(MergedOperation $operation): ?array
    {
        foreach ((array) ($operation->controller['responses'] ?? []) as $response) {
            if (! is_array($response)) {
                continue;
            }

            $status = (int) ($response['status'] ?? $operation->controller['http']['status'] ?? 0);
            if ($status < 200 || $status >= 300) {
                continue;
            }

            if (is_array($response['bodySchema'] ?? null)) {
                return (array) $response['bodySchema'];
            }

            if (is_array($response['inertia']['propsSchema'] ?? null)) {
                return (array) $response['inertia']['propsSchema'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function normalizedSchemaType(array $schema): string
    {
        $type = $schema['type'] ?? null;
        if (is_string($type) && $type !== '') {
            return $type;
        }
        if (is_array($type)) {
            $types = array_values(array_filter(
                array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $type),
                static fn (string $value): bool => $value !== '' && $value !== 'null',
            ));
            if ($types !== []) {
                return $types[0];
            }
        }
        if (is_array($schema['properties'] ?? null)) {
            return 'object';
        }
        if (is_array($schema['items'] ?? null)) {
            return 'array';
        }
        if (is_string($schema['ref'] ?? null) && $schema['ref'] !== '') {
            return 'object';
        }

        return 'string';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function schemaNullable(array $schema): bool
    {
        if (($schema['nullable'] ?? false) === true) {
            return true;
        }

        $type = $schema['type'] ?? null;
        if (! is_array($type)) {
            return false;
        }

        return in_array('null', array_map('strval', $type), true);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function responseItemType(array $schema): ?string
    {
        if (! is_array($schema['items'] ?? null)) {
            return null;
        }

        $items = (array) $schema['items'];
        if (is_string($items['ref'] ?? null) && $items['ref'] !== '') {
            return (string) $items['ref'];
        }

        $type = $this->normalizedSchemaType($items);

        return $type !== '' ? $type : null;
    }

    private function looksNumericPattern(string $pattern): bool
    {
        $normalized = trim($pattern);
        $normalized = preg_replace('/^\^|\$$/', '', $normalized) ?? $normalized;

        return in_array($normalized, ['[0-9]+', '\d+', '[1-9][0-9]*'], true);
    }
}
