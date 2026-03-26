<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

final class PublishVersionResolver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(mixed $commandVersion, array $config): string
    {
        if (is_string($commandVersion) && trim($commandVersion) !== '') {
            return trim($commandVersion);
        }

        $configuredVersion = trim((string) ($config['default_version'] ?? ''));
        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $appVersion = trim((string) config('app.version', ''));
        if ($appVersion !== '') {
            return $appVersion;
        }

        return 'dev';
    }
}
