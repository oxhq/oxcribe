<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\ExampleScenario;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;

final class ScenarioResolver
{
    /**
     * @return list<ExampleScenario>
     */
    public function resolve(OperationExampleSpec $spec): array
    {
        $scenarios = [];

        if ($this->hasCollectionFields($spec->requestFields) || $this->hasCollectionFields($spec->responseFields)) {
            $scenarios[] = new ExampleScenario(
                key: 'single_item',
                label: 'Single item',
                description: 'Array fields resolve to a single representative item.',
                arrayCount: 1,
            );
            $scenarios[] = new ExampleScenario(
                key: 'multiple_items',
                label: 'Multiple items',
                description: 'Array fields expand to a richer multi-item example.',
                arrayCount: 3,
            );
        }

        return $scenarios;
    }

    /**
     * @param  list<ExampleField>  $fields
     */
    private function hasCollectionFields(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field->collection || str_contains($field->path, '[]')) {
                return true;
            }
        }

        return false;
    }
}
