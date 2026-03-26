<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Overrides;

final readonly class OverrideSet
{
    /**
     * @param  list<OverrideRule>  $rules
     * @param  list<string>  $sources
     */
    public function __construct(
        public array $rules = [],
        public array $sources = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @return array{rules: list<array<string, mixed>>, sources: list<string>}
     */
    public function toArray(): array
    {
        return [
            'rules' => array_map(
                static fn (OverrideRule $rule): array => [
                    'source' => $rule->source,
                    'routeId' => $rule->routeId,
                    'actionKey' => $rule->actionKey,
                    'uri' => $rule->uri,
                    'name' => $rule->name,
                    'prefix' => $rule->prefix,
                    'methods' => $rule->methods,
                    'include' => $rule->include,
                    'summary' => $rule->summary,
                    'operationId' => $rule->operationId,
                    'tags' => $rule->tags,
                    'security' => $rule->security,
                    'examples' => $rule->examples,
                    'extensions' => $rule->extensions,
                ],
                $this->rules,
            ),
            'sources' => $this->sources,
        ];
    }
}
