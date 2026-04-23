<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\OpenApi\Support;

final class ResourceSchemaIndex
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $byFqcn = [];

    /**
     * @var array<string, string>
     */
    private array $componentNames = [];

    /**
     * @param  array<int, array<string, mixed>>  $resources
     */
    public function __construct(array $resources)
    {
        foreach ($resources as $resource) {
            $fqcn = trim((string) ($resource['fqcn'] ?? ''));
            if ($fqcn === '') {
                continue;
            }

            $this->byFqcn[$fqcn] = $resource;
        }

        $this->componentNames = $this->buildComponentNames(array_keys($this->byFqcn));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentSchemas(): array
    {
        $schemas = [];

        foreach ($this->byFqcn as $fqcn => $resource) {
            $componentName = $this->componentName($fqcn);
            if ($componentName === null) {
                continue;
            }

            $schema = $this->convertNode((array) ($resource['schema'] ?? []));
            if ($schema === []) {
                continue;
            }

            $schemas[$componentName] = $schema;
        }

        ksort($schemas);

        return $schemas;
    }

    /**
     * @param  array<string, mixed>|null  $resourceUse
     * @return array<string, mixed>|null
     */
    public function responseSchemaFor(?array $resourceUse): ?array
    {
        if (! is_array($resourceUse)) {
            return null;
        }

        $fqcn = trim((string) ($resourceUse['fqcn'] ?? ''));
        if ($fqcn === '') {
            return null;
        }

        $schema = $this->referenceSchema($fqcn);
        if ($schema === null) {
            return null;
        }

        if (($resourceUse['collection'] ?? false) !== true) {
            return $schema;
        }

        if (str_ends_with($this->shortTypeName($fqcn), 'Collection')) {
            return $schema;
        }

        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $schema,
                ],
            ],
            'required' => ['data'],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function schemaForNode(array $node): array
    {
        return $this->convertNode($node);
    }

    /**
     * Expands resource refs inline so downstream generators can materialize
     * concrete example payloads instead of opaque string placeholders.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function expandedSchemaForNode(array $node): array
    {
        return $this->expandNode($node);
    }

    /**
     * @param  array<string, mixed>|null  $resourceUse
     * @return array<string, mixed>|null
     */
    public function expandedResponseSchemaFor(?array $resourceUse): ?array
    {
        if (! is_array($resourceUse)) {
            return null;
        }

        $fqcn = trim((string) ($resourceUse['fqcn'] ?? ''));
        if ($fqcn === '') {
            return null;
        }

        $schema = $this->expandRef($fqcn);
        if ($schema === null) {
            return null;
        }

        if (($resourceUse['collection'] ?? false) !== true) {
            return $schema;
        }

        if (str_ends_with($this->shortTypeName($fqcn), 'Collection')) {
            return $schema;
        }

        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $schema,
                ],
            ],
            'required' => ['data'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function referenceSchema(string $fqcn): ?array
    {
        $componentName = $this->componentName($fqcn);
        if ($componentName === null) {
            return null;
        }

        return ['$ref' => '#/components/schemas/'.$componentName];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function convertNode(array $node): array
    {
        $ref = $this->nodeRef($node);
        if ($ref !== '') {
            $schema = $this->referenceSchema($ref);
            if ($schema === null) {
                return [];
            }

            if (($node['nullable'] ?? false) === true) {
                return [
                    'anyOf' => [
                        $schema,
                        ['type' => 'null'],
                    ],
                ];
            }

            return $schema;
        }

        $schema = [];
        $type = trim((string) ($node['type'] ?? ''));
        if ($type !== '') {
            $schema['type'] = $type;
        }

        $format = trim((string) ($node['format'] ?? ''));
        if ($format !== '') {
            $schema['format'] = $format;
        }

        $properties = [];
        foreach ((array) ($node['properties'] ?? []) as $name => $propertyNode) {
            if (! is_array($propertyNode)) {
                continue;
            }

            $converted = $this->convertNode($propertyNode);
            if ($converted === []) {
                continue;
            }

            $properties[(string) $name] = $converted;
        }
        if ($properties !== []) {
            ksort($properties);
            $schema['properties'] = $properties;
        }

        $required = array_values(array_filter(
            (array) ($node['required'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        if ($required !== []) {
            sort($required);
            $schema['required'] = $required;
        }

        if (is_array($node['items'] ?? null)) {
            $items = $this->convertNode((array) $node['items']);
            if ($items !== []) {
                $schema['items'] = $items;
            }
        }

        if (($node['nullable'] ?? false) === true) {
            if (isset($schema['type']) && is_string($schema['type'])) {
                $schema['type'] = [$schema['type'], 'null'];
            } elseif ($schema !== []) {
                $schema = [
                    'anyOf' => [
                        $schema,
                        ['type' => 'null'],
                    ],
                ];
            }
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $resolving
     * @return array<string, mixed>
     */
    private function expandNode(array $node, array $resolving = []): array
    {
        $ref = $this->nodeRef($node);
        if ($ref !== '') {
            $expanded = $this->expandRef($ref, $resolving);
            if ($expanded === null) {
                return [];
            }

            if (($node['nullable'] ?? false) === true) {
                $expanded['nullable'] = true;
            }

            $expanded['ref'] = $ref;

            return $expanded;
        }

        $schema = [];
        $type = trim((string) ($node['type'] ?? ''));
        if ($type !== '') {
            $schema['type'] = $type;
        }

        $format = trim((string) ($node['format'] ?? ''));
        if ($format !== '') {
            $schema['format'] = $format;
        }

        $properties = [];
        foreach ((array) ($node['properties'] ?? []) as $name => $propertyNode) {
            if (! is_array($propertyNode)) {
                continue;
            }

            $expanded = $this->expandNode($propertyNode, $resolving);
            if ($expanded === []) {
                continue;
            }

            $properties[(string) $name] = $expanded;
        }
        if ($properties !== []) {
            ksort($properties);
            $schema['properties'] = $properties;
        }

        $required = array_values(array_filter(
            (array) ($node['required'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        if ($required !== []) {
            sort($required);
            $schema['required'] = $required;
        }

        if (is_array($node['items'] ?? null)) {
            $items = $this->expandNode((array) $node['items'], $resolving);
            if ($items !== []) {
                $schema['items'] = $items;
            }
        }

        if (($node['nullable'] ?? false) === true) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    private function nodeRef(array $node): string
    {
        foreach (['ref', '$ref'] as $key) {
            $ref = trim((string) ($node[$key] ?? ''));
            if ($ref !== '') {
                return $ref;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $resolving
     * @return array<string, mixed>|null
     */
    private function expandRef(string $fqcn, array $resolving = []): ?array
    {
        if (in_array($fqcn, $resolving, true)) {
            return ['type' => 'object'];
        }

        $resource = $this->byFqcn[$fqcn] ?? null;
        if (! is_array($resource)) {
            return null;
        }

        $schema = $this->expandNode((array) ($resource['schema'] ?? []), [...$resolving, $fqcn]);
        if ($schema === []) {
            return null;
        }

        return $schema;
    }

    /**
     * @param  list<string>  $fqcns
     * @return array<string, string>
     */
    private function buildComponentNames(array $fqcns): array
    {
        sort($fqcns);

        $counts = [];
        foreach ($fqcns as $fqcn) {
            $short = $this->shortTypeName($fqcn);
            $counts[$short] = ($counts[$short] ?? 0) + 1;
        }

        $componentNames = [];
        foreach ($fqcns as $fqcn) {
            $short = $this->shortTypeName($fqcn);
            if (($counts[$short] ?? 0) === 1) {
                $componentNames[$fqcn] = $short;

                continue;
            }

            $componentNames[$fqcn] = str_replace('\\', '.', ltrim($fqcn, '\\'));
        }

        return $componentNames;
    }

    private function componentName(string $fqcn): ?string
    {
        return $this->componentNames[$fqcn] ?? null;
    }

    private function shortTypeName(string $fqcn): string
    {
        $fqcn = ltrim(trim($fqcn), '\\');
        if ($fqcn === '') {
            return '';
        }

        $segments = explode('\\', $fqcn);

        return trim((string) end($segments));
    }
}
