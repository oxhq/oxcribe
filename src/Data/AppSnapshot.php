<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class AppSnapshot
{
    public function __construct(
        public string $basePath,
        public string $laravelVersion,
        public string $phpVersion,
        public string $appEnv,
    ) {}

    public function toArray(): array
    {
        return [
            'basePath' => $this->basePath,
            'laravelVersion' => $this->laravelVersion,
            'phpVersion' => $this->phpVersion,
            'appEnv' => $this->appEnv,
        ];
    }
}
