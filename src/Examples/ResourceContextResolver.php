<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Illuminate\Support\Str;

final class ResourceContextResolver
{
    public function resolve(string $fieldPath, string $operationKind, string $endpointPath): ?string
    {
        $fieldPhrase = $this->resolveFromPhrase($fieldPath);
        if ($fieldPhrase !== null) {
            return $fieldPhrase;
        }

        $fieldResource = $this->resolveFromSegments($this->fieldSegments($fieldPath));
        if ($fieldResource !== null) {
            return $fieldResource;
        }

        $endpointPhrase = $this->resolveFromPhrase($endpointPath);
        if ($endpointPhrase !== null) {
            return $endpointPhrase;
        }

        $endpointResource = $this->resolveFromSegments($this->tokenize($endpointPath, '/[\/._{}-]+/'));
        if ($endpointResource !== null) {
            return $endpointResource;
        }

        $operationPhrase = $this->resolveFromPhrase($operationKind);
        if ($operationPhrase !== null) {
            return $operationPhrase;
        }

        return $this->resolveFromSegments($this->tokenize($operationKind, '/[._\\\\]+/'));
    }

    private function resolveFromPhrase(string $value): ?string
    {
        $normalized = $this->normalize($value);

        foreach ([
            'creators_list',
            'creator_list',
            'roster_group',
            'watchlist',
            'collection',
            'catalog',
            'directory',
            'folder',
            'folders',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'collection';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function resolveFromSegments(array $segments): ?string
    {
        foreach (array_reverse($segments) as $segment) {
            $resource = $this->mapSegment($segment);
            if ($resource !== null) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function fieldSegments(string $fieldPath): array
    {
        $normalized = $this->normalize($fieldPath);
        $segments = array_values(array_filter(explode('.', str_replace('[]', '', $normalized))));
        array_pop($segments);

        return $segments;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $value, string $pattern): array
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(preg_split($pattern, $normalized) ?: []));
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value) ?? $value;

        return strtolower(str_replace(['*', '-'], ['', '_'], $value));
    }

    private function mapSegment(string $segment): ?string
    {
        $segment = trim($segment);
        if ($segment === '' || is_numeric($segment)) {
            return null;
        }

        $segment = Str::singular($segment);
        if (in_array($segment, $this->ignoredSegments(), true)) {
            return null;
        }

        return match ($segment) {
            'organization', 'org', 'company', 'business' => 'organization',
            'workspace' => 'workspace',
            'project', 'api', 'application', 'documentation', 'doc' => 'project',
            'game' => 'game',
            'collection', 'catalog', 'directory', 'folder', 'watchlist', 'playlist', 'group' => 'collection',
            'creator', 'user', 'member', 'owner', 'profile', 'person' => 'person',
            'broadcast', 'video', 'stream', 'movie', 'episode', 'article', 'post', 'page', 'series' => 'content',
            'platform_account', 'account' => 'platform_account',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function ignoredSegments(): array
    {
        return [
            'action',
            'api',
            'attribute',
            'attributes',
            'body',
            'controller',
            'data',
            'detail',
            'filter',
            'filters',
            'god',
            'http',
            'index',
            'item',
            'items',
            'meta',
            'minimal',
            'param',
            'params',
            'path',
            'payload',
            'query',
            'realistic',
            'request',
            'response',
            'result',
            'results',
            'single',
            'store',
            'update',
            'show',
            'destroy',
            'delete',
            'create',
            'list',
            'v1',
            'v2',
        ];
    }
}
