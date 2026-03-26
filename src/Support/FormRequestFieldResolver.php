<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Oxhq\Oxcribe\Data\RouteAction;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class FormRequestFieldResolver
{
    /**
     * @param  list<string>  $routeMethods
     * @param  array<string, mixed>|null  $controller
     * @return array<string, mixed>|null
     */
    public function augment(RouteAction $action, array $routeMethods, ?array $controller): ?array
    {
        if ($action->kind !== 'controller_method' || ! is_array($controller)) {
            return $controller;
        }

        $controllerClass = $action->fqcn;
        $controllerMethod = $action->method;
        if (! is_string($controllerClass) || $controllerClass === '' || ! is_string($controllerMethod) || $controllerMethod === '') {
            return $controller;
        }

        if (! class_exists($controllerClass) || ! method_exists($controllerClass, $controllerMethod)) {
            return $controller;
        }

        $fields = $this->resolveFields($controllerClass, $controllerMethod, $routeMethods);
        if ($fields === []) {
            return $controller;
        }

        $request = is_array($controller['request'] ?? null) ? $controller['request'] : [];
        $existing = is_array($request['fields'] ?? null) ? $request['fields'] : [];
        $request['fields'] = $this->mergeFields($existing, $fields);
        $controller['request'] = $request;

        return $controller;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveFields(string $controllerClass, string $controllerMethod, array $routeMethods): array
    {
        try {
            $method = new ReflectionMethod($controllerClass, $controllerMethod);
        } catch (\ReflectionException) {
            return [];
        }

        $fields = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (! is_subclass_of($typeName, FormRequest::class)) {
                continue;
            }

            $rules = $this->resolveRules($typeName);
            if ($rules === []) {
                continue;
            }

            foreach ($rules as $path => $definition) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $fields[] = $this->fieldFromRules($path, $definition, $routeMethods);
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRules(string $formRequestClass): array
    {
        try {
            /** @var FormRequest $instance */
            $instance = new $formRequestClass;
        } catch (\Throwable) {
            return [];
        }

        try {
            $rules = app()->bound('config')
                ? app()->call([$instance, 'rules'])
                : $instance->rules();
        } catch (\Throwable) {
            try {
                $rules = $instance->rules();
            } catch (\Throwable) {
                return [];
            }
        }

        return is_array($rules) ? $rules : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldFromRules(string $path, mixed $definition, array $routeMethods): array
    {
        $rules = $this->normalizeRules($definition);
        $location = $this->fieldLocation($rules, $routeMethods);
        $normalizedPath = $this->normalizePath($path);
        $type = 'string';
        $format = null;
        $kind = 'scalar';
        $scalarType = 'string';
        $allowedValues = [];
        $isArray = false;
        $collection = false;
        $itemType = null;

        foreach ($rules as $rule) {
            $name = Str::of($rule['name'])->lower()->value();
            $arguments = $rule['arguments'];

            match ($name) {
                'integer' => [$type, $scalarType] = ['integer', 'integer'],
                'numeric', 'decimal' => [$type, $scalarType] = ['number', 'number'],
                'boolean' => [$type, $scalarType] = ['boolean', 'boolean'],
                'array' => [$type, $kind, $isArray, $collection] = ['array', 'collection', true, true],
                'email' => $format = 'email',
                'uuid' => $format = 'uuid',
                'ulid' => $format = 'ulid',
                'url', 'active_url' => $format = 'uri',
                'date' => $format = 'date',
                'date_format', 'datetime' => $format = 'date-time',
                'file', 'image', 'mimes', 'mimetypes' => [$location, $kind, $type, $scalarType, $format] = ['files', 'file', 'file', 'string', 'binary'],
                'in' => $allowedValues = $arguments,
                default => null,
            };
        }

        if ($type === 'array' && $itemType === null) {
            $itemType = 'string';
        }

        $required = collect($rules)->contains(
            static fn (array $rule): bool => Str::startsWith($rule['name'], 'required')
        );
        $nullable = collect($rules)->contains(
            static fn (array $rule): bool => $rule['name'] === 'nullable'
        );

        return array_filter([
            'location' => $location,
            'path' => $normalizedPath,
            'kind' => $kind,
            'type' => $type,
            'scalarType' => $scalarType,
            'format' => $format,
            'required' => $required,
            'optional' => ! $required,
            'nullable' => $nullable,
            'allowedValues' => $allowedValues,
            'isArray' => $isArray,
            'collection' => $collection,
            'itemType' => $itemType,
            'source' => 'FormRequest::rules',
            'via' => 'runtime_rules',
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return list<array{name: string, arguments: list<string>}>
     */
    private function normalizeRules(mixed $definition): array
    {
        $entries = is_string($definition) ? explode('|', $definition) : (is_array($definition) ? $definition : []);

        $normalized = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $parts = explode(':', $entry, 2);
                $name = trim($parts[0]);
                if ($name === '') {
                    continue;
                }

                $arguments = isset($parts[1])
                    ? array_values(array_filter(array_map('trim', explode(',', $parts[1])), static fn (string $value): bool => $value !== ''))
                    : [];

                $normalized[] = [
                    'name' => $name,
                    'arguments' => $arguments,
                ];

                continue;
            }

            if (is_object($entry)) {
                $reflection = new ReflectionClass($entry);
                $shortName = Str::snake($reflection->getShortName());
                $arguments = [];

                if ($reflection->hasProperty('values')) {
                    $property = $reflection->getProperty('values');
                    $property->setAccessible(true);
                    $values = $property->getValue($entry);
                    if (is_array($values)) {
                        $arguments = array_values(array_filter(array_map(
                            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                            $values,
                        ), static fn (string $value): bool => $value !== ''));
                    }
                }

                $normalized[] = [
                    'name' => $shortName,
                    'arguments' => $arguments,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array{name: string, arguments: list<string>}>  $rules
     * @param  list<string>  $routeMethods
     */
    private function fieldLocation(array $rules, array $routeMethods): string
    {
        if (collect($rules)->contains(static fn (array $rule): bool => in_array($rule['name'], ['file', 'image', 'mimes', 'mimetypes'], true))) {
            return 'files';
        }

        $primaryMethod = strtoupper((string) ($routeMethods[0] ?? 'GET'));

        return in_array($primaryMethod, ['GET', 'HEAD'], true) ? 'query' : 'body';
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(['.*.', '.*'], ['[].', '[]'], trim($path));

        return trim($path, '.');
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeFields(array $existing, array $incoming): array
    {
        $merged = [];

        foreach ($existing as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = ($field['location'] ?? '')."\0".($field['path'] ?? '');
            $merged[$key] = $field;
        }

        foreach ($incoming as $field) {
            $key = ($field['location'] ?? '')."\0".($field['path'] ?? '');
            if (isset($merged[$key])) {
                continue;
            }

            $merged[$key] = $field;
        }

        ksort($merged);

        return array_values($merged);
    }
}
