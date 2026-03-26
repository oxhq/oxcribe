<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Docs;

final class DocsPayloadFactory
{
    private const CONTRACT_VERSION = 'oxcribe.docs.v1';

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function make(array $document, array $options = []): array
    {
        return [
            'contractVersion' => self::CONTRACT_VERSION,
            'info' => [
                'title' => (string) ($document['info']['title'] ?? 'Oxcribe Docs'),
                'version' => (string) ($document['info']['version'] ?? '0.1.0'),
                'openapi' => (string) ($document['openapi'] ?? '3.1.0'),
            ],
            'meta' => [
                'defaultBaseUrl' => trim((string) ($options['defaultBaseUrl'] ?? '')),
                'operationCount' => (int) ($document['x-oxcribe']['operationCount'] ?? 0),
                'diagnosticCount' => (int) ($document['x-oxcribe']['diagnosticCount'] ?? 0),
                'viewer' => 'universal',
            ],
            'operations' => $this->flattenOperations((array) ($document['paths'] ?? [])),
            'components' => [
                'schemas' => (array) ($document['components']['schemas'] ?? []),
                'securitySchemes' => (array) ($document['components']['securitySchemes'] ?? []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return list<array<string, mixed>>
     */
    private function flattenOperations(array $paths): array
    {
        $operations = [];
        ksort($paths);

        foreach ($paths as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            ksort($methods);
            foreach ($methods as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $xOxcribe = (array) ($operation['x-oxcribe'] ?? []);
                $operations[] = [
                    'id' => (string) ($operation['operationId'] ?? strtoupper((string) $method).':'.$path),
                    'method' => strtoupper((string) $method),
                    'path' => (string) $path,
                    'summary' => (string) ($operation['summary'] ?? $operation['description'] ?? $path),
                    'description' => is_string($operation['description'] ?? null) ? (string) $operation['description'] : null,
                    'tags' => array_values(array_filter(
                        (array) ($operation['tags'] ?? []),
                        static fn (mixed $value): bool => is_string($value) && $value !== '',
                    )),
                    'security' => array_values((array) ($operation['security'] ?? [])),
                    'parameters' => array_values((array) ($operation['parameters'] ?? [])),
                    'requestBody' => is_array($operation['requestBody'] ?? null) ? (array) $operation['requestBody'] : null,
                    'responses' => (array) ($operation['responses'] ?? []),
                    'examples' => (array) ($xOxcribe['examples'] ?? []),
                    'snippets' => (array) ($xOxcribe['snippets'] ?? []),
                    'scenarios' => (array) ($xOxcribe['scenarios'] ?? []),
                    'runtime' => array_filter([
                        'routeId' => is_string($xOxcribe['routeId'] ?? null) ? (string) $xOxcribe['routeId'] : null,
                        'actionKey' => is_string($xOxcribe['actionKey'] ?? null) ? (string) $xOxcribe['actionKey'] : null,
                        'matchStatus' => is_string($xOxcribe['matchStatus'] ?? null) ? (string) $xOxcribe['matchStatus'] : null,
                        'middleware' => is_array($xOxcribe['middleware'] ?? null) ? array_values((array) $xOxcribe['middleware']) : null,
                        'authorization' => is_array($xOxcribe['authorization'] ?? null) ? (array) $xOxcribe['authorization'] : null,
                        'authorizationStatic' => is_array($xOxcribe['authorizationStatic'] ?? null) ? (array) $xOxcribe['authorizationStatic'] : null,
                        'auth' => is_array($xOxcribe['auth'] ?? null) ? (array) $xOxcribe['auth'] : null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== []),
                ];
            }
        }

        usort($operations, static function (array $left, array $right): int {
            return [$left['path'], $left['method'], $left['id']] <=> [$right['path'], $right['method'], $right['id']];
        });

        return $operations;
    }
}
