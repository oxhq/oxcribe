<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\OpenApi\Support;

/**
 * Indexes the richer `request.fields` overlay emitted by oxinfer.
 *
 * The index is intentionally read-only and additive: it never mutates the
 * original payload, and it can be used as a fallback overlay on top of the
 * legacy `body`, `query`, and `files` trees.
 */
final class RequestFieldIndex
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $byLocation = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $byPath = [];

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function __construct(array $fields)
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $location = $this->stringValue($field['location'] ?? null);
            $path = $this->stringValue($field['path'] ?? null);
            if ($location === '' || $path === '') {
                continue;
            }

            $normalized = $this->normalizeField($field);
            $this->byLocation[$location][$path] = $normalized;
            $this->byPath[$this->key($location, $path)] = $normalized;
        }
    }

    /**
     * @param  array<string, mixed>|null  $controller
     */
    public static function fromController(?array $controller): self
    {
        $request = is_array($controller['request'] ?? null) ? $controller['request'] : [];
        $fields = is_array($request['fields'] ?? null) ? $request['fields'] : [];

        return new self($fields);
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    public static function fromRequest(?array $request): self
    {
        $fields = is_array($request['fields'] ?? null) ? $request['fields'] : [];

        return new self($fields);
    }

    public function has(string $location, string $path): bool
    {
        return isset($this->byPath[$this->key($location, $path)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $location, string $path): ?array
    {
        return $this->byPath[$this->key($location, $path)] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $fields = array_values($this->byPath);
        usort($fields, static function (array $left, array $right): int {
            $locationComparison = strcmp((string) ($left['location'] ?? ''), (string) ($right['location'] ?? ''));
            if ($locationComparison !== 0) {
                return $locationComparison;
            }

            return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForLocation(string $location): array
    {
        $fields = array_values($this->byLocation[$location] ?? []);
        usort($fields, static function (array $left, array $right): int {
            return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return $fields;
    }

    /**
     * @return list<string>
     */
    public function allowedValues(string $location, string $path): array
    {
        $field = $this->get($location, $path);
        if (! is_array($field)) {
            return [];
        }

        $values = $field['allowedValues'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? $value : '',
            $values,
        )));
    }

    public function hasDescendant(string $location, string $prefix): bool
    {
        foreach ($this->allForLocation($location) as $field) {
            $path = (string) ($field['path'] ?? '');
            if ($path === $prefix || str_starts_with($path, $prefix.'.') || str_starts_with($path, $prefix.'[')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function descendants(string $location, string $prefix): array
    {
        $descendants = [];
        foreach ($this->allForLocation($location) as $field) {
            $path = (string) ($field['path'] ?? '');
            if ($path === $prefix || str_starts_with($path, $prefix.'.') || str_starts_with($path, $prefix.'[')) {
                $descendants[] = $field;
            }
        }

        return $descendants;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeField(array $field): array
    {
        $field['location'] = $this->stringValue($field['location'] ?? null);
        $field['path'] = $this->stringValue($field['path'] ?? null);
        $field['kind'] = $this->stringValue($field['kind'] ?? null);
        $field['type'] = $this->stringValue($field['type'] ?? null);
        $field['scalarType'] = $this->stringValue($field['scalarType'] ?? null);
        $field['format'] = $this->stringValue($field['format'] ?? null);
        $field['itemType'] = $this->stringValue($field['itemType'] ?? null);
        $field['source'] = $this->stringValue($field['source'] ?? null);
        $field['via'] = $this->stringValue($field['via'] ?? null);
        $field['wrappers'] = $this->stringList($field['wrappers'] ?? null);
        $field['allowedValues'] = $this->stringList($field['allowedValues'] ?? null);
        $field['required'] = $this->boolValue($field['required'] ?? null);
        $field['optional'] = $this->boolValue($field['optional'] ?? null);
        $field['nullable'] = $this->boolValue($field['nullable'] ?? null);
        $field['isArray'] = $this->boolValue($field['isArray'] ?? null);
        $field['collection'] = $this->boolValue($field['collection'] ?? null);

        return $field;
    }

    private function key(string $location, string $path): string
    {
        return $location."\0".$path;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        $items = array_values(array_unique($items));
        sort($items);

        return $items;
    }

    private function boolValue(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
