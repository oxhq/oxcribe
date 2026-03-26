<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Contracts;

use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\AnalysisResponse;

interface OxinferClient
{
    public function analyze(AnalysisRequest $request): AnalysisResponse;
}
