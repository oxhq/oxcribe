<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Contracts;

use Oxhq\Oxcribe\Data\PackageInventorySnapshot;

interface PackageInventoryDetector
{
    public function detect(string $projectRoot): PackageInventorySnapshot;
}
