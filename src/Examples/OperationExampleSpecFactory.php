<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\RouteBinding;
use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\OpenApi\Support\EffectiveRequestFieldLocation;
use Oxhq\Oxcribe\OpenApi\Support\RequestFieldIndex;
use Oxhq\Oxcribe\OpenApi\Support\ResourceSchemaIndex;

final readonly class OperationExampleSpecFactory
{
    public function __construct(
        private OperationKindResolver $operationKindResolver = new OperationKindResolver,
        private FieldClassifier $fieldClassifier = new FieldClassifier,
    ) {}

    public function make(MergedOperation $operation, ?ResourceSchemaIndex $resourceIndex = null): OperationExampleSpec
    {
        $endpoint = new EndpointExampleContext(
            method: strtoupper($operation->methods[0] ?? 'GET'),
            path: '/'.trim($operation->uri, '/'),
            routeName: $operation->name,
            actionKey: $operation->routeMatch->actionKey,
            operationKind: $this->operationKindResolver->resolve($operation),
        );

        $requestFieldIndex = RequestFieldIndex::fromController($operation->controller);
        $request = is_array($operation->controller['request'] ?? null) ? $operation->controller['request'] : [];
        $queryLocation = EffectiveRequestFieldLocation::query($operation, $request, $requestFieldIndex);
        $bodyLocation = EffectiveRequestFieldLocation::body($operation, $request, $requestFieldIndex);

        return new OperationExampleSpec(
            endpoint: $endpoint,
            pathParams: $this->buildPathParams($operation, $endpoint),
            queryParams: $this->buildRequestFieldsForLocation($requestFieldIndex, $request, $queryLocation, $endpoint),
            requestFields: array_merge(
                $bodyLocation !== null ? $this->buildRequestFieldsForLocation($requestFieldIndex, $request, $bodyLocation, $endpoint) : [],
                $this->buildRequestFieldsForLocation($requestFieldIndex, $request, 'files', $endpoint),
            ),
            responseFields: $this->buildResponseFields($operation, $endpoint, $resourceIndex),
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
     * @param  array<string, mixed>  $request
     * @return list<ExampleField>
     */
    private function buildRequestFieldsForLocation(RequestFieldIndex $index, array $request, string $location, EndpointExampleContext $endpoint): array
    {
        $fields = $this->buildRequestFields($index, $location, $endpoint);
        $legacyShape = $this->legacyRequestShape($request, $location);
        if ($legacyShape === []) {
            return $fields;
        }

        $existingRelativePaths = array_map(
            fn (ExampleField $field): string => $this->relativePath($field->path, $location),
            $fields,
        );

        $fallbackMetadata = $this->flattenLegacyShape($legacyShape);
        if ($fallbackMetadata === []) {
            return $fields;
        }

        $knownRelativePaths = array_values(array_unique(array_merge(
            array_filter($existingRelativePaths, static fn (string $path): bool => $path !== ''),
            array_keys($fallbackMetadata),
        )));
        sort($knownRelativePaths);
        $knownPaths = array_map(static fn (string $path): string => $location.'.'.$path, $knownRelativePaths);

        foreach ($fallbackMetadata as $path => $metadata) {
            if ($path === '' || in_array($path, $existingRelativePaths, true)) {
                continue;
            }

            $fields[] = $this->fieldClassifier->classify(
                path: $path,
                location: $location,
                metadata: $metadata,
                endpoint: $endpoint,
                knownPaths: $knownPaths,
            );
        }

        usort($fields, static fn (ExampleField $left, ExampleField $right): int => strcmp($left->path, $right->path));

        return $fields;
    }

    /**
     * @return list<ExampleField>
     */
    private function buildResponseFields(MergedOperation $operation, EndpointExampleContext $endpoint, ?ResourceSchemaIndex $resourceIndex = null): array
    {
        $schema = $this->primaryResponseSchema($operation, $resourceIndex);
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
    private function primaryResponseSchema(MergedOperation $operation, ?ResourceSchemaIndex $resourceIndex = null): ?array
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
                $schema = (array) $response['bodySchema'];
                if ($resourceIndex !== null) {
                    $expanded = $resourceIndex->expandedSchemaForNode($schema);
                    if ($expanded !== []) {
                        return $expanded;
                    }
                }

                return $schema;
            }

            if (is_array($response['inertia']['propsSchema'] ?? null)) {
                $schema = (array) $response['inertia']['propsSchema'];
                if ($resourceIndex !== null) {
                    $expanded = $resourceIndex->expandedSchemaForNode($schema);
                    if ($expanded !== []) {
                        return $expanded;
                    }
                }

                return $schema;
            }
        }

        if ($resourceIndex !== null) {
            return $resourceIndex->expandedResponseSchemaFor($this->primaryResourceUse($operation));
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

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function legacyRequestShape(array $request, string $location): array
    {
        return match ($location) {
            'query' => is_array($request['query'] ?? null) ? $request['query'] : [],
            'body' => is_array($request['body'] ?? null) ? $request['body'] : [],
            'files' => is_array($request['files'] ?? null) ? $request['files'] : [],
            default => [],
        };
    }

    private function relativePath(string $path, string $location): string
    {
        $prefix = $location.'.';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, array<string, mixed>>
     */
    private function flattenLegacyShape(array $shape): array
    {
        $fields = [];

        foreach ($shape as $name => $child) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $this->collectLegacyShapeFields((array) $child, $name, false, $fields);
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function collectLegacyShapeFields(array $node, string $path, bool $required, array &$fields): void
    {
        $properties = is_array($node['properties'] ?? null) ? (array) $node['properties'] : [];
        $requiredKeys = array_values(array_filter(
            array_map('strval', (array) ($node['required'] ?? [])),
            static fn (string $value): bool => $value !== '',
        ));

        if ($properties !== []) {
            foreach ($properties as $name => $child) {
                if (! is_array($child) || ! is_string($name) || $name === '') {
                    continue;
                }

                $this->collectLegacyShapeFields(
                    $child,
                    $path.'.'.$name,
                    in_array($name, $requiredKeys, true),
                    $fields,
                );
            }

            return;
        }

        if (is_array($node['items'] ?? null)) {
            $this->collectLegacyShapeFields((array) $node['items'], $path.'[]', true, $fields);

            return;
        }

        $type = $this->normalizedSchemaType($node);
        $format = is_string($node['format'] ?? null) ? (string) $node['format'] : null;
        $enum = is_array($node['enum'] ?? null) ? array_values(array_filter(array_map('strval', $node['enum']))) : [];
        $metadata = array_filter([
            'type' => $type,
            'scalarType' => $type === 'array' ? null : $type,
            'format' => $format,
            'required' => $required,
            'nullable' => $this->schemaNullable($node),
            'allowedValues' => $enum,
            'collection' => $type === 'array' || str_ends_with($path, '[]'),
            'isArray' => $type === 'array' || str_ends_with($path, '[]'),
            'itemType' => $this->responseItemType($node),
            'source' => 'request.shape',
            'via' => 'legacy_shape',
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $fields[$path] = $metadata;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryResourceUse(MergedOperation $operation): ?array
    {
        $resources = (array) ($operation->controller['resources'] ?? []);
        if ($resources === []) {
            return null;
        }

        $primary = $resources[0] ?? null;

        return is_array($primary) ? $primary : null;
    }
}
