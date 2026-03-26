<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use Oxhq\Oxcribe\Data\RouteAction;

final class RouteIdFactory
{
    /**
     * @param  list<string>  $methods
     */
    public function make(
        array $methods,
        ?string $domain,
        string $uri,
        RouteAction $action,
        ?string $name,
    ): string {
        $payload = implode('|', [
            implode(',', $methods),
            $domain ?? '',
            $uri,
            $action->signature(),
            $name ?? '',
        ]);

        return hash('sha256', $payload);
    }
}
