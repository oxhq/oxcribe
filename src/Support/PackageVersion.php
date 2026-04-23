<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

final class PackageVersion
{
    public const TAG = 'v0.1.4';

    public static function label(): string
    {
        return 'oxcribe '.self::TAG;
    }
}
