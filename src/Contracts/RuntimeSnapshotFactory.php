<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Contracts;

use Garaekz\Oxcribe\Data\RuntimeSnapshot;

interface RuntimeSnapshotFactory
{
    public function make(): RuntimeSnapshot;
}
