<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\OpenApi\Support;

use Oxhq\Oxcribe\Data\MergedOperation;

final class EffectiveRequestFieldLocation
{
    /**
     * @param  array<string, mixed>|null  $request
     */
    public static function query(MergedOperation $operation, ?array $request, RequestFieldIndex $index): string
    {
        return self::shouldPromoteBodyFieldsToQuery($operation, $request, $index) ? 'body' : 'query';
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    public static function body(MergedOperation $operation, ?array $request, RequestFieldIndex $index): ?string
    {
        return self::shouldPromoteBodyFieldsToQuery($operation, $request, $index) ? null : 'body';
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    private static function shouldPromoteBodyFieldsToQuery(MergedOperation $operation, ?array $request, RequestFieldIndex $index): bool
    {
        $method = strtoupper((string) ($operation->methods[0] ?? 'GET'));
        if (! in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }

        $request = is_array($request) ? $request : [];
        $queryShape = is_array($request['query'] ?? null) ? $request['query'] : [];
        if ($queryShape !== []) {
            return false;
        }

        if ($index->allForLocation('query') !== []) {
            return false;
        }

        return $index->allForLocation('body') !== [];
    }
}
