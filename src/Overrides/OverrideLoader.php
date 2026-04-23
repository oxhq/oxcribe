<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Overrides;

use InvalidArgumentException;

final class OverrideLoader
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @param  list<string>  $overrideFiles
     */
    public function load(?string $projectRoot = null, array $overrideFiles = []): OverrideSet
    {
        $overridesConfig = (array) ($this->config['overrides'] ?? []);
        if (! (bool) ($overridesConfig['enabled'] ?? true)) {
            return new OverrideSet;
        }

        $resolvedProjectRoot = $projectRoot ?? base_path();
        $rules = [];
        $sources = [];

        foreach ($this->buildVisibilityRules() as $rule) {
            $rules[] = $rule;
        }

        if ($rules !== []) {
            $sources[] = 'config:visibility';
        }

        foreach ($this->buildRulesFromPayload($overridesConfig['defaults'] ?? null, 'config:overrides.defaults') as $rule) {
            $rules[] = $rule;
        }

        foreach ($this->buildRulesFromPayload($overridesConfig['routes'] ?? null, 'config:overrides.routes') as $rule) {
            $rules[] = $rule;
        }

        $sources[] = 'config';

        $candidateFiles = array_merge(
            $this->configFiles($overridesConfig),
            $overrideFiles,
        );

        foreach ($this->resolveFiles($resolvedProjectRoot, $candidateFiles) as $file) {
            $payload = require $file;
            if (! is_array($payload)) {
                throw new InvalidArgumentException(sprintf('Override file "%s" must return an array.', $file));
            }

            $sourceFile = $this->normalizeSourceId($file);

            $sources[] = $sourceFile;

            foreach ($this->buildRulesFromPayload($payload['defaults'] ?? null, $sourceFile.'#defaults') as $rule) {
                $rules[] = $rule;
            }

            $routes = $this->routePayloadsFromFile($payload);
            foreach ($routes as $index => $routePayload) {
                $rules[] = OverrideRule::fromArray($routePayload, $sourceFile.'#routes['.$index.']');
            }
        }

        return new OverrideSet($rules, array_values(array_unique($sources)));
    }

    /**
     * @param  array<string, mixed>  $overridesConfig
     * @return list<string>
     */
    private function configFiles(array $overridesConfig): array
    {
        $files = [];
        foreach ((array) ($overridesConfig['files'] ?? []) as $file) {
            if (is_string($file) && $file !== '') {
                $files[] = $file;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function resolveFiles(string $projectRoot, array $files): array
    {
        $resolved = [];
        foreach ($files as $file) {
            $candidate = $this->resolveFilePath($projectRoot, $file);
            if (is_file($candidate)) {
                $resolved[] = $candidate;
            }
        }

        return array_values(array_unique($resolved));
    }

    private function resolveFilePath(string $projectRoot, string $file): string
    {
        if ($file === '') {
            return $file;
        }

        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $file) === 1) {
            return $file;
        }

        return rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($file, DIRECTORY_SEPARATOR);
    }

    private function normalizeSourceId(string $source): string
    {
        return str_replace('\\', '/', $source);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routePayloadsFromFile(array $payload): array
    {
        if (array_key_exists('routes', $payload) && is_array($payload['routes'])) {
            return array_values(array_filter($payload['routes'], static fn (mixed $item): bool => is_array($item)));
        }

        if ($this->looksLikeRule($payload)) {
            return [$payload];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item)));
        }

        return [];
    }

    /**
     * @return list<OverrideRule>
     */
    private function buildRulesFromPayload(mixed $payload, string $source): array
    {
        if ($payload === null) {
            return [];
        }

        if (is_array($payload) && $this->looksLikeRule($payload)) {
            return [OverrideRule::fromArray($payload, $source)];
        }

        if (! is_array($payload)) {
            return [];
        }

        $rules = [];
        foreach (array_values($payload) as $index => $item) {
            if (is_array($item) && $this->looksLikeRule($item)) {
                $rules[] = OverrideRule::fromArray($item, $source.'['.$index.']');
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function looksLikeRule(array $payload): bool
    {
        foreach (['routeId', 'actionKey', 'uri', 'name', 'prefix', 'methods', 'middleware', 'tags', 'summary', 'description', 'operationId', 'deprecated', 'security', 'examples', 'responses', 'requestBody', 'x-oxcribe', 'externalDocs', 'extensions'] as $key) {
            if (array_key_exists($key, $payload) && $this->hasMeaningfulValue($payload[$key])) {
                return true;
            }
        }

        return array_key_exists('include', $payload);
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if (is_string($value)) {
            return $value !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }

    /**
     * @return list<OverrideRule>
     */
    private function buildVisibilityRules(): array
    {
        $visibility = is_array($this->config['visibility'] ?? null) ? $this->config['visibility'] : [];
        $mode = $this->normalizeVisibilityMode($visibility['mode'] ?? null);
        $includeMiddleware = $this->selectorList($visibility['include_middleware'] ?? []);
        $excludeMiddleware = $this->selectorList($visibility['exclude_middleware'] ?? []);

        if ($mode === 'all' && $excludeMiddleware === []) {
            return [];
        }

        $rules = [];

        if ($mode === 'only_marked') {
            $rules[] = OverrideRule::fromArray([
                'match' => [
                    'uri' => '*',
                ],
                'include' => false,
            ], 'config:visibility[exclude-all]');

            foreach ($includeMiddleware as $index => $middleware) {
                $rules[] = OverrideRule::fromArray([
                    'match' => [
                        'middleware' => $middleware,
                    ],
                    'include' => true,
                ], 'config:visibility[include]['.$index.']');
            }
        }

        foreach ($excludeMiddleware as $index => $middleware) {
            $rules[] = OverrideRule::fromArray([
                'match' => [
                    'middleware' => $middleware,
                ],
                'include' => false,
            ], 'config:visibility[exclude]['.$index.']');
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function selectorList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')));
    }

    private function normalizeVisibilityMode(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'all';
        }

        return match (strtolower($value)) {
            'only_marked', 'marked', 'allowlist', 'whitelist' => 'only_marked',
            default => 'all',
        };
    }
}
