<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Contracts;

use Garaekz\Oxcribe\Data\AnalysisRequest;
use Garaekz\Oxcribe\Data\AnalysisResponse;

interface OxinferClient
{
    public function analyze(AnalysisRequest $request): AnalysisResponse;
}
