<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Data\PackageInventorySnapshot;
use Oxhq\Oxcribe\Data\PackageSnapshot;
use Oxhq\Oxcribe\Data\SpatiePackageSnapshot;

final class InstalledPackageDetector implements PackageInventoryDetector
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function detect(string $projectRoot): PackageInventorySnapshot
    {
        $analysis = (array) ($this->config['analysis'] ?? []);
        $composerPath = (string) ($analysis['composer'] ?? 'composer.json');
        $composerLockPath = (string) ($analysis['composer_lock'] ?? 'composer.lock');
        $supportedPackages = $this->supportedPackages($analysis);

        $composerJson = $this->readJson($this->resolvePath($projectRoot, $composerPath));
        $composerLock = $this->readJson($this->resolvePath($projectRoot, $composerLockPath));

        return new PackageInventorySnapshot(
            spatie: new SpatiePackageSnapshot(
                laravelData: $this->resolvePackage(
                    name: 'spatie/laravel-data',
                    composerJson: $composerJson,
                    composerLock: $composerLock,
                    supportedPackages: $supportedPackages,
                ),
                laravelQueryBuilder: $this->resolvePackage(
                    name: 'spatie/laravel-query-builder',
                    composerJson: $composerJson,
                    composerLock: $composerLock,
                    supportedPackages: $supportedPackages,
                ),
                laravelPermission: $this->resolvePackage(
                    name: 'spatie/laravel-permission',
                    composerJson: $composerJson,
                    composerLock: $composerLock,
                    supportedPackages: $supportedPackages,
                ),
                laravelMedialibrary: $this->resolvePackage(
                    name: 'spatie/laravel-medialibrary',
                    composerJson: $composerJson,
                    composerLock: $composerLock,
                    supportedPackages: $supportedPackages,
                ),
                laravelTranslatable: $this->resolvePackage(
                    name: 'spatie/laravel-translatable',
                    composerJson: $composerJson,
                    composerLock: $composerLock,
                    supportedPackages: $supportedPackages,
                ),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, string>
     */
    private function supportedPackages(array $analysis): array
    {
        $packages = (array) ($analysis['packages']['spatie'] ?? []);
        $resolved = [];

        foreach ($packages as $field => $packageName) {
            if (is_string($field) && is_string($packageName) && $field !== '' && $packageName !== '') {
                $resolved[$packageName] = $field;
            }
        }

        if ($resolved !== []) {
            return $resolved;
        }

        return [
            'spatie/laravel-data' => 'laravelData',
            'spatie/laravel-query-builder' => 'laravelQueryBuilder',
            'spatie/laravel-permission' => 'laravelPermission',
            'spatie/laravel-medialibrary' => 'laravelMedialibrary',
            'spatie/laravel-translatable' => 'laravelTranslatable',
        ];
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @param  array<string, mixed>  $composerLock
     * @param  array<string, string>  $supportedPackages
     */
    private function resolvePackage(
        string $name,
        array $composerJson,
        array $composerLock,
        array $supportedPackages,
    ): PackageSnapshot {
        if (! isset($supportedPackages[$name])) {
            return PackageSnapshot::missing($name);
        }

        $lockPackages = $this->indexedPackages((array) ($composerLock['packages'] ?? []), false);
        $lockDevPackages = $this->indexedPackages((array) ($composerLock['packages-dev'] ?? []), true);
        $lockPackage = $lockPackages[$name] ?? $lockDevPackages[$name] ?? null;

        if (is_array($lockPackage)) {
            return PackageSnapshot::installed(
                name: $name,
                version: $this->stringValue($lockPackage['pretty_version'] ?? $lockPackage['version'] ?? null),
                source: 'composer.lock',
                dev: $lockPackage['dev'] ?? false,
            );
        }

        $constraint = $this->composerConstraint($composerJson, $name);
        $dev = $this->isDevRequirement($composerJson, $name);

        return PackageSnapshot::missing(
            name: $name,
            constraint: $constraint,
            source: $constraint !== null ? 'composer.json' : null,
            dev: $dev,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @return array<string, array<string, mixed>>
     */
    private function indexedPackages(array $packages, bool $dev): array
    {
        $indexed = [];

        foreach ($packages as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                continue;
            }

            $package['dev'] = $dev;
            $indexed[$package['name']] = $package;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $composerJson
     */
    private function composerConstraint(array $composerJson, string $name): ?string
    {
        foreach (['require', 'require-dev'] as $section) {
            $requirements = (array) ($composerJson[$section] ?? []);
            if (isset($requirements[$name]) && is_string($requirements[$name])) {
                return $requirements[$name];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $composerJson
     */
    private function isDevRequirement(array $composerJson, string $name): bool
    {
        $requirements = (array) ($composerJson['require-dev'] ?? []);

        return isset($requirements[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolvePath(string $projectRoot, string $path): string
    {
        return rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
