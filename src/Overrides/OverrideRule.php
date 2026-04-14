<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Overrides;

use Oxhq\Oxcribe\Data\MergedOperation;

final readonly class OverrideRule
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $tags
     * @param  list<array<string, mixed>>  $security
     * @param  array<string, mixed>  $examples
     * @param  array<string, mixed>  $responses
     * @param  array<string, mixed>  $requestBody
     * @param  array<string, mixed>  $xOxcribe
     * @param  array<string, mixed>  $externalDocs
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public string $source,
        public ?string $routeId = null,
        public ?string $actionKey = null,
        public ?string $uri = null,
        public ?string $name = null,
        public ?string $prefix = null,
        public array $methods = [],
        public array $middleware = [],
        public bool $include = true,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $operationId = null,
        public array $tags = [],
        public ?bool $deprecated = null,
        public array $security = [],
        public array $examples = [],
        public array $responses = [],
        public array $requestBody = [],
        public array $xOxcribe = [],
        public array $externalDocs = [],
        public array $extensions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload, string $source): self
    {
        $match = is_array($payload['match'] ?? null) ? $payload['match'] : [];
        $selectors = array_merge($match, $payload);

        return new self(
            source: $source,
            routeId: self::stringValue($selectors['routeId'] ?? null),
            actionKey: self::stringValue($selectors['actionKey'] ?? null),
            uri: self::stringValue($selectors['uri'] ?? null),
            name: self::stringValue($selectors['name'] ?? null),
            prefix: self::stringValue($selectors['prefix'] ?? null),
            methods: self::stringList($selectors['methods'] ?? []),
            middleware: self::selectorList($selectors['middleware'] ?? []),
            include: array_key_exists('include', $payload) ? (bool) $payload['include'] : true,
            summary: self::stringValue($payload['summary'] ?? null),
            description: self::stringValue($payload['description'] ?? null),
            operationId: self::stringValue($payload['operationId'] ?? null),
            tags: self::stringList($payload['tags'] ?? []),
            deprecated: array_key_exists('deprecated', $payload) ? (bool) $payload['deprecated'] : null,
            security: self::nestedArrayList($payload['security'] ?? []),
            examples: is_array($payload['examples'] ?? null) ? $payload['examples'] : [],
            responses: is_array($payload['responses'] ?? null) ? $payload['responses'] : [],
            requestBody: is_array($payload['requestBody'] ?? null) ? $payload['requestBody'] : [],
            xOxcribe: is_array($payload['x-oxcribe'] ?? null) ? $payload['x-oxcribe'] : [],
            externalDocs: is_array($payload['externalDocs'] ?? null) ? $payload['externalDocs'] : [],
            extensions: is_array($payload['extensions'] ?? null) ? $payload['extensions'] : [],
        );
    }

    public function matches(MergedOperation $operation): bool
    {
        if ($this->routeId !== null && $operation->routeId !== $this->routeId) {
            return false;
        }

        if ($this->actionKey !== null && $operation->routeMatch->actionKey !== $this->actionKey) {
            return false;
        }

        if ($this->uri !== null && ! self::matchesPattern($this->normalizePath($operation->uri), $this->normalizePath($this->uri))) {
            return false;
        }

        if ($this->name !== null && ! self::matchesPattern((string) $operation->name, $this->name)) {
            return false;
        }

        if ($this->prefix !== null && ! self::matchesPattern((string) $operation->prefix, $this->prefix)) {
            return false;
        }

        if ($this->methods !== [] && ! $this->matchesMethods($operation->methods)) {
            return false;
        }

        if ($this->middleware !== [] && ! $this->matchesMiddleware($operation->middleware)) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<string>
     */
    private static function selectorList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return self::stringList($value);
    }

    private static function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function nestedArrayList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function matchesMethods(array $operationMethods): bool
    {
        $normalizedOperationMethods = array_map(static fn (string $method): string => strtoupper($method), $operationMethods);

        foreach ($this->methods as $method) {
            if (in_array(strtoupper($method), $normalizedOperationMethods, true)) {
                return true;
            }
        }

        return false;
    }

    private function matchesMiddleware(array $operationMiddleware): bool
    {
        foreach ($this->middleware as $pattern) {
            foreach ($operationMiddleware as $actual) {
                if (self::matchesPattern($actual, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function matchesPattern(string $value, string $pattern): bool
    {
        if ($pattern === '') {
            return true;
        }

        if (! str_contains($pattern, '*') && ! str_contains($pattern, '?')) {
            return $value === $pattern;
        }

        return fnmatch($pattern, $value, FNM_NOESCAPE);
    }

    private function normalizePath(string $value): string
    {
        return trim($value, '/');
    }
}
