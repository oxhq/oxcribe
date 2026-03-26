<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class ExampleField
{
    /**
     * @param  list<string>  $allowedValues
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $location,
        public string $baseType,
        public string $semanticType,
        public bool $required,
        public bool $nullable,
        public bool $collection,
        public ?string $itemType,
        public FieldConstraints $constraints,
        public FieldHints $hints,
        public ?string $format = null,
        public array $allowedValues = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'location' => $this->location,
            'baseType' => $this->baseType,
            'semanticType' => $this->semanticType,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'collection' => $this->collection,
            'itemType' => $this->itemType,
            'format' => $this->format,
            'allowedValues' => array_values($this->allowedValues),
            'constraints' => $this->constraints->toArray(),
            'hints' => $this->hints->toArray(),
        ];
    }
}
