<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Bridge;

use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\AnalysisResponse;
use Oxhq\Oxcribe\Support\OxinferBinaryResolver;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ProcessOxinferClient implements OxinferClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function analyze(AnalysisRequest $request): AnalysisResponse
    {
        $workingDirectory = (string) ($this->config['working_directory'] ?? $request->runtime->app->basePath);
        $timeout = (float) ($this->config['timeout'] ?? 120);
        $binary = (new OxinferBinaryResolver)->resolve($this->config, $workingDirectory);

        $process = new Process([$binary, '--request', '-'], $workingDirectory, null, null, $timeout);
        $process->setInput($request->toWireJson());
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());

            throw new RuntimeException(
                sprintf(
                    'Oxinfer failed with exit code %d%s',
                    $process->getExitCode() ?? 1,
                    $stderr !== '' ? ': '.$stderr : '',
                ),
            );
        }

        return AnalysisResponse::fromJson($process->getOutput());
    }
}
