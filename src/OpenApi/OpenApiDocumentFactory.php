<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\OpenApi;

use Oxhq\Oxcribe\Auth\AuthProfile;
use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\OperationGraph;
use Oxhq\Oxcribe\Data\RouteBinding;
use Oxhq\Oxcribe\Examples\Data\GeneratedOperationExample;
use Oxhq\Oxcribe\Examples\Data\GeneratedScenarioExample;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\Examples\OperationExampleGenerator;
use Oxhq\Oxcribe\Examples\OperationExampleSpecFactory;
use Oxhq\Oxcribe\OpenApi\Support\EffectiveRequestFieldLocation;
use Oxhq\Oxcribe\OpenApi\Support\RequestFieldIndex;
use Oxhq\Oxcribe\OpenApi\Support\ResourceSchemaIndex;
use ReflectionMethod;

final class OpenApiDocumentFactory
{
    public function __construct(
        private OperationExampleSpecFactory $operationExampleSpecFactory = new OperationExampleSpecFactory,
        private OperationExampleGenerator $operationExampleGenerator = new OperationExampleGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function make(OperationGraph $graph, array $config): array
    {
        $paths = [];
        $usedSecuritySchemes = [];
        $includedOperationCount = 0;
        $resourceIndex = new ResourceSchemaIndex($graph->resources);

        foreach ($graph->operations as $operation) {
            $pathKey = $this->normalizePathKey($operation->uri);

            foreach ($operation->methods as $method) {
                $methodKey = strtolower($method);
                if ($this->shouldExcludeOperation($operation, $methodKey, $config)) {
                    continue;
                }
                [$operationDocument, $schemeNames] = $this->buildOperation($operation, $methodKey, $config, $resourceIndex);
                $paths[$pathKey][$methodKey] = $operationDocument;
                $includedOperationCount++;

                foreach ($schemeNames as $schemeName) {
                    $usedSecuritySchemes[$schemeName] = true;
                }
            }
        }

        $document = [
            'openapi' => $config['version'] ?? '3.1.0',
            'info' => [
                'title' => $config['info']['title'] ?? 'Laravel API',
                'version' => $config['info']['version'] ?? '0.1.0',
            ],
            'paths' => $paths,
            'x-oxcribe' => [
                'operationCount' => $includedOperationCount,
                'diagnosticCount' => count($graph->diagnostics),
            ],
        ];

        $securitySchemes = $this->buildSecuritySchemes($usedSecuritySchemes, $config);
        $componentSchemas = $resourceIndex->componentSchemas();
        if ($securitySchemes !== [] || $componentSchemas !== []) {
            $document['components'] = [];
            if ($securitySchemes !== []) {
                $document['components']['securitySchemes'] = $securitySchemes;
            }
            if ($componentSchemas !== []) {
                $document['components']['schemas'] = $componentSchemas;
            }
        }

        if ($graph->models !== []) {
            $document['x-oxinfer']['models'] = $graph->models;
        }
        if ($graph->polymorphic !== []) {
            $document['x-oxinfer']['polymorphic'] = $graph->polymorphic;
        }
        if ($graph->broadcast !== []) {
            $document['x-oxinfer']['broadcast'] = $graph->broadcast;
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function buildOperation(MergedOperation $operation, string $method, array $config, ResourceSchemaIndex $resourceIndex): array
    {
        $requestFieldIndex = RequestFieldIndex::fromController($operation->controller);
        $override = $this->mergedOperationOverride($operation, $method, $config);
        $authProfile = $operation->authProfile();
        $spec = $this->operationExampleSpecFactory->make($operation, $resourceIndex);
        $generatedExamples = $this->buildGeneratedExamples($operation, $spec);
        $generatedScenarios = $this->buildGeneratedScenarios($operation, $spec);
        $document = [
            'operationId' => $operation->operationId().'_'.$method,
            'responses' => $this->buildResponses($operation, $resourceIndex),
            'x-oxcribe' => [
                'routeId' => $operation->routeId,
                'matchStatus' => $operation->routeMatch->matchStatus,
                'actionKind' => $operation->action->kind,
                'middleware' => $operation->middleware,
            ],
        ];

        $document['summary'] = $this->preferredOperationSummary($operation, $method);

        $tags = $this->generatedOperationTags($operation);
        if ($tags !== []) {
            $document['tags'] = $tags;
        }

        $generatedDescription = $this->generatedOperationDescription($operation, $method);
        if ($generatedDescription !== null) {
            $document['description'] = $generatedDescription;
        }

        if ($operation->prefix !== null) {
            $document['x-oxcribe']['prefix'] = $operation->prefix;
        }

        if ($operation->routeMatch->actionKey !== null) {
            $document['x-oxcribe']['actionKey'] = $operation->routeMatch->actionKey;
        }

        $authorization = $operation->authorizationConstraints();
        if ($authorization !== []) {
            $document['x-oxcribe']['authorization'] = $authorization;
        }
        $authorizationStatic = $operation->staticAuthorizationHints();
        if ($authorizationStatic !== []) {
            $document['x-oxcribe']['authorizationStatic'] = $authorizationStatic;
        }

        if ($this->shouldExposeAuthProfile($authProfile)) {
            $document['x-oxcribe']['auth'] = $authProfile->toArray();
        }

        $parameters = array_merge(
            $this->buildPathParameters($operation, $generatedExamples),
            $this->buildQueryParameters($operation, $requestFieldIndex, $generatedExamples),
        );
        if ($parameters !== []) {
            $document['parameters'] = $parameters;
        }

        $security = $this->buildSecurityRequirements($operation, $config);
        if ($security !== []) {
            $document['security'] = $security;
        }

        $requestBody = $this->buildRequestBody($operation, $requestFieldIndex);
        if ($generatedExamples !== []) {
            $document['responses'] = $this->attachGeneratedResponseExamples((array) $document['responses'], $generatedExamples);
            $document['x-oxcribe']['examples'] = $this->generatedExamplesExtension($generatedExamples);
            $document['x-oxcribe']['snippets'] = $this->generatedSnippetsExtension($generatedExamples);
        }
        if ($generatedScenarios !== []) {
            $document['x-oxcribe']['scenarios'] = $this->generatedScenariosExtension($generatedScenarios);
        }
        if ($requestBody !== null) {
            $requestBody = $this->attachGeneratedRequestExamples($requestBody, $generatedExamples);
            $document['requestBody'] = $requestBody;
        }

        $document = $this->applyOperationOverride($document, $override);

        return [$document, $this->securitySchemeNames((array) ($document['security'] ?? []))];
    }

    /**
     * @return array<string, GeneratedOperationExample>
     */
    private function buildGeneratedExamples(MergedOperation $operation, ?OperationExampleSpec $spec = null): array
    {
        $spec ??= $this->operationExampleSpecFactory->make($operation);

        return $this->operationExampleGenerator->generateAll(
            $spec,
            $this->projectSeedFor($operation),
            $this->exampleBaseUrl(),
            null,
        );
    }

    /**
     * @return array<string, array<string, GeneratedScenarioExample>>
     */
    private function buildGeneratedScenarios(MergedOperation $operation, ?OperationExampleSpec $spec = null): array
    {
        $spec ??= $this->operationExampleSpecFactory->make($operation);

        return $this->operationExampleGenerator->generateScenarios(
            $spec,
            $this->projectSeedFor($operation),
            $this->exampleBaseUrl(),
            null,
        );
    }

    private function projectSeedFor(MergedOperation $operation): string
    {
        return implode('|', array_filter([
            base_path(),
            $operation->routeId,
            $operation->name,
            $operation->uri,
            $operation->routeMatch->actionKey,
            $operation->action->signature(),
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    private function exampleBaseUrl(): string
    {
        $configured = trim((string) config('app.url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'https://api.example.test';
    }

    /**
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return array<string, mixed>|null
     */
    private function attachGeneratedRequestExamples(?array $requestBody, array $generatedExamples): ?array
    {
        if (! is_array($requestBody) || $generatedExamples === []) {
            return $requestBody;
        }

        $content = $requestBody['content'] ?? null;
        if (! is_array($content) || $content === []) {
            return $requestBody;
        }

        $examples = [];
        foreach ($generatedExamples as $mode => $generatedExample) {
            $body = $generatedExample->request->body;
            if ($body === null) {
                continue;
            }

            $examples[$mode] = [
                'summary' => $this->openApiExampleSummary($mode),
                'value' => $body,
            ];
        }

        if ($examples === []) {
            return $requestBody;
        }

        foreach (array_keys($content) as $mediaType) {
            if (! is_string($mediaType) || $mediaType === '') {
                continue;
            }

            $existingExamples = (array) ($content[$mediaType]['examples'] ?? []);
            $content[$mediaType]['examples'] = array_replace_recursive($existingExamples, $examples);
        }

        $requestBody['content'] = $content;

        return $requestBody;
    }

    /**
     * @param  array<string, mixed>  $responses
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return array<string, mixed>
     */
    private function attachGeneratedResponseExamples(array $responses, array $generatedExamples): array
    {
        if ($responses === [] || $generatedExamples === []) {
            return $responses;
        }

        foreach ($generatedExamples as $mode => $generatedExample) {
            $status = $generatedExample->response->status;
            $body = $generatedExample->response->body;
            if ($status === null || $body === null) {
                continue;
            }

            $statusKey = (string) $status;
            $response = (array) ($responses[$statusKey] ?? []);
            $mediaType = $this->responseMediaType($response);
            if ($mediaType === null) {
                continue;
            }

            $existingExamples = (array) ($response['content'][$mediaType]['examples'] ?? []);
            $response['content'][$mediaType]['examples'] = array_replace_recursive($existingExamples, [
                $mode => [
                    'summary' => $this->openApiExampleSummary($mode),
                    'value' => $body,
                ],
            ]);
            $responses[$statusKey] = $response;
        }

        return $responses;
    }

    private function openApiExampleSummary(string $mode): string
    {
        return match ($mode) {
            'minimal_valid' => 'Minimal valid',
            'realistic_full' => 'Realistic full',
            default => 'Happy path',
        };
    }

    /**
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return array<string, mixed>
     */
    private function generatedExamplesExtension(array $generatedExamples): array
    {
        $extension = [];

        foreach ($generatedExamples as $mode => $generatedExample) {
            $extension[$mode] = [
                'endpoint' => $generatedExample->endpoint->toArray(),
                'context' => $generatedExample->context->toArray(),
                'request' => $generatedExample->request->toArray(),
                'response' => $generatedExample->response->toArray(),
            ];
        }

        ksort($extension);

        return $extension;
    }

    /**
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return array<string, mixed>
     */
    private function generatedSnippetsExtension(array $generatedExamples): array
    {
        $extension = [];

        foreach ($generatedExamples as $mode => $generatedExample) {
            $extension[$mode] = $generatedExample->snippets->toArray();
        }

        ksort($extension);

        return $extension;
    }

    /**
     * @param  array<string, array<string, GeneratedScenarioExample>>  $generatedScenarios
     * @return array<string, mixed>
     */
    private function generatedScenariosExtension(array $generatedScenarios): array
    {
        $extension = [];

        foreach ($generatedScenarios as $mode => $scenarios) {
            foreach ($scenarios as $key => $generatedScenario) {
                $extension[$mode][$key] = $generatedScenario->toArray();
            }
        }

        ksort($extension);

        return $extension;
    }

    private function shouldExposeAuthProfile(AuthProfile $profile): bool
    {
        return $profile->requiresAuthentication
            || $profile->requiresAuthorization
            || $profile->runtimeConstraints() !== [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildResponses(MergedOperation $operation, ResourceSchemaIndex $resourceIndex): array
    {
        $responses = [];

        foreach ($this->controllerResponses($operation) as $response) {
            $status = $this->responseStatus($response, $operation);
            if ($status === '') {
                continue;
            }

            if (! $this->shouldDocumentResponse($response, $status)) {
                continue;
            }

            $responses[$status] = $this->buildControllerResponse($operation, $response, $status, $resourceIndex);
        }

        if ($responses === []) {
            $status = (string) ($operation->controller['http']['status'] ?? 200);
            $responses[$status] = $this->buildFallbackResponse($operation, $status, $resourceIndex);
        }

        ksort($responses, SORT_NATURAL);

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function buildControllerResponse(MergedOperation $operation, array $response, string $status, ResourceSchemaIndex $resourceIndex): array
    {
        $document = [
            'description' => $this->responseDescription($status, $response),
        ];

        $kind = trim((string) ($response['kind'] ?? ''));
        $hasResponseMeta = $kind !== ''
            || array_key_exists('explicit', $response)
            || array_key_exists('source', $response)
            || array_key_exists('via', $response)
            || is_array($response['redirect'] ?? null)
            || is_array($response['download'] ?? null)
            || is_array($response['inertia'] ?? null);
        if ($hasResponseMeta) {
            $responseMeta = array_filter([
                'kind' => $kind !== '' ? $kind : null,
                'explicit' => is_bool($response['explicit'] ?? null) ? (bool) $response['explicit'] : null,
                'source' => is_string($response['source'] ?? null) && $response['source'] !== '' ? (string) $response['source'] : null,
                'via' => is_string($response['via'] ?? null) && $response['via'] !== '' ? (string) $response['via'] : null,
            ], static fn (mixed $value): bool => $value !== null);

            if (is_array($response['redirect'] ?? null) && $response['redirect'] !== []) {
                $responseMeta['redirect'] = $response['redirect'];
            }
            if (is_array($response['download'] ?? null) && $response['download'] !== []) {
                $responseMeta['download'] = $response['download'];
            }

            $document['x-oxcribe'] = [
                'response' => $responseMeta,
            ];

            if ($kind === 'inertia') {
                $inertiaMeta = $this->buildInertiaExtension((array) ($response['inertia'] ?? []), $resourceIndex);
                if ($inertiaMeta !== []) {
                    $document['x-oxcribe']['inertia'] = $inertiaMeta;
                }
            }
        }

        $headers = $this->buildResponseHeaders((array) ($response['headers'] ?? []));
        if ($headers !== []) {
            $document['headers'] = $headers;
        }

        if ($status === '204' || $kind === 'no_content') {
            return $document;
        }
        if ($kind === 'redirect') {
            return $document;
        }

        $contentType = trim((string) ($response['contentType'] ?? ''));
        if ($contentType === '') {
            $contentType = $this->defaultContentTypeForResponseKind($kind) ?? 'application/json';
        }

        $schema = null;
        $inlineBodySchema = null;
        if (is_array($response['bodySchema'] ?? null)) {
            $inlineSchema = $resourceIndex->schemaForNode((array) $response['bodySchema']);
            if ($inlineSchema !== []) {
                $inlineBodySchema = $inlineSchema;
            }
        }

        $validationErrorSchema = $this->validationErrorResponseSchema($operation, $response);
        if ($validationErrorSchema !== null) {
            $inlineBodySchema = $inlineBodySchema === null
                ? $validationErrorSchema
                : array_replace_recursive($inlineBodySchema, $validationErrorSchema);
        }

        if ($kind === 'inertia') {
            $document['content'] = [
                $contentType => [
                    'schema' => $this->defaultSchemaForResponseKind($kind, $contentType) ?? ['type' => 'string'],
                ],
            ];

            return $document;
        }

        $disableResourceFallback = $this->responseKindDisablesResourceFallback($kind);
        if ($disableResourceFallback) {
            $schema = $this->defaultSchemaForResponseKind($kind, $contentType);
        }
        if ($inlineBodySchema !== null) {
            $schema = $inlineBodySchema;
        }
        if ($schema === null && ! $disableResourceFallback) {
            $schema = $resourceIndex->responseSchemaFor($this->primaryResourceUse($operation));
        }
        if ($schema === null) {
            return $document;
        }

        $document['content'] = [
            $contentType => [
                'schema' => $this->hydrateSchemaDescriptions($schema),
            ],
        ];

        return $document;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function buildResponseHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || trim($name) === '' || strcasecmp($name, 'Content-Type') === 0) {
                continue;
            }

            $normalized[$name] = [
                'schema' => [
                    'type' => 'string',
                ],
            ];

            if (is_string($value) && trim($value) !== '') {
                $normalized[$name]['example'] = $value;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function responseKindDisablesResourceFallback(string $kind): bool
    {
        return in_array($kind, ['redirect', 'download', 'stream', 'inertia'], true);
    }

    private function defaultContentTypeForResponseKind(string $kind): ?string
    {
        return match ($kind) {
            'download' => 'application/octet-stream',
            'inertia' => 'text/html',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function defaultSchemaForResponseKind(string $kind, string $contentType): ?array
    {
        return match ($kind) {
            'download' => [
                'type' => 'string',
                'format' => 'binary',
            ],
            'stream' => $this->streamResponseSchema($contentType),
            'inertia' => [
                'type' => 'string',
            ],
            'redirect' => null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $inertia
     * @return array<string, mixed>
     */
    private function buildInertiaExtension(array $inertia, ResourceSchemaIndex $resourceIndex): array
    {
        $extension = array_filter([
            'component' => is_string($inertia['component'] ?? null) && $inertia['component'] !== '' ? (string) $inertia['component'] : null,
            'rootView' => is_string($inertia['rootView'] ?? null) && $inertia['rootView'] !== '' ? (string) $inertia['rootView'] : null,
            'version' => is_string($inertia['version'] ?? null) && $inertia['version'] !== '' ? (string) $inertia['version'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        if (is_array($inertia['propsSchema'] ?? null)) {
            $propsSchema = $resourceIndex->schemaForNode((array) $inertia['propsSchema']);
            if ($propsSchema !== []) {
                $extension['propsSchema'] = $propsSchema;
            }
        }

        return $extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function streamResponseSchema(string $contentType): array
    {
        if (str_contains($contentType, 'json') || str_contains($contentType, 'text/')) {
            return ['type' => 'string'];
        }

        return [
            'type' => 'string',
            'format' => 'binary',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFallbackResponse(MergedOperation $operation, string $status, ResourceSchemaIndex $resourceIndex): array
    {
        $response = [
            'description' => $this->responseDescription($status),
        ];

        if ($status === '204') {
            return $response;
        }

        $resourceUse = $this->primaryResourceUse($operation);
        $schema = $resourceIndex->responseSchemaFor($resourceUse);
        if ($schema === null) {
            return $response;
        }

        $response['content'] = [
            'application/json' => [
                'schema' => $this->hydrateSchemaDescriptions($schema),
            ],
        ];

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function controllerResponses(MergedOperation $operation): array
    {
        $controller = $operation->controller;
        if (! is_array($controller)) {
            return [];
        }

        return array_values(array_filter(
            (array) ($controller['responses'] ?? []),
            static fn (mixed $response): bool => is_array($response),
        ));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseStatus(array $response, MergedOperation $operation): string
    {
        $status = $response['status'] ?? null;
        if (is_int($status) && $status >= 100 && $status <= 599) {
            return (string) $status;
        }
        if (is_string($status) && preg_match('/^[1-5][0-9][0-9]$/', $status) === 1) {
            return $status;
        }

        $fallback = $operation->controller['http']['status'] ?? 200;

        return is_int($fallback) ? (string) $fallback : '200';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseDescription(string $status, array $response = []): string
    {
        $kind = trim((string) ($response['kind'] ?? ''));

        return match ($status) {
            '201' => 'Created',
            '202' => 'Accepted',
            '204' => 'No content',
            '400' => 'Bad request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => 'Not found',
            '409' => 'Conflict',
            '410' => 'Gone',
            '422' => 'Unprocessable content',
            '429' => 'Too many requests',
            '500' => 'Internal server error',
            '501' => 'Not implemented',
            '502' => 'Bad gateway',
            '503' => 'Service unavailable',
            '504' => 'Gateway timeout',
            default => $kind === 'no_content'
                ? 'No content'
                : ((int) $status >= 500
                    ? 'Server error'
                    : ((int) $status >= 400 ? 'Error response' : 'Successful response')),
        };
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function shouldExcludeOperation(MergedOperation $operation, string $method, array $config): bool
    {
        $override = $this->operationOverride($operation, $method, $config);
        if (($override['exclude'] ?? false) === true) {
            return true;
        }

        $uri = ltrim($operation->uri, '/');
        /** @var list<string> $prefixes */
        $prefixes = array_values((array) ($config['route_filters']['exclude_uri_prefixes'] ?? []));
        /** @var list<string> $exactUris */
        $exactUris = array_values((array) ($config['route_filters']['exclude_uri_exact'] ?? []));

        foreach ($exactUris as $exactUri) {
            $normalizedUri = trim($exactUri, '/');
            if ($normalizedUri !== '' && $uri === $normalizedUri) {
                return true;
            }
        }

        foreach ($prefixes as $prefix) {
            $normalizedPrefix = trim($prefix, '/');
            if ($normalizedPrefix === '') {
                continue;
            }

            if ($uri === $normalizedPrefix || str_starts_with($uri, $normalizedPrefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function normalizePathKey(string $uri): string
    {
        $normalized = preg_replace('/\{([^}]+)\?\}/', '{$1}', trim($uri, '/')) ?? trim($uri, '/');

        return $normalized === '' ? '/' : '/'.$normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return list<array<string, mixed>>
     */
    private function buildPathParameters(MergedOperation $operation, array $generatedExamples = []): array
    {
        preg_match_all('/\{([^}]+)\}/', $operation->uri, $matches);
        if (($matches[1] ?? []) === []) {
            return [];
        }

        $bindingsByParameter = [];
        foreach ($operation->bindings as $binding) {
            $bindingsByParameter[$binding->parameter] = $binding;
        }

        $parameters = [];
        foreach ($matches[1] as $rawName) {
            $optional = str_ends_with($rawName, '?');
            $parameterName = rtrim($rawName, '?');
            $binding = $bindingsByParameter[$parameterName] ?? null;
            $parameter = [
                'name' => $parameterName,
                'in' => 'path',
                'required' => true,
                'schema' => $this->buildPathParameterSchema($parameterName, $operation),
                'description' => $this->generatedPathParameterDescription($parameterName, $operation),
            ];

            $extension = [];
            if ($optional) {
                $extension['optionalSegment'] = true;
            }
            if (array_key_exists($parameterName, $operation->defaults)) {
                $extension['default'] = $operation->defaults[$parameterName];
            }
            if ($binding instanceof RouteBinding) {
                $extension['binding'] = $binding->toArray();
            }
            if ($extension !== []) {
                $parameter['x-oxcribe'] = $extension;
            }

            $parameter = $this->attachGeneratedParameterExample($parameter, $generatedExamples, $parameterName, 'path');
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPathParameterSchema(string $parameterName, MergedOperation $operation): array
    {
        $schema = ['type' => 'string'];
        $pattern = $operation->where[$parameterName] ?? null;
        if (! is_string($pattern) || $pattern === '') {
            return $schema;
        }

        if ($this->looksNumericPattern($pattern)) {
            return ['type' => 'integer'];
        }

        $schema['pattern'] = $pattern;

        return $this->hydrateSchemaDescriptions($schema);
    }

    private function looksNumericPattern(string $pattern): bool
    {
        $normalized = trim($pattern);
        $normalized = preg_replace('/^\^|\$$/', '', $normalized) ?? $normalized;

        return in_array($normalized, ['[0-9]+', '\d+', '[1-9][0-9]*'], true);
    }

    private function generatedOperationSummary(MergedOperation $operation, string $method): string
    {
        $systemSummary = $this->systemOperationSummary($operation, $method);
        if ($systemSummary !== null) {
            return $systemSummary;
        }

        $authSummary = $this->authOperationSummary($operation, $method);
        if ($authSummary !== null) {
            return $authSummary;
        }

        $resource = $this->humanizedResourceName($operation->uri);

        return match (strtoupper($method)) {
            'GET' => str_contains($operation->uri, '{') ? sprintf('Show %s', $resource) : sprintf('List %s', $this->pluralizeLabel($resource)),
            'POST' => sprintf('Create %s', $resource),
            'PUT', 'PATCH' => sprintf('Update %s', $resource),
            'DELETE' => sprintf('Delete %s', $resource),
            default => sprintf('%s %s', strtoupper($method), $resource),
        };
    }

    private function preferredOperationSummary(MergedOperation $operation, string $method): string
    {
        $name = trim((string) ($operation->name ?? ''));
        if ($name === '' || $this->looksMachineGeneratedSummary($name)) {
            return $this->generatedOperationSummary($operation, $method);
        }

        return $name;
    }

    private function generatedOperationDescription(MergedOperation $operation, string $method): ?string
    {
        $segments = [
            $this->generatedOperationSummary($operation, $method).'.',
        ];

        if ($operation->requiresAuthentication()) {
            $segments[] = 'Requires authentication.';
        }

        if ($operation->requiresAuthorization()) {
            $segments[] = 'Authorization rules apply before the handler runs.';
        }

        if ($operation->prefix !== null && $operation->prefix !== '') {
            $segments[] = sprintf('Published under the "%s" route group.', $operation->prefix);
        }

        return implode(' ', array_filter($segments));
    }

    /**
     * @return list<string>
     */
    private function generatedOperationTags(MergedOperation $operation): array
    {
        $segments = array_values(array_filter(
            explode('/', trim($operation->uri, '/')),
            static fn (string $segment): bool => $segment !== '' && ! str_starts_with($segment, '{'),
        ));

        if ($segments === []) {
            return ['System'];
        }

        $first = strtolower($segments[0]);
        if ($first === 'api' || preg_match('/^v[0-9]+$/', $first) === 1) {
            array_shift($segments);
        }

        if ($segments === []) {
            return ['System'];
        }

        $tag = strtolower($segments[0]);

        if (in_array($tag, ['login', 'logout', 'register', 'authenticate', 'callback'], true)) {
            return ['Auth'];
        }

        if (in_array($tag, ['docs', 'documentation'], true)) {
            return ['Docs'];
        }

        if (in_array($tag, ['up', 'health', 'status', 'heartbeat', 'ping'], true)) {
            return ['System'];
        }

        return [$this->humanizeToken($segments[0])];
    }

    private function generatedPathParameterDescription(string $parameterName, MergedOperation $operation): string
    {
        $resource = $this->humanizedResourceName($operation->uri);
        $label = $this->humanizeToken($parameterName);

        if ($parameterName === 'id' || str_ends_with($parameterName, '_id')) {
            return sprintf('Path identifier for the %s resource.', strtolower($resource));
        }

        return sprintf('Path parameter for %s on the %s route.', strtolower($label), strtolower($resource));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function generatedFieldDescription(string $path, array $schema): string
    {
        $leaf = $this->leafToken($path);
        $label = $this->humanizeToken($leaf);
        $enum = is_array($schema['enum'] ?? null) ? array_values($schema['enum']) : [];
        $format = is_string($schema['format'] ?? null) ? (string) $schema['format'] : null;
        $type = is_string($schema['type'] ?? null) ? (string) $schema['type'] : 'value';

        $base = match (true) {
            $leaf === 'email' || $format === 'email' => 'Email address.',
            str_contains($leaf, 'password') => 'Password used by the endpoint.',
            $leaf === 'id' || str_ends_with($leaf, '_id') => sprintf('Identifier for %s.', strtolower($label)),
            in_array($leaf, ['created_at', 'updated_at', 'deleted_at'], true) => sprintf('Timestamp for %s.', strtolower($label)),
            $format === 'uuid' => sprintf('UUID value for %s.', strtolower($label)),
            $format === 'date-time' => sprintf('Date and time for %s.', strtolower($label)),
            $format === 'date' => sprintf('Date value for %s.', strtolower($label)),
            $type === 'boolean' => sprintf('Boolean flag for %s.', strtolower($label)),
            $type === 'array' => sprintf('Collection of %s entries.', strtolower($label)),
            $type === 'object' => sprintf('Object payload for %s.', strtolower($label)),
            $type === 'integer' => sprintf('Integer value for %s.', strtolower($label)),
            $type === 'number' => sprintf('Numeric value for %s.', strtolower($label)),
            default => sprintf('String value for %s.', strtolower($label)),
        };

        if ($enum !== []) {
            $allowedValues = implode(', ', array_map(static fn (mixed $value): string => (string) $value, $enum));

            return sprintf('%s Allowed values: %s.', $base, $allowedValues);
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function hydrateSchemaDescriptions(array $schema, string $path = ''): array
    {
        if (($schema['format'] ?? null) === null && $this->shouldInferUriFormat($path, $schema)) {
            $schema['format'] = 'uri';
        }

        if ($path !== '' && ! is_string($schema['description'] ?? null)) {
            $schema['description'] = $this->generatedFieldDescription($path, $schema);
        }

        if (is_array($schema['properties'] ?? null)) {
            foreach ($schema['properties'] as $property => $childSchema) {
                if (! is_array($childSchema) || ! is_string($property)) {
                    continue;
                }

                $childPath = $path === '' ? $property : $path.'.'.$property;
                $schema['properties'][$property] = $this->hydrateSchemaDescriptions($childSchema, $childPath);
            }
        }

        if (is_array($schema['items'] ?? null)) {
            $itemPath = $path === '' ? 'items[]' : $path.'[]';
            $schema['items'] = $this->hydrateSchemaDescriptions($schema['items'], $itemPath);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function shouldInferUriFormat(string $path, array $schema): bool
    {
        $type = $schema['type'] ?? null;
        $isStringSchema = $type === 'string'
            || (is_array($type) && in_array('string', $type, true));

        if (! $isStringSchema) {
            return false;
        }

        $leaf = strtolower((string) last(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== '')));
        if ($leaf === '') {
            return false;
        }

        return in_array($leaf, ['url', 'uri', 'href', 'link', 'links', 'self'], true)
            || str_contains($path, '.url')
            || str_contains($path, '.uri')
            || str_contains($path, '.href')
            || str_contains($path, '.link')
            || str_contains($path, '.links.');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function validationErrorResponseSchema(MergedOperation $operation, array $response): ?array
    {
        $source = trim((string) ($response['source'] ?? ''));
        $via = trim((string) ($response['via'] ?? ''));
        if ($source !== 'ValidationException::withMessages' && $via !== 'ValidationException::withMessages') {
            return null;
        }

        $controllerClass = $operation->action->fqcn;
        $controllerMethod = $operation->action->method;
        if (! is_string($controllerClass) || $controllerClass === '' || ! is_string($controllerMethod) || $controllerMethod === '') {
            return null;
        }

        if (! class_exists($controllerClass, false)) {
            $sourceFile = $this->controllerSourceFile($controllerClass);
            if ($sourceFile === null) {
                return null;
            }

            try {
                require_once $sourceFile;
            } catch (\Throwable) {
                return null;
            }
        }

        if (! class_exists($controllerClass, false) || ! method_exists($controllerClass, $controllerMethod)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($controllerClass, $controllerMethod);
        } catch (\ReflectionException) {
            return null;
        }

        $file = $reflection->getFileName();
        if (! is_string($file) || $file === '' || ! is_file($file)) {
            return null;
        }

        $methodSource = $this->readMethodSource($file, $reflection->getStartLine(), $reflection->getEndLine());
        if ($methodSource === null) {
            return null;
        }

        $keys = $this->validationMessageKeys($methodSource);
        if ($keys === []) {
            return null;
        }

        $properties = [];
        foreach ($keys as $key) {
            $properties[$key] = [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'errors' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $keys,
                ],
            ],
            'required' => ['errors'],
        ];
    }

    private function readMethodSource(string $file, int $startLine, int $endLine): ?string
    {
        $contents = @file($file, FILE_IGNORE_NEW_LINES);
        if (! is_array($contents) || $contents === []) {
            return null;
        }

        $startIndex = max(0, $startLine - 1);
        $length = max(0, $endLine - $startLine + 1);
        $lines = array_slice($contents, $startIndex, $length);

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function validationMessageKeys(string $methodSource): array
    {
        $needle = 'ValidationException::withMessages';
        $position = strpos($methodSource, $needle);
        if ($position === false) {
            return [];
        }

        $arrayStart = strpos($methodSource, '[', $position);
        if ($arrayStart === false) {
            return [];
        }

        $body = $this->balancedArrayBody(substr($methodSource, $arrayStart));
        if ($body === null) {
            return [];
        }

        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $body, $matches);
        $keys = array_values(array_unique(array_filter(
            array_map('strval', $matches[1] ?? []),
            static fn (string $value): bool => $value !== '',
        )));
        sort($keys);

        return $keys;
    }

    private function balancedArrayBody(string $source): ?string
    {
        $depth = 0;
        $body = '';

        for ($index = 0, $length = strlen($source); $index < $length; $index++) {
            $char = $source[$index];

            if ($char === '[') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return $body;
                }
            }

            if ($depth >= 1) {
                $body .= $char;
            }
        }

        return null;
    }

    private function controllerSourceFile(string $controllerClass): ?string
    {
        $workingDirectory = trim((string) config('oxcribe.oxinfer.working_directory', ''));
        if ($workingDirectory === '') {
            $workingDirectory = base_path();
        }

        $relativePath = str_starts_with($controllerClass, 'App\\')
            ? 'app/'.str_replace('\\', '/', substr($controllerClass, 4)).'.php'
            : str_replace('\\', '/', $controllerClass).'.php';

        $path = rtrim($workingDirectory, '/\\').DIRECTORY_SEPARATOR.$relativePath;
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function humanizedResourceName(string $uri): string
    {
        $segments = array_values(array_filter(explode('/', trim($uri, '/')), static fn (string $segment): bool => $segment !== ''));
        $segments = array_values(array_filter($segments, static fn (string $segment): bool => ! str_starts_with($segment, '{')));
        $resource = $segments !== [] ? end($segments) : 'resource';

        return $this->humanizeToken($this->singularizeToken((string) ($resource ?: 'resource')));
    }

    private function authOperationSummary(MergedOperation $operation, string $method): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($operation->uri, '/')), static fn (string $segment): bool => $segment !== ''));
        $last = strtolower((string) end($segments));

        return match (true) {
            strtoupper($method) === 'POST' && in_array($last, ['login', 'signin', 'sign-in', 'authenticate'], true) => 'Login',
            strtoupper($method) === 'POST' && in_array($last, ['register', 'signup', 'sign-up'], true) => 'Register',
            in_array(strtoupper($method), ['POST', 'DELETE'], true) && in_array($last, ['logout', 'signout', 'sign-out'], true) => 'Logout',
            strtoupper($method) === 'GET' && $last === 'callback' => 'Authentication callback',
            default => null,
        };
    }

    private function systemOperationSummary(MergedOperation $operation, string $method): ?string
    {
        $method = strtoupper($method);
        $segments = array_values(array_filter(explode('/', trim($operation->uri, '/')), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return $method === 'GET' ? 'Root endpoint' : null;
        }

        $last = strtolower((string) end($segments));
        $first = strtolower($segments[0]);

        if ($method === 'GET' && in_array($last, ['up', 'health', 'status', 'heartbeat', 'ping'], true)) {
            return 'Health check';
        }

        if ($first === 'docs' && $method === 'GET') {
            return str_ends_with($last, '.json') ? 'OpenAPI document' : 'Documentation';
        }

        return null;
    }

    private function looksMachineGeneratedSummary(string $value): bool
    {
        return ! str_contains($value, ' ')
            && preg_match('/^[a-z0-9._:-]+$/i', $value) === 1;
    }

    private function pluralizeLabel(string $value): string
    {
        return str_ends_with(strtolower($value), 's') ? $value : $value.'s';
    }

    private function leafToken(string $path): string
    {
        $segments = preg_split('/[.\[]/', $path) ?: [$path];
        $segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== ']'));

        return strtolower((string) end($segments));
    }

    private function humanizeToken(string $value): string
    {
        $normalized = str_replace(['[]', '_', '-'], [' items', ' ', ' '], strtolower($value));
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

        return ucfirst($normalized !== '' ? $normalized : 'value');
    }

    private function singularizeToken(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (str_ends_with($normalized, 'ies')) {
            return substr($normalized, 0, -3).'y';
        }

        if (str_ends_with($normalized, 'sses')) {
            return substr($normalized, 0, -2);
        }

        if (str_ends_with($normalized, 's') && strlen($normalized) > 3) {
            return substr($normalized, 0, -1);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return list<array<string, mixed>>
     */
    private function buildQueryParameters(MergedOperation $operation, RequestFieldIndex $requestFieldIndex, array $generatedExamples = []): array
    {
        $request = $operation->controller['request'] ?? null;
        if (! is_array($request)) {
            return [];
        }

        $effectiveLocation = EffectiveRequestFieldLocation::query($operation, $request, $requestFieldIndex);
        $queryShape = is_array($request['query'] ?? null) ? $request['query'] : [];
        $parameterNames = $this->orderedNames(
            array_keys($queryShape),
            $this->overlayChildNames($requestFieldIndex, $effectiveLocation, ''),
        );
        if ($parameterNames === []) {
            return [];
        }

        $parameters = [];
        foreach ($parameterNames as $name) {
            $shape = is_array($queryShape[$name] ?? null) ? $queryShape[$name] : [];
            $field = $requestFieldIndex->get($effectiveLocation, $name);
            $parameter = [
                'name' => (string) $name,
                'in' => 'query',
                'required' => (bool) ($field['required'] ?? false),
                'schema' => $this->buildOverlaySchema($shape, $requestFieldIndex, $effectiveLocation, (string) $name, 'string'),
                'description' => $this->generatedFieldDescription((string) $name, $this->buildOverlaySchema($shape, $requestFieldIndex, $effectiveLocation, (string) $name, 'string')),
            ];

            if ($this->queryParameterUsesCsvEncoding((string) $name, $shape, $field, $requestFieldIndex, $effectiveLocation)) {
                $parameter['schema'] = ['type' => 'string'];
            } elseif ($this->queryParameterUsesDeepObject((string) $name, $shape, $field, $requestFieldIndex, $effectiveLocation)) {
                $parameter['style'] = 'deepObject';
                $parameter['explode'] = true;
            }

            $extension = $this->buildQueryParameterExtension((string) $name, $shape, $requestFieldIndex, $effectiveLocation);
            if ($extension !== []) {
                $parameter['x-oxcribe'] = $extension;
            }

            $parameter = $this->attachGeneratedParameterExample($parameter, $generatedExamples, (string) $name, 'query');
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @param  array<string, GeneratedOperationExample>  $generatedExamples
     * @return array<string, mixed>
     */
    private function attachGeneratedParameterExample(array $parameter, array $generatedExamples, string $name, string $location): array
    {
        $value = null;

        foreach (['happy_path', 'realistic_full', 'minimal_valid'] as $mode) {
            $example = $generatedExamples[$mode] ?? null;
            if (! $example instanceof GeneratedOperationExample) {
                continue;
            }

            $value = match ($location) {
                'path' => $example->request->pathParams[$name] ?? null,
                'query' => $example->request->queryParams[$name] ?? null,
                default => null,
            };

            if ($value !== null) {
                break;
            }
        }

        if ($value === null) {
            $value = $this->fallbackParameterExample($parameter);
        }

        if ($value === null) {
            return $parameter;
        }

        $parameter['example'] = $value;

        return $parameter;
    }

    private function fallbackParameterExample(array $parameter): mixed
    {
        $name = strtolower((string) ($parameter['name'] ?? ''));
        $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];
        $type = $this->primarySchemaType($schema);

        return match (true) {
            in_array($name, ['search', 'query', 'q'], true) => 'valorant',
            in_array($name, ['limit', 'per_page', 'page_size'], true) => 20,
            $name === 'page' => 1,
            in_array($name, ['sort'], true) => 'created_at',
            in_array($name, ['order', 'direction'], true) => 'desc',
            $name === 'list' => 'featured-broadcasts',
            $name === 'account' => 'ana_lopez',
            $name === 'path' => 'exports/weekly-report.csv',
            $type === 'boolean' => true,
            $type === 'integer' || $type === 'number' => 123,
            $type === 'string' => 'sample',
            default => null,
        };
    }

    private function primarySchemaType(array $schema): ?string
    {
        $type = $schema['type'] ?? null;

        if (is_string($type) && $type !== '') {
            return $type;
        }

        if (! is_array($type)) {
            return null;
        }

        foreach ($type as $candidate) {
            if (! is_string($candidate) || $candidate === 'null' || $candidate === '') {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRequestBody(MergedOperation $operation, RequestFieldIndex $requestFieldIndex): ?array
    {
        $request = $operation->controller['request'] ?? null;
        if (! is_array($request)) {
            return null;
        }

        $bodyLocation = EffectiveRequestFieldLocation::body($operation, $request, $requestFieldIndex);
        if ($bodyLocation === null) {
            return null;
        }

        $contentTypes = array_values(array_filter(
            (array) ($request['contentTypes'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        if ($contentTypes === []) {
            return null;
        }

        $bodyShape = is_array($request['body'] ?? null) ? $request['body'] : [];
        $fileShape = is_array($request['files'] ?? null) ? $request['files'] : [];
        $content = [];

        foreach ($contentTypes as $contentType) {
            $content[$contentType] = [
                'schema' => $this->buildRequestSchema($bodyShape, $fileShape, $contentType, $requestFieldIndex, $bodyLocation),
            ];
        }

        return [
            'required' => false,
            'content' => $content,
        ];
    }

    /**
     * @param  array<string, mixed>  $bodyShape
     * @param  array<string, mixed>  $fileShape
     * @return array<string, mixed>
     */
    private function buildRequestSchema(array $bodyShape, array $fileShape, string $contentType, RequestFieldIndex $requestFieldIndex, string $bodyLocation = 'body'): array
    {
        $properties = [];
        $required = [];

        foreach ($this->orderedNames(array_keys($bodyShape), $this->overlayChildNames($requestFieldIndex, $bodyLocation, '')) as $name) {
            $path = (string) $name;
            $shape = is_array($bodyShape[$name] ?? null) ? $bodyShape[$name] : [];
            $properties[$path] = $this->buildOverlaySchema($shape, $requestFieldIndex, $bodyLocation, $path, null);
            if (($requestFieldIndex->get($bodyLocation, $path)['required'] ?? false) === true) {
                $required[] = $path;
            }
        }

        if ($contentType === 'multipart/form-data') {
            foreach ($this->orderedNames(array_keys($fileShape), $this->overlayChildNames($requestFieldIndex, 'files', '')) as $name) {
                $path = (string) $name;
                $shape = is_array($fileShape[$name] ?? null) ? $fileShape[$name] : [];
                $properties[$path] = $this->buildOverlaySchema($shape, $requestFieldIndex, 'files', $path, 'string', true);
                if (($requestFieldIndex->get('files', $path)['required'] ?? false) === true) {
                    $required[] = $path;
                }
            }
        }

        if ($properties === []) {
            return $this->hydrateSchemaDescriptions(['type' => 'object']);
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        $required = array_values(array_unique($required));
        sort($required);
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $this->hydrateSchemaDescriptions($schema);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function shouldDocumentResponse(array $response, string $status): bool
    {
        if ((int) $status < 500) {
            return true;
        }

        if (($response['explicit'] ?? false) === true) {
            return true;
        }

        if (is_string($response['source'] ?? null) && trim((string) $response['source']) !== '') {
            return true;
        }

        if (is_string($response['via'] ?? null) && trim((string) $response['via']) !== '') {
            return true;
        }

        if (is_array($response['bodySchema'] ?? null) && $response['bodySchema'] !== []) {
            return true;
        }

        if (is_array($response['headers'] ?? null) && $response['headers'] !== []) {
            return true;
        }

        return is_string($response['contentType'] ?? null) && trim((string) $response['contentType']) !== '';
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, mixed>
     */
    private function buildOverlaySchema(array $shape, RequestFieldIndex $requestFieldIndex, string $location, string $path, ?string $fallbackLeafType, bool $files = false): array
    {
        $field = $requestFieldIndex->get($location, $path);

        if ($this->fieldRepresentsArray($shape, $field, $requestFieldIndex, $location, $path)) {
            $itemPath = $path.'[]';
            $itemShape = is_array($shape['_item'] ?? null) ? $shape['_item'] : [];
            $items = $this->buildArrayItemsSchema($itemShape, $requestFieldIndex, $location, $itemPath, $fallbackLeafType, $files);

            return $this->applyFieldOverlay([
                'type' => 'array',
                'items' => $items,
            ], $field, $files);
        }

        $childNames = $this->orderedNames(
            $this->shapeChildNames($shape),
            $this->overlayChildNames($requestFieldIndex, $location, $path),
        );

        if ($childNames !== []) {
            $properties = [];
            $required = [];

            foreach ($childNames as $childName) {
                $childPath = $path === '' ? $childName : $path.'.'.$childName;
                $childShape = is_array($shape[$childName] ?? null) ? $shape[$childName] : [];
                $properties[$childName] = $this->buildOverlaySchema($childShape, $requestFieldIndex, $location, $childPath, $fallbackLeafType, $files);

                if (($requestFieldIndex->get($location, $childPath)['required'] ?? false) === true) {
                    $required[] = $childName;
                }
            }

            $schema = [
                'type' => 'object',
                'properties' => $properties,
            ];
            if ($required !== []) {
                $required = array_values(array_unique($required));
                sort($required);
                $schema['required'] = $required;
            }

            return $this->applyFieldOverlay($schema, $field, $files);
        }

        return $this->applyFieldOverlay($this->fallbackLeafSchema($shape, $fallbackLeafType, $files), $field, $files);
    }

    /**
     * @param  array<string, mixed>|null  $field
     * @return array<string, mixed>
     */
    private function buildArrayItemsSchema(array $shape, RequestFieldIndex $requestFieldIndex, string $location, string $itemPath, ?string $fallbackLeafType, bool $files): array
    {
        $field = $requestFieldIndex->get($location, $itemPath);
        $childNames = $this->overlayChildNames($requestFieldIndex, $location, $itemPath);

        if ($shape === [] && $childNames === [] && ! $requestFieldIndex->has($location, $itemPath)) {
            return $this->fallbackLeafSchema([], $fallbackLeafType, $files);
        }

        return $this->buildOverlaySchema($shape, $requestFieldIndex, $location, $itemPath, $fallbackLeafType, $files);
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<string, mixed>|null  $field
     */
    private function queryParameterUsesCsvEncoding(string $name, array $shape, ?array $field, RequestFieldIndex $requestFieldIndex, string $location = 'query'): bool
    {
        return (($field['kind'] ?? null) === 'csv')
            || (in_array($name, ['include', 'sort'], true) && (($requestFieldIndex->allowedValues($location, $name) !== []) || $this->shapeHasChildren($shape)));
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<string, mixed>|null  $field
     */
    private function queryParameterUsesDeepObject(string $name, array $shape, ?array $field, RequestFieldIndex $requestFieldIndex, string $location = 'query'): bool
    {
        if ($this->queryParameterUsesCsvEncoding($name, $shape, $field, $requestFieldIndex, $location)) {
            return false;
        }

        return $this->shapeHasChildren($shape)
            || $this->overlayChildNames($requestFieldIndex, $location, $name) !== []
            || (($field['kind'] ?? null) === 'object');
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, mixed>
     */
    private function buildQueryParameterExtension(string $name, array $shape, RequestFieldIndex $requestFieldIndex, string $location = 'query'): array
    {
        $extension = [];

        $allowedValues = $requestFieldIndex->allowedValues($location, $name);
        if ($allowedValues === [] && in_array($name, ['include', 'sort'], true) && $this->shapeHasChildren($shape)) {
            $allowedValues = $this->flattenLeafPaths($shape);
        }
        if ($allowedValues !== []) {
            $extension['allowedValues'] = $allowedValues;
        }

        $allowedValuesByGroup = $this->queryAllowedValuesByGroup($name, $shape, $requestFieldIndex, $location);
        if ($allowedValuesByGroup !== []) {
            $extension['allowedValuesByGroup'] = $allowedValuesByGroup;
        }

        if ($name === 'sort' && ($allowedValues !== [] || $this->shapeHasChildren($shape))) {
            $extension['supportsDescending'] = true;
        }

        return $extension;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, list<string>>
     */
    private function queryAllowedValuesByGroup(string $name, array $shape, RequestFieldIndex $requestFieldIndex, string $location = 'query'): array
    {
        $groups = [];

        foreach ($this->overlayChildNames($requestFieldIndex, $location, $name) as $childName) {
            $values = $requestFieldIndex->allowedValues($location, $name.'.'.$childName);
            if ($values === []) {
                continue;
            }
            $groups[$childName] = $values;
        }

        if ($groups !== [] || $name !== 'fields') {
            ksort($groups);

            return $groups;
        }

        foreach ($shape as $childName => $childShape) {
            if (! is_array($childShape)) {
                continue;
            }
            $groups[(string) $childName] = $this->flattenLeafPaths($childShape);
        }

        ksort($groups);

        return array_filter($groups, static fn (array $values): bool => $values !== []);
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<string, mixed>|null  $field
     * @return array<string, mixed>
     */
    private function applyFieldOverlay(array $schema, ?array $field, bool $files): array
    {
        if (! is_array($field)) {
            return $schema;
        }

        $type = $this->fieldSchemaType($field, $schema['type'] ?? null, $files);
        if ($type !== null && $type !== '') {
            $schema['type'] = $type;
        }

        $format = is_string($field['format'] ?? null) ? trim((string) $field['format']) : '';
        if ($format !== '') {
            $schema['format'] = $format;
        } elseif ($files && (($field['type'] ?? null) === 'file' || ($field['itemType'] ?? null) === 'file')) {
            $schema['format'] = 'binary';
        }

        if (($field['nullable'] ?? false) === true) {
            $schema = $this->markSchemaNullable($schema);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>|null  $field
     */
    private function fieldSchemaType(?array $field, mixed $fallbackType, bool $files): mixed
    {
        if (! is_array($field)) {
            return $fallbackType;
        }

        $kind = is_string($field['kind'] ?? null) ? trim((string) $field['kind']) : '';
        $type = is_string($field['type'] ?? null) ? trim((string) $field['type']) : '';
        $scalarType = is_string($field['scalarType'] ?? null) ? trim((string) $field['scalarType']) : '';
        $isArray = ($field['isArray'] ?? false) === true || ($field['collection'] ?? false) === true;

        if ($isArray || $kind === 'collection') {
            return 'array';
        }
        if ($files && ($type === 'file' || (($field['itemType'] ?? null) === 'file'))) {
            return 'string';
        }
        if ($kind === 'object') {
            return 'object';
        }
        if ($kind === 'file') {
            return 'string';
        }
        if ($scalarType !== '') {
            return $scalarType;
        }
        if (in_array($type, ['string', 'integer', 'number', 'boolean', 'object', 'array'], true)) {
            return $type;
        }

        return $fallbackType;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, mixed>
     */
    private function fallbackLeafSchema(array $shape, ?string $fallbackLeafType, bool $files): array
    {
        if ($files) {
            return $this->fileShapeToSchema($shape);
        }

        return $this->shapeToSchema($shape, $fallbackLeafType);
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<string, mixed>|null  $field
     */
    private function fieldRepresentsArray(array $shape, ?array $field, RequestFieldIndex $requestFieldIndex, string $location, string $path): bool
    {
        if (array_key_exists('_item', $shape)) {
            return true;
        }

        if (! is_array($field)) {
            return $this->overlayHasArrayItems($requestFieldIndex, $location, $path);
        }

        return (($field['isArray'] ?? false) === true)
            || (($field['collection'] ?? false) === true)
            || $this->overlayHasArrayItems($requestFieldIndex, $location, $path);
    }

    private function overlayHasArrayItems(RequestFieldIndex $requestFieldIndex, string $location, string $path): bool
    {
        foreach ($requestFieldIndex->descendants($location, $path) as $field) {
            $fieldPath = is_string($field['path'] ?? null) ? (string) $field['path'] : '';
            if ($fieldPath === $path.'[]' || str_starts_with($fieldPath, $path.'[].')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return list<string>
     */
    private function shapeChildNames(array $shape): array
    {
        $children = array_values(array_filter(
            array_map('strval', array_keys($shape)),
            static fn (string $name): bool => $name !== '_item',
        ));
        sort($children);

        return $children;
    }

    /**
     * @return list<string>
     */
    private function overlayChildNames(RequestFieldIndex $requestFieldIndex, string $location, string $prefix): array
    {
        $children = [];

        foreach ($requestFieldIndex->allForLocation($location) as $field) {
            $path = is_string($field['path'] ?? null) ? (string) $field['path'] : '';
            if ($path === '') {
                continue;
            }

            $remainder = $this->overlayPathRemainder($path, $prefix);
            if ($remainder === null || $remainder === '') {
                continue;
            }

            $child = $this->firstOverlaySegment($remainder);
            if ($child === null || $child === '' || $child === '[]') {
                continue;
            }

            $children[$child] = true;
        }

        $names = array_keys($children);
        sort($names);

        return $names;
    }

    private function overlayPathRemainder(string $path, string $prefix): ?string
    {
        if ($prefix === '') {
            return $path;
        }
        if ($path === $prefix) {
            return null;
        }
        if (str_starts_with($path, $prefix.'.')) {
            return substr($path, strlen($prefix) + 1);
        }
        if (str_starts_with($path, $prefix.'[')) {
            return substr($path, strlen($prefix));
        }

        return null;
    }

    private function firstOverlaySegment(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, '[]')) {
            return '[]';
        }

        $delimiters = [];
        foreach (['.', '['] as $delimiter) {
            $position = strpos($path, $delimiter);
            if ($position !== false) {
                $delimiters[] = $position;
            }
        }
        if ($delimiters === []) {
            return $path;
        }

        return substr($path, 0, min($delimiters));
    }

    /**
     * @param  list<string>  $primary
     * @param  list<string>  $secondary
     * @return list<string>
     */
    private function orderedNames(array $primary, array $secondary): array
    {
        $ordered = [];
        foreach ([$primary, $secondary] as $names) {
            foreach ($names as $name) {
                $name = (string) $name;
                if ($name === '' || array_key_exists($name, $ordered)) {
                    continue;
                }
                $ordered[$name] = true;
            }
        }

        return array_keys($ordered);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function markSchemaNullable(array $schema): array
    {
        $type = $schema['type'] ?? null;
        if (is_string($type) && $type !== '') {
            $schema['type'] = array_values(array_unique([$type, 'null']));

            return $schema;
        }
        if (is_array($type)) {
            $types = array_values(array_unique(array_filter(
                array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $type),
                static fn (string $value): bool => $value !== '',
            )));
            if (! in_array('null', $types, true)) {
                $types[] = 'null';
            }
            $schema['type'] = $types;

            return $schema;
        }

        $schema['nullable'] = true;

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, mixed>
     */
    private function shapeToSchema(array $shape, ?string $leafType): array
    {
        if (array_key_exists('_item', $shape)) {
            return [
                'type' => 'array',
                'items' => $this->shapeToSchema(
                    is_array($shape['_item']) ? $shape['_item'] : [],
                    $leafType,
                ),
            ];
        }

        if ($shape === []) {
            return $leafType !== null ? ['type' => $leafType] : [];
        }

        $properties = [];
        foreach ($shape as $name => $childShape) {
            $properties[(string) $name] = $this->shapeToSchema(
                is_array($childShape) ? $childShape : [],
                $leafType,
            );
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    private function shapeHasChildren(mixed $shape): bool
    {
        return is_array($shape) && $shape !== [];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<string, mixed>
     */
    private function fileShapeToSchema(array $shape): array
    {
        if (array_key_exists('_item', $shape)) {
            return [
                'type' => 'array',
                'items' => $this->fileShapeToSchema(
                    is_array($shape['_item']) ? $shape['_item'] : [],
                ),
            ];
        }

        if ($shape === []) {
            return [
                'type' => 'string',
                'format' => 'binary',
            ];
        }

        $properties = [];
        foreach ($shape as $name => $childShape) {
            $properties[(string) $name] = $this->fileShapeToSchema(
                is_array($childShape) ? $childShape : [],
            );
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return list<string>
     */
    private function flattenLeafPaths(array $shape, string $prefix = ''): array
    {
        $paths = [];

        foreach ($shape as $name => $childShape) {
            if ($name === '_item') {
                continue;
            }

            $currentPath = $prefix === '' ? (string) $name : $prefix.'.'.$name;
            if (! is_array($childShape) || $childShape === []) {
                $paths[] = $currentPath;

                continue;
            }

            $paths = array_merge($paths, $this->flattenLeafPaths($childShape, $currentPath));
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, array<int, string>>>
     */
    private function buildSecurityRequirements(MergedOperation $operation, array $config): array
    {
        $securityConfig = (array) ($config['security'] ?? []);
        $scopeScheme = is_string($securityConfig['scope_scheme'] ?? null)
            ? $securityConfig['scope_scheme']
            : null;

        $profile = $operation->authProfile();
        $effectiveProfile = AuthProfile::fromMiddleware(
            array_merge($profile->authenticationMiddleware(), $profile->authorizationMiddleware()),
            [
                'auth' => (array) config('oxcribe.auth', []),
                'openapi' => [
                    'security' => array_replace_recursive(
                        (array) config('oxcribe.openapi.security', []),
                        $securityConfig,
                    ),
                ],
            ],
        );

        $requirements = $effectiveProfile->securityRequirements();
        if ($requirements === []) {
            return [];
        }

        if ($scopeScheme === null) {
            return $requirements;
        }

        $scopes = $this->extractAbilityScopes($effectiveProfile);
        if ($scopes === []) {
            return $requirements;
        }

        foreach ($requirements as $index => $requirement) {
            if (! array_key_exists($scopeScheme, $requirement)) {
                continue;
            }

            $requirements[$index][$scopeScheme] = $scopes;
        }

        return $requirements;
    }

    /**
     * @param  list<array<string, array<int, string>>>  $security
     * @return list<string>
     */
    private function securitySchemeNames(array $security): array
    {
        $names = [];
        foreach ($security as $requirement) {
            foreach (array_keys($requirement) as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @param  array<string, bool>  $usedSecuritySchemes
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildSecuritySchemes(array $usedSecuritySchemes, array $config): array
    {
        if ($usedSecuritySchemes === []) {
            return [];
        }

        $configuredSchemes = (array) (($config['security'] ?? [])['schemes'] ?? []);
        $schemes = [];
        foreach (array_keys($usedSecuritySchemes) as $schemeName) {
            $scheme = $configuredSchemes[$schemeName] ?? null;
            if (is_array($scheme)) {
                $schemes[$schemeName] = $scheme;
            }
        }

        return $schemes;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mergedOperationOverride(MergedOperation $operation, string $method, array $config): array
    {
        return array_replace_recursive(
            $this->controllerOverride($operation),
            $this->operationOverride($operation, $method, $config),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function controllerOverride(MergedOperation $operation): array
    {
        $controller = $operation->controller;
        if (! is_array($controller)) {
            return [];
        }

        $override = $controller['overrides'] ?? null;

        return is_array($override) ? $override : [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function operationOverride(MergedOperation $operation, string $method, array $config): array
    {
        foreach ((array) ($config['overrides'] ?? []) as $override) {
            if (! is_array($override)) {
                continue;
            }

            $match = (array) ($override['match'] ?? []);
            if (! $this->matchesOverride($operation, $method, $match)) {
                continue;
            }

            return (array) ($override['set'] ?? []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function matchesOverride(MergedOperation $operation, string $method, array $match): bool
    {
        if ($match === []) {
            return false;
        }

        $path = $this->normalizePathKey($operation->uri);
        $actionKey = $operation->routeMatch->actionKey;

        foreach ($match as $field => $expected) {
            if (! is_string($expected)) {
                return false;
            }

            $actual = match ($field) {
                'routeId' => $operation->routeId,
                'name' => $operation->name,
                'path' => $path,
                'method' => $method,
                'actionKey' => $actionKey,
                default => null,
            };

            if ($actual === null || $actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function applyOperationOverride(array $document, array $override): array
    {
        if ($override === []) {
            return $document;
        }

        foreach (['summary', 'description', 'operationId'] as $field) {
            if (is_string($override[$field] ?? null) && trim((string) $override[$field]) !== '') {
                $document[$field] = trim((string) $override[$field]);
            }
        }

        if (is_array($override['tags'] ?? null)) {
            $tags = array_values(array_filter(
                (array) $override['tags'],
                static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
            ));
            if ($tags !== []) {
                $document['tags'] = array_values(array_unique(array_map('trim', $tags)));
            }
        }

        if (is_bool($override['deprecated'] ?? null)) {
            $document['deprecated'] = (bool) $override['deprecated'];
        }

        if (is_array($override['externalDocs'] ?? null) && $override['externalDocs'] !== []) {
            $document['externalDocs'] = array_replace_recursive(
                (array) ($document['externalDocs'] ?? []),
                (array) $override['externalDocs'],
            );
        }

        if (array_key_exists('security', $override)) {
            $security = $override['security'];
            if ($security === null || $security === []) {
                unset($document['security']);
            } elseif (is_array($security)) {
                $document['security'] = $security;
            }
        }

        if (is_array($override['responses'] ?? null)) {
            $document['responses'] = array_replace_recursive(
                (array) ($document['responses'] ?? []),
                (array) $override['responses'],
            );
        }

        if (is_array($override['x-oxcribe'] ?? null)) {
            $document['x-oxcribe'] = array_replace_recursive(
                (array) ($document['x-oxcribe'] ?? []),
                (array) $override['x-oxcribe'],
            );
        }

        if (is_array($override['requestBody'] ?? null)) {
            $document['requestBody'] = array_replace_recursive(
                (array) ($document['requestBody'] ?? []),
                (array) $override['requestBody'],
            );
        }

        if (is_array($override['examples'] ?? null)) {
            $document = $this->applyResponseExamplesOverride($document, (array) $override['examples']);
        }

        if (is_array($override['extensions'] ?? null)) {
            $document = $this->applyOperationExtensions($document, (array) $override['extensions']);
        }

        $matchedSources = array_values(array_filter(
            (array) ($override['matchedSources'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
        if ($matchedSources !== []) {
            $document['x-oxcribe']['overrides']['matchedSources'] = $matchedSources;
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $examplesByStatus
     * @return array<string, mixed>
     */
    private function applyResponseExamplesOverride(array $document, array $examplesByStatus): array
    {
        foreach ($examplesByStatus as $status => $examples) {
            if (! is_array($examples)) {
                continue;
            }

            $statusKey = is_int($status) || is_string($status) ? (string) $status : '';
            if ($statusKey === '') {
                continue;
            }

            $response = (array) ($document['responses'][$statusKey] ?? []);
            if ($response === []) {
                $response = ['description' => 'Response'];
            }

            $mediaType = $this->responseMediaType($response) ?? 'application/json';
            $normalizedExamples = $this->normalizeExamples($examples);
            if ($normalizedExamples === []) {
                continue;
            }

            $existingExamples = (array) ($response['content'][$mediaType]['examples'] ?? []);
            $response['content'][$mediaType]['examples'] = array_replace_recursive($existingExamples, $normalizedExamples);
            $document['responses'][$statusKey] = $response;
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseMediaType(array $response): ?string
    {
        $content = $response['content'] ?? null;
        if (! is_array($content) || $content === []) {
            return null;
        }

        foreach (array_keys($content) as $mediaType) {
            if (is_string($mediaType) && $mediaType !== '') {
                return $mediaType;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $examples
     * @return array<string, mixed>
     */
    private function normalizeExamples(array $examples): array
    {
        if ($examples === []) {
            return [];
        }

        if ($this->looksLikeOpenApiExample($examples)) {
            return ['default' => $examples];
        }

        $normalized = [];
        foreach ($examples as $name => $example) {
            if ((! is_string($name) && ! is_int($name)) || ! is_array($example) || ! $this->looksLikeOpenApiExample($example)) {
                continue;
            }

            $normalized[(string) $name] = $example;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $example
     */
    private function looksLikeOpenApiExample(array $example): bool
    {
        foreach (['summary', 'description', 'value', 'externalValue'] as $key) {
            if (array_key_exists($key, $example)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $extensions
     * @return array<string, mixed>
     */
    private function applyOperationExtensions(array $document, array $extensions): array
    {
        foreach ($extensions as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'x-')) {
                continue;
            }

            if (is_array($value) && is_array($document[$key] ?? null)) {
                $document[$key] = array_replace_recursive((array) $document[$key], $value);

                continue;
            }

            $document[$key] = $value;
        }

        return $document;
    }

    /**
     * @return list<string>
     */
    private function extractAbilityScopes(AuthProfile $profile): array
    {
        $scopes = [];

        foreach ($profile->authorizationConstraints() as $constraint) {
            if (! is_array($constraint)) {
                continue;
            }

            $kind = is_string($constraint['kind'] ?? null) ? $constraint['kind'] : '';
            if (! in_array($kind, ['ability', 'abilities', 'can'], true)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(
                    static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                    (array) ($constraint['values'] ?? []),
                ),
                static fn (string $value): bool => $value !== '',
            ));

            if ($kind === 'can') {
                $ability = is_string($constraint['ability'] ?? null) ? trim((string) $constraint['ability']) : '';
                if ($ability !== '') {
                    $values[] = $ability;
                }
            }

            foreach ($values as $value) {
                $scopes[] = $value;
            }
        }

        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes;
    }
}
