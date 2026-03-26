<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class PackageInventorySnapshot
{
    public function __construct(
        public SpatiePackageSnapshot $spatie,
    ) {}

    public static function empty(): self
    {
        return new self(SpatiePackageSnapshot::empty());
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'spatie' => $this->spatie->toArray(),
        ];
    }

    /**
     * @return list<array{name: string, version?: string}>
     */
    public function toWireArray(): array
    {
        $packages = array_map(
            static fn (PackageSnapshot $package): array => $package->toWireArray(),
            $this->spatie->installedPackages(),
        );

        usort(
            $packages,
            static fn (array $left, array $right): int => $left['name'] <=> $right['name'],
        );

        return $packages;
    }
}
