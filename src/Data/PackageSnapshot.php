<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class PackageSnapshot
{
    public function __construct(
        public string $name,
        public bool $installed,
        public ?string $version = null,
        public ?string $constraint = null,
        public ?string $source = null,
        public bool $dev = false,
    ) {}

    public static function installed(
        string $name,
        ?string $version = null,
        ?string $source = null,
        bool $dev = false,
    ): self {
        return new self(
            name: $name,
            installed: true,
            version: $version,
            constraint: null,
            source: $source,
            dev: $dev,
        );
    }

    public static function missing(
        string $name,
        ?string $constraint = null,
        ?string $source = null,
        bool $dev = false,
    ): self {
        return new self(
            name: $name,
            installed: false,
            version: null,
            constraint: $constraint,
            source: $source,
            dev: $dev,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'installed' => $this->installed,
            'version' => $this->version,
            'constraint' => $this->constraint,
            'source' => $this->source,
            'dev' => $this->dev,
        ];
    }

    /**
     * @return array{name: string, version?: string}
     */
    public function toWireArray(): array
    {
        $payload = [
            'name' => $this->name,
        ];

        if ($this->version !== null && $this->version !== '') {
            $payload['version'] = $this->version;
        }

        return $payload;
    }
}
