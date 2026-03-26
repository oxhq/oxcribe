<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

final class ManifestFactory
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function make(string $projectRoot, array $config): array
    {
        $analysis = (array) ($config['analysis'] ?? []);
        $scan = (array) ($analysis['scan'] ?? []);

        return array_filter([
            'project' => [
                'root' => $projectRoot,
                'composer' => $analysis['composer'] ?? 'composer.json',
            ],
            'scan' => [
                'targets' => array_values((array) ($scan['targets'] ?? ['app', 'routes'])),
                'vendor_whitelist' => array_values((array) ($scan['vendor_whitelist'] ?? [])),
                'globs' => array_values((array) ($scan['globs'] ?? ['app/**/*.php', 'routes/**/*.php'])),
            ],
            'limits' => $this->normalizeAssoc((array) ($analysis['limits'] ?? [])),
            'cache' => $this->normalizeAssoc((array) ($analysis['cache'] ?? [])),
            'features' => $this->normalizeAssoc((array) ($analysis['features'] ?? [])),
        ], static fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeAssoc(array $value): array
    {
        return array_filter($value, static fn (mixed $item): bool => $item !== null);
    }
}
