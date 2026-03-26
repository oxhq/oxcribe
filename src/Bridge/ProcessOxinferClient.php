<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Bridge;

use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\AnalysisResponse;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
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
        $binary = $this->resolveBinary($workingDirectory);

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

    private function resolveBinary(string $workingDirectory): string
    {
        $configured = trim((string) ($this->config['binary'] ?? 'oxinfer'));

        if ($configured === '') {
            throw new RuntimeException(
                'Unable to find the oxinfer binary: oxcribe.oxinfer.binary is empty. '.
                'Set OXINFER_BINARY or configure oxcribe.oxinfer.binary.',
            );
        }

        if ($this->looksLikePath($configured)) {
            $candidate = $this->normalizePath($configured, $workingDirectory);

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }

            throw new RuntimeException(
                sprintf(
                    'Unable to find the oxinfer binary at "%s". Set OXINFER_BINARY or oxcribe.oxinfer.binary to an executable path.',
                    $candidate,
                ),
            );
        }

        $finder = new ExecutableFinder;
        $found = $finder->find($configured, null, [
            $workingDirectory,
            $workingDirectory.DIRECTORY_SEPARATOR.'bin',
            $workingDirectory.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin',
        ]);

        if ($found !== null) {
            return $found;
        }

        throw new RuntimeException(
            sprintf(
                'Unable to find the oxinfer binary "%s". Add it to PATH, or set OXINFER_BINARY / oxcribe.oxinfer.binary to an executable path.',
                $configured,
            ),
        );
    }

    private function looksLikePath(string $binary): bool
    {
        return str_contains($binary, DIRECTORY_SEPARATOR)
            || str_starts_with($binary, '.')
            || str_starts_with($binary, '~');
    }

    private function normalizePath(string $binary, string $workingDirectory): string
    {
        if (str_starts_with($binary, '~/')) {
            $home = getenv('HOME') ?: '';

            return ($home !== '' ? rtrim($home, DIRECTORY_SEPARATOR) : '').DIRECTORY_SEPARATOR.ltrim(substr($binary, 2), DIRECTORY_SEPARATOR);
        }

        if (str_starts_with($binary, DIRECTORY_SEPARATOR)) {
            return $binary;
        }

        return rtrim($workingDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($binary, DIRECTORY_SEPARATOR);
    }
}
