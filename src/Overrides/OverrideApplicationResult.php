<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Overrides;

use Garaekz\Oxcribe\Data\OperationGraph;

final readonly class OverrideApplicationResult
{
    /**
     * @param  list<ResolvedOverride>  $resolutions
     */
    public function __construct(
        public OperationGraph $graph,
        public array $resolutions,
    ) {
    }
}
