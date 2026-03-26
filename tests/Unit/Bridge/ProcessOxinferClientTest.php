<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Bridge\ProcessOxinferClient;
use Oxhq\Oxcribe\Data\AnalysisRequest;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;

it('resolves the oxinfer binary from the project bin directory', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-process-client-'.bin2hex(random_bytes(4));
    $binDir = $projectRoot.'/bin';
    mkdir($binDir, 0777, true);

    $responsePayload = json_encode([
        'contractVersion' => 'oxcribe.oxinfer.v2',
        'requestId' => 'req-1',
        'runtimeFingerprint' => 'fp-1',
        'status' => 'ok',
        'meta' => [
            'oxinferVersion' => '0.1.0',
            'partial' => false,
            'stats' => [
                'filesParsed' => 0,
                'skipped' => 0,
                'durationMs' => 0,
            ],
            'diagnosticCounts' => [
                'info' => 0,
                'warn' => 0,
                'error' => 0,
            ],
        ],
        'delta' => [
            'meta' => [
                'partial' => false,
                'stats' => [
                    'filesParsed' => 0,
                    'skipped' => 0,
                    'durationMs' => 0,
                ],
            ],
            'controllers' => [],
            'models' => [],
            'resources' => [],
            'polymorphic' => [],
            'broadcast' => [],
        ],
        'routeMatches' => [],
        'diagnostics' => [],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $binaryPath = $binDir.'/oxinfer';
    file_put_contents($binaryPath, "#!/bin/sh\ncat >/dev/null\nprintf '%s' ".escapeshellarg($responsePayload)."\n");
    chmod($binaryPath, 0755);

    $client = new ProcessOxinferClient([
        'binary' => 'oxinfer',
        'working_directory' => $projectRoot,
        'timeout' => 5,
    ]);

    $response = $client->analyze(fakeAnalysisRequest($projectRoot));

    expect($response->contractVersion)->toBe('oxcribe.oxinfer.v2')
        ->and($response->status)->toBe('ok');
});

it('throws an actionable error when the oxinfer binary cannot be resolved', function () {
    $client = new ProcessOxinferClient([
        'binary' => 'definitely-missing-oxinfer',
        'working_directory' => sys_get_temp_dir(),
        'timeout' => 5,
    ]);

    expect(fn () => $client->analyze(fakeAnalysisRequest(sys_get_temp_dir())))
        ->toThrow(RuntimeException::class, 'OXINFER_BINARY / oxcribe.oxinfer.binary');
});

function fakeAnalysisRequest(string $projectRoot): AnalysisRequest
{
    return new AnalysisRequest(
        contractVersion: 'oxcribe.oxinfer.v2',
        requestId: 'req-1',
        runtimeFingerprint: 'fp-1',
        manifest: [
            'composer' => 'composer.json',
            'scan' => [
                'targets' => ['app', 'routes'],
                'globs' => ['app/**/*.php', 'routes/**/*.php'],
                'vendor_whitelist' => [],
            ],
            'limits' => [
                'max_workers' => 1,
                'max_files' => 10,
                'max_depth' => 1,
            ],
            'cache' => [
                'enabled' => false,
                'kind' => 'mtime',
            ],
        ],
        runtime: new RuntimeSnapshot(
            app: new AppSnapshot(
                basePath: $projectRoot,
                laravelVersion: '12.0.0',
                phpVersion: PHP_VERSION,
                appEnv: 'testing',
            ),
            routes: [],
        ),
    );
}
