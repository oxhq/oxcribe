<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\ExampleScenario;
use Oxhq\Oxcribe\Examples\Data\GeneratedOperationExample;
use Oxhq\Oxcribe\Examples\Data\GeneratedRequestExample;
use Oxhq\Oxcribe\Examples\Data\GeneratedResponseExample;
use Oxhq\Oxcribe\Examples\Data\GeneratedScenarioExample;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\Examples\Data\ScenarioContext;

final readonly class OperationExampleGenerator
{
    public function __construct(
        private ScenarioContextFactory $scenarioContextFactory = new ScenarioContextFactory,
        private DeterministicValueGenerator $valueGenerator = new DeterministicValueGenerator,
        private SnippetFactory $snippetFactory = new SnippetFactory,
        private ScenarioResolver $scenarioResolver = new ScenarioResolver,
    ) {}

    public function generate(
        OperationExampleSpec $spec,
        string $projectSeed,
        ExampleMode $mode,
        string $baseUrl = 'https://api.example.test',
        ?string $bearerToken = null,
        ?ExampleScenario $scenario = null,
    ): GeneratedOperationExample {
        $scenarioSeed = $scenario !== null ? $projectSeed.'|scenario:'.$scenario->key : $projectSeed;
        $context = $this->scenarioContextFactory->make($scenarioSeed, $spec->endpoint, $mode);
        $request = new GeneratedRequestExample(
            pathParams: $this->buildPayloadMap($spec->pathParams, $context, $mode, $spec->endpoint),
            queryParams: $this->buildPayloadMap($spec->queryParams, $context, $mode, $spec->endpoint),
            body: $this->buildBodyPayload($spec->requestFields, $context, $mode, $spec->endpoint, $scenario?->arrayCount),
            headers: [
                'Accept' => 'application/json',
            ],
        );
        $response = new GeneratedResponseExample(
            status: $this->responseStatus($spec),
            body: $this->buildBodyPayload($spec->responseFields, $context, $mode, $spec->endpoint, $scenario?->arrayCount),
        );
        $snippets = $this->snippetFactory->make($spec, $request, $baseUrl, $bearerToken);

        return new GeneratedOperationExample(
            mode: $mode,
            endpoint: $spec->endpoint,
            context: $context,
            request: $request,
            response: $response,
            snippets: $snippets,
        );
    }

    /**
     * @return array<string, GeneratedOperationExample>
     */
    public function generateAll(OperationExampleSpec $spec, string $projectSeed, string $baseUrl = 'https://api.example.test', ?string $bearerToken = null): array
    {
        $examples = [];
        foreach (ExampleMode::cases() as $mode) {
            $examples[$mode->value] = $this->generate($spec, $projectSeed, $mode, $baseUrl, $bearerToken);
        }

        return $examples;
    }

    /**
     * @return array<string, array<string, GeneratedScenarioExample>>
     */
    public function generateScenarios(OperationExampleSpec $spec, string $projectSeed, string $baseUrl = 'https://api.example.test', ?string $bearerToken = null): array
    {
        $definitions = $this->scenarioResolver->resolve($spec);
        if ($definitions === []) {
            return [];
        }

        $scenarios = [];
        foreach (ExampleMode::cases() as $mode) {
            foreach ($definitions as $definition) {
                $scenarios[$mode->value][$definition->key] = new GeneratedScenarioExample(
                    scenario: $definition,
                    example: $this->generate($spec, $projectSeed, $mode, $baseUrl, $bearerToken, $definition),
                );
            }
        }

        return $scenarios;
    }

    /**
     * @param  list<ExampleField>  $fields
     * @return array<string, mixed>
     */
    private function buildPayloadMap(array $fields, ScenarioContext $context, ExampleMode $mode, EndpointExampleContext $endpoint): array
    {
        $payload = [];
        foreach ($this->filteredFields($fields, $mode) as $field) {
            $payload[$field->name] = $this->valueGenerator->generate($field, $context, endpoint: $endpoint);
        }
        ksort($payload);

        return $payload;
    }

    /**
     * @param  list<ExampleField>  $fields
     */
    private function buildBodyPayload(array $fields, ScenarioContext $context, ExampleMode $mode, EndpointExampleContext $endpoint, ?int $arrayCountOverride = null): mixed
    {
        $payload = [];
        $filtered = $this->filteredFields($fields, $mode);
        if ($filtered === []) {
            return null;
        }

        foreach ($filtered as $field) {
            if ($this->hasDescendants($field, $filtered)) {
                continue;
            }

            $relativePath = $this->relativeFieldPath($field);
            if ($relativePath === '') {
                continue;
            }

            $this->assignPathValue($payload, $relativePath, $field, $context, $arrayCountOverride ?? $this->arrayCount($mode), $endpoint);
        }

        return $payload === [] ? null : $payload;
    }

    /**
     * @param  list<ExampleField>  $fields
     * @return list<ExampleField>
     */
    private function filteredFields(array $fields, ExampleMode $mode): array
    {
        usort($fields, static function (ExampleField $left, ExampleField $right): int {
            return strcmp($left->path, $right->path);
        });

        return array_values(array_filter($fields, fn (ExampleField $field): bool => $this->shouldIncludeField($field, $mode)));
    }

    private function shouldIncludeField(ExampleField $field, ExampleMode $mode): bool
    {
        if ($field->required) {
            return true;
        }

        if ($field->location === 'query') {
            return match ($mode) {
                ExampleMode::MinimalValid => false,
                default => true,
            };
        }

        return match ($mode) {
            ExampleMode::MinimalValid => false,
            ExampleMode::HappyPath => $field->hints->confidence >= 0.8 || $field->collection || $field->allowedValues !== [],
            ExampleMode::RealisticFull => $field->hints->confidence >= 0.55 || $field->collection || $field->allowedValues !== [],
        };
    }

    /**
     * @param  list<ExampleField>  $fields
     */
    private function hasDescendants(ExampleField $field, array $fields): bool
    {
        $prefix = $this->normalizePathNotation($field->path);
        foreach ($fields as $candidate) {
            if ($candidate->path === $field->path) {
                continue;
            }
            $candidatePath = $this->normalizePathNotation($candidate->path);
            if (str_starts_with($candidatePath, $prefix.'.') || str_starts_with($candidatePath, $prefix.'[]')) {
                return true;
            }
        }

        return false;
    }

    private function relativeFieldPath(ExampleField $field): string
    {
        $normalizedPath = $this->normalizePathNotation($field->path);
        $prefix = $field->location.'.';
        if (str_starts_with($normalizedPath, $prefix)) {
            return substr($normalizedPath, strlen($prefix));
        }

        return $normalizedPath;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assignPathValue(array &$payload, string $path, ExampleField $field, ScenarioContext $context, int $arrayCount, EndpointExampleContext $endpoint): void
    {
        $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));
        $this->assignSegments($payload, $segments, $field, $context, $arrayCount, $endpoint, null);
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  list<string>  $segments
     */
    private function assignSegments(array &$cursor, array $segments, ExampleField $field, ScenarioContext $context, int $arrayCount, EndpointExampleContext $endpoint, ?int $index): void
    {
        if ($segments === []) {
            return;
        }

        $segment = array_shift($segments);
        if ($segment === null) {
            return;
        }

        $isArray = str_ends_with($segment, '[]');
        $key = $isArray ? substr($segment, 0, -2) : $segment;

        if ($isArray) {
            $cursor[$key] ??= [];
            for ($i = 0; $i < $arrayCount; $i++) {
                if ($segments === []) {
                    $cursor[$key][$i] = $this->valueGenerator->generateCollectionItem($field, $context, $i, $endpoint);

                    continue;
                }

                $cursor[$key][$i] ??= [];
                if (! is_array($cursor[$key][$i])) {
                    $cursor[$key][$i] = [];
                }
                $this->assignSegments($cursor[$key][$i], $segments, $field, $context, $arrayCount, $endpoint, $i);
            }

            return;
        }

        if ($segments === []) {
            if ($field->collection || $field->baseType === 'array') {
                $cursor[$key] = $this->collectionTerminalValue($field, $context, $arrayCount, $endpoint);

                return;
            }

            $cursor[$key] = $this->valueGenerator->generate($field, $context, $index, $endpoint);

            return;
        }

        $cursor[$key] ??= [];
        if (! is_array($cursor[$key])) {
            $cursor[$key] = [];
        }
        $this->assignSegments($cursor[$key], $segments, $field, $context, $arrayCount, $endpoint, $index);
    }

    private function arrayCount(ExampleMode $mode): int
    {
        return $mode === ExampleMode::MinimalValid ? 1 : 2;
    }

    /**
     * @return list<mixed>
     */
    private function collectionTerminalValue(ExampleField $field, ScenarioContext $context, int $arrayCount, EndpointExampleContext $endpoint): array
    {
        $values = [];
        for ($i = 0; $i < $arrayCount; $i++) {
            $values[] = $this->valueGenerator->generateCollectionItem($field, $context, $i, $endpoint);
        }

        return $values;
    }

    private function normalizePathNotation(string $path): string
    {
        $normalized = str_replace('.*.', '[].', $path);
        if (str_ends_with($normalized, '.*')) {
            $normalized = substr($normalized, 0, -2).'[]';
        }

        return $normalized;
    }

    private function responseStatus(OperationExampleSpec $spec): ?int
    {
        foreach ($spec->responseStatuses as $status) {
            if ($status >= 200 && $status < 300) {
                return $status;
            }
        }

        return $spec->responseStatuses[0] ?? null;
    }
}
