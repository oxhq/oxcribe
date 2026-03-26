<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class SpatiePackageSnapshot
{
    public function __construct(
        public PackageSnapshot $laravelData,
        public PackageSnapshot $laravelQueryBuilder,
        public PackageSnapshot $laravelPermission,
        public PackageSnapshot $laravelMedialibrary,
        public PackageSnapshot $laravelTranslatable,
    ) {}

    public static function empty(): self
    {
        return new self(
            laravelData: PackageSnapshot::missing('spatie/laravel-data'),
            laravelQueryBuilder: PackageSnapshot::missing('spatie/laravel-query-builder'),
            laravelPermission: PackageSnapshot::missing('spatie/laravel-permission'),
            laravelMedialibrary: PackageSnapshot::missing('spatie/laravel-medialibrary'),
            laravelTranslatable: PackageSnapshot::missing('spatie/laravel-translatable'),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return [
            'laravelData' => $this->laravelData->toArray(),
            'laravelQueryBuilder' => $this->laravelQueryBuilder->toArray(),
            'laravelPermission' => $this->laravelPermission->toArray(),
            'laravelMedialibrary' => $this->laravelMedialibrary->toArray(),
            'laravelTranslatable' => $this->laravelTranslatable->toArray(),
        ];
    }

    /**
     * @return list<PackageSnapshot>
     */
    public function installedPackages(): array
    {
        return array_values(array_filter([
            $this->laravelData,
            $this->laravelQueryBuilder,
            $this->laravelPermission,
            $this->laravelMedialibrary,
            $this->laravelTranslatable,
        ], static fn (PackageSnapshot $package): bool => $package->installed));
    }
}
