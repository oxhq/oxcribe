<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Contracts;

use Garaekz\Oxcribe\Data\PackageInventorySnapshot;

interface PackageInventoryDetector
{
    public function detect(string $projectRoot): PackageInventorySnapshot;
}
