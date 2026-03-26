<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class OperationGraph
{
    /**
     * @param  list<MergedOperation>  $operations
     * @param  list<Diagnostic>  $diagnostics
     * @param  array<int, array<string, mixed>>  $models
     * @param  array<int, array<string, mixed>>  $resources
     * @param  array<int, array<string, mixed>>  $polymorphic
     * @param  array<int, array<string, mixed>>  $broadcast
     */
    public function __construct(
        public array $operations,
        public array $diagnostics,
        public array $models,
        public array $resources,
        public array $polymorphic,
        public array $broadcast,
    ) {}
}
