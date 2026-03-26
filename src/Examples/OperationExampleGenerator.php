<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Examples;

use Garaekz\Oxcribe\Examples\Data\ExampleField;
use Garaekz\Oxcribe\Examples\Data\GeneratedOperationExample;
use Garaekz\Oxcribe\Examples\Data\GeneratedRequestExample;
use Garaekz\Oxcribe\Examples\Data\GeneratedResponseExample;
use Garaekz\Oxcribe\Examples\Data\OperationExampleSpec;
use Garaekz\Oxcribe\Examples\Data\ScenarioContext;

final readonly class OperationExampleGenerator
{
    public function __construct(
        private ScenarioContextFactory $scenarioContextFactory = new ScenarioContextFactory(),
        private DeterministicValueGenerator $valueGenerator = new DeterministicValueGenerator(),
        private SnippetFactory $snippetFactory = new SnippetFactory(),
    ) {
    }

    public function generate(OperationExampleSpec $spec, string $projectSeed, ExampleMode $mode, string $baseUrl = 'https://api.example.test', ?string $bearerToken = null): GeneratedOperationExample
    {
        $context = $this->scenarioContextFactory->make($projectSeed, $spec->endpoint, $mode);
        $request = new GeneratedRequestExample(
            pathParams: $this->buildPayloadMap($spec->pathParams, $context, $mode),
            queryParams: $this->buildPayloadMap($spec->queryParams, $context, $mode),
            body: $this->buildBodyPayload($spec->requestFields, $context, $mode),
            headers: [
                'Accept' => 'application/json',
            ],
        );
        $response = new GeneratedResponseExample(
            status: $this->responseStatus($spec),
            body: $this->buildBodyPayload($spec->responseFields, $context, $mode),
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
     * @param  list<ExampleField>  $fields
     * @return array<string, mixed>
     */
    private function buildPayloadMap(array $fields, ScenarioContext $context, ExampleMode $mode): array
    {
        $payload = [];
        foreach ($this->filteredFields($fields, $mode) as $field) {
            $payload[$field->name] = $this->valueGenerator->generate($field, $context);
        }
        ksort($payload);

        return $payload;
    }

    /**
     * @param  list<ExampleField>  $fields
     */
    private function buildBodyPayload(array $fields, ScenarioContext $context, ExampleMode $mode): mixed
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

            $this->assignPathValue($payload, $relativePath, $field, $context, $this->arrayCount($mode));
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
        $prefix = $field->path;
        foreach ($fields as $candidate) {
            if ($candidate->path === $field->path) {
                continue;
            }
            if (str_starts_with($candidate->path, $prefix.'.') || str_starts_with($candidate->path, $prefix.'[]')) {
                return true;
            }
        }

        return false;
    }

    private function relativeFieldPath(ExampleField $field): string
    {
        $prefix = $field->location.'.';
        if (str_starts_with($field->path, $prefix)) {
            return substr($field->path, strlen($prefix));
        }

        return $field->path;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assignPathValue(array &$payload, string $path, ExampleField $field, ScenarioContext $context, int $arrayCount): void
    {
        $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));
        $this->assignSegments($payload, $segments, $field, $context, $arrayCount, null);
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  list<string>  $segments
     */
    private function assignSegments(array &$cursor, array $segments, ExampleField $field, ScenarioContext $context, int $arrayCount, ?int $index): void
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
                    $cursor[$key][$i] = $this->valueGenerator->generate($field, $context, $i);
                    continue;
                }

                $cursor[$key][$i] ??= [];
                if (! is_array($cursor[$key][$i])) {
                    $cursor[$key][$i] = [];
                }
                $this->assignSegments($cursor[$key][$i], $segments, $field, $context, $arrayCount, $i);
            }

            return;
        }

        if ($segments === []) {
            $cursor[$key] = $this->valueGenerator->generate($field, $context, $index);

            return;
        }

        $cursor[$key] ??= [];
        if (! is_array($cursor[$key])) {
            $cursor[$key] = [];
        }
        $this->assignSegments($cursor[$key], $segments, $field, $context, $arrayCount, $index);
    }

    private function arrayCount(ExampleMode $mode): int
    {
        return $mode === ExampleMode::MinimalValid ? 1 : 2;
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
