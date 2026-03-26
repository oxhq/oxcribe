<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Overrides;

use Oxhq\Oxcribe\Data\MergedOperation;
use Oxhq\Oxcribe\Data\OperationGraph;

final class OverrideApplier
{
    public function apply(OperationGraph $graph, OverrideSet $overrideSet): OverrideApplicationResult
    {
        if ($overrideSet->isEmpty()) {
            return new OverrideApplicationResult($graph, []);
        }

        $operations = [];
        $resolutions = [];

        foreach ($graph->operations as $operation) {
            $resolution = $this->resolveOperation($operation, $overrideSet->rules);
            $resolutions[] = $resolution;

            if (! $resolution->included) {
                continue;
            }

            $operations[] = $this->applyResolution($operation, $resolution);
        }

        return new OverrideApplicationResult(
            graph: new OperationGraph(
                operations: $operations,
                diagnostics: $graph->diagnostics,
                models: $graph->models,
                resources: $graph->resources,
                polymorphic: $graph->polymorphic,
                broadcast: $graph->broadcast,
            ),
            resolutions: $resolutions,
        );
    }

    /**
     * @param  list<OverrideRule>  $rules
     */
    private function resolveOperation(MergedOperation $operation, array $rules): ResolvedOverride
    {
        $included = true;
        $summary = null;
        $description = null;
        $operationId = null;
        $tags = [];
        $deprecated = null;
        $security = [];
        $examples = [];
        $responses = [];
        $requestBody = [];
        $xOxcribe = [];
        $externalDocs = [];
        $extensions = [];
        $matchedSources = [];

        foreach ($rules as $rule) {
            if (! $rule instanceof OverrideRule || ! $rule->matches($operation)) {
                continue;
            }

            $matchedSources[] = $rule->source;
            $included = $rule->include;

            if ($rule->summary !== null) {
                $summary = $rule->summary;
            }

            if ($rule->description !== null) {
                $description = $rule->description;
            }

            if ($rule->operationId !== null) {
                $operationId = $rule->operationId;
            }

            $tags = $this->mergeStrings($tags, $rule->tags);
            if ($rule->deprecated !== null) {
                $deprecated = $rule->deprecated;
            }
            $security = $this->mergeNestedArrayList($security, $rule->security);
            $examples = array_replace_recursive($examples, $rule->examples);
            $responses = array_replace_recursive($responses, $rule->responses);
            $requestBody = array_replace_recursive($requestBody, $rule->requestBody);
            $xOxcribe = array_replace_recursive($xOxcribe, $rule->xOxcribe);
            $externalDocs = array_replace_recursive($externalDocs, $rule->externalDocs);
            $extensions = array_replace_recursive($extensions, $rule->extensions);
        }

        return new ResolvedOverride(
            routeId: $operation->routeId,
            included: $included,
            summary: $summary,
            description: $description,
            operationId: $operationId,
            tags: $tags,
            deprecated: $deprecated,
            security: $security,
            examples: $examples,
            responses: $responses,
            requestBody: $requestBody,
            xOxcribe: $xOxcribe,
            externalDocs: $externalDocs,
            extensions: $extensions,
            matchedSources: $matchedSources,
        );
    }

    private function applyResolution(MergedOperation $operation, ResolvedOverride $resolution): MergedOperation
    {
        $controller = $operation->controller;
        if ($controller === null) {
            $controller = [];
        }

        $overrides = array_filter([
            'summary' => $resolution->summary,
            'description' => $resolution->description,
            'operationId' => $resolution->operationId,
            'tags' => $resolution->tags,
            'deprecated' => $resolution->deprecated,
            'security' => $resolution->security,
            'examples' => $resolution->examples,
            'responses' => $resolution->responses,
            'requestBody' => $resolution->requestBody,
            'x-oxcribe' => $resolution->xOxcribe,
            'externalDocs' => $resolution->externalDocs,
            'extensions' => $resolution->extensions,
            'matchedSources' => $resolution->matchedSources,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($overrides !== []) {
            $controller['overrides'] = $overrides;
        }

        return new MergedOperation(
            routeId: $operation->routeId,
            methods: $operation->methods,
            uri: $operation->uri,
            domain: $operation->domain,
            name: $operation->name,
            prefix: $operation->prefix,
            middleware: $operation->middleware,
            where: $operation->where,
            defaults: $operation->defaults,
            bindings: $operation->bindings,
            action: $operation->action,
            routeMatch: $operation->routeMatch,
            controller: $controller !== [] ? $controller : null,
        );
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $incoming
     * @return list<string>
     */
    private function mergeStrings(array $current, array $incoming): array
    {
        foreach ($incoming as $value) {
            if (! in_array($value, $current, true)) {
                $current[] = $value;
            }
        }

        return array_values($current);
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeNestedArrayList(array $current, array $incoming): array
    {
        $seen = [];
        foreach ($current as $item) {
            $seen[md5(serialize($item))] = true;
        }

        foreach ($incoming as $item) {
            $fingerprint = md5(serialize($item));
            if (! isset($seen[$fingerprint])) {
                $seen[$fingerprint] = true;
                $current[] = $item;
            }
        }

        return array_values($current);
    }
}
