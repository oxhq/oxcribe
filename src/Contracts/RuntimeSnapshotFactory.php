<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Contracts;

use Oxhq\Oxcribe\Data\RuntimeSnapshot;

interface RuntimeSnapshotFactory
{
    public function make(): RuntimeSnapshot;
}
