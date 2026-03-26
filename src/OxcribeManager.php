<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe;

use Oxhq\Oxcribe\Bridge\AnalysisRequestFactory;
use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AnalysisResponse;
use Oxhq\Oxcribe\Data\OperationGraph;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Docs\DocsPayloadFactory;
use Oxhq\Oxcribe\Merge\OperationGraphMerger;
use Oxhq\Oxcribe\OpenApi\OpenApiDocumentFactory;
use Oxhq\Oxcribe\Overrides\OverrideApplier;
use Oxhq\Oxcribe\Overrides\OverrideLoader;
use Oxhq\Oxcribe\Overrides\OverrideSet;

final class OxcribeManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly RuntimeSnapshotFactory $runtimeSnapshotFactory,
        private readonly AnalysisRequestFactory $analysisRequestFactory,
        private readonly OxinferClient $oxinferClient,
        private readonly OperationGraphMerger $operationGraphMerger,
        private readonly OverrideLoader $overrideLoader,
        private readonly OverrideApplier $overrideApplier,
        private readonly OpenApiDocumentFactory $openApiDocumentFactory,
        private readonly DocsPayloadFactory $docsPayloadFactory,
        private readonly array $config,
    ) {}

    public function analyze(?string $projectRoot = null): AnalysisResponse
    {
        $runtime = $this->runtimeSnapshotFactory->make();
        $request = $this->analysisRequestFactory->make($runtime, $projectRoot);

        return $this->oxinferClient->analyze($request);
    }

    public function graph(?string $projectRoot = null, array $overrideFiles = []): OperationGraph
    {
        $graph = $this->buildGraph($projectRoot);
        $overrides = $this->overrideLoader->load($this->resolveProjectRoot($graph['runtime']->app->basePath, $projectRoot), $overrideFiles);

        return $this->overrideApplier->apply($graph['graph'], $overrides)->graph;
    }

    /**
     * @return array<string, mixed>
     */
    public function exportOpenApi(?string $projectRoot = null, array $overrideFiles = []): array
    {
        $graph = $this->graph($projectRoot, $overrideFiles);

        return $this->openApiDocumentFactory->make($graph, (array) ($this->config['openapi'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function docsPayload(?string $projectRoot = null, array $overrideFiles = []): array
    {
        $document = $this->exportOpenApi($projectRoot, $overrideFiles);

        return $this->docsPayloadFactory->make($document, [
            'defaultBaseUrl' => (string) config('app.url', ''),
        ]);
    }

    public function overrideSet(?string $projectRoot = null, array $overrideFiles = []): OverrideSet
    {
        $runtime = $this->runtimeSnapshotFactory->make();

        return $this->overrideLoader->load($this->resolveProjectRoot($runtime->app->basePath, $projectRoot), $overrideFiles);
    }

    /**
     * @return array{runtime: RuntimeSnapshot, graph: OperationGraph}
     */
    private function buildGraph(?string $projectRoot = null): array
    {
        $runtime = $this->runtimeSnapshotFactory->make();
        $request = $this->analysisRequestFactory->make($runtime, $projectRoot);
        $response = $this->oxinferClient->analyze($request);

        return [
            'runtime' => $runtime,
            'graph' => $this->operationGraphMerger->merge($runtime, $response),
        ];
    }

    private function resolveProjectRoot(string $fallback, ?string $projectRoot = null): string
    {
        return $projectRoot ?? $fallback;
    }
}
