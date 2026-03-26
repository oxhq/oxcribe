<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Overrides;

final readonly class ResolvedOverride
{
    /**
     * @param  list<string>  $tags
     * @param  list<array<string, mixed>>  $security
     * @param  array<string, mixed>  $examples
     * @param  array<string, mixed>  $responses
     * @param  array<string, mixed>  $requestBody
     * @param  array<string, mixed>  $xOxcribe
     * @param  array<string, mixed>  $externalDocs
     * @param  array<string, mixed>  $extensions
     * @param  list<string>  $matchedSources
     */
    public function __construct(
        public string $routeId,
        public bool $included,
        public ?string $summary,
        public ?string $description,
        public ?string $operationId,
        public array $tags,
        public ?bool $deprecated,
        public array $security,
        public array $examples,
        public array $responses,
        public array $requestBody,
        public array $xOxcribe,
        public array $externalDocs,
        public array $extensions,
        public array $matchedSources,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'routeId' => $this->routeId,
            'included' => $this->included,
            'summary' => $this->summary,
            'description' => $this->description,
            'operationId' => $this->operationId,
            'tags' => $this->tags,
            'deprecated' => $this->deprecated,
            'security' => $this->security,
            'examples' => $this->examples,
            'responses' => $this->responses,
            'requestBody' => $this->requestBody,
            'x-oxcribe' => $this->xOxcribe,
            'externalDocs' => $this->externalDocs,
            'extensions' => $this->extensions,
            'matchedSources' => $this->matchedSources,
        ];
    }
}
