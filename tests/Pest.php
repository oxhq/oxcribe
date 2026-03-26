<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Tests\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class)->in('Feature', 'Unit');

function resolveOxinferSourceRoot(): string
{
    $candidates = array_filter([
        trim((string) getenv('OXINFER_SOURCE_ROOT')),
        dirname(__DIR__, 4).'/go/oxinfer',
    ]);

    foreach ($candidates as $candidate) {
        if (is_dir($candidate) && is_file($candidate.'/cmd/oxinfer/main.go')) {
            return $candidate;
        }
    }

    test()->markTestSkipped(
        'Oxinfer source root is not available. Set OXINFER_SOURCE_ROOT to run oxcribe end-to-end fixture tests.'
    );

    throw new RuntimeException('markTestSkipped() should interrupt execution.');
}

function configureFixtureOxinfer(string $fixtureRoot): void
{
    static $builtBinary = null;

    if (! is_string($builtBinary)) {
        $oxinferRoot = resolveOxinferSourceRoot();
        $oxinferBinary = $oxinferRoot.'/bin/oxinfer';

        if (! is_dir(dirname($oxinferBinary))) {
            mkdir(dirname($oxinferBinary), 0777, true);
        }

        $build = new Process(['go', 'build', '-o', $oxinferBinary, './cmd/oxinfer'], $oxinferRoot, [
            'GOEXPERIMENT' => 'jsonv2',
        ]);
        $build->mustRun();

        $builtBinary = $oxinferBinary;
    }

    config()->set('oxcribe.oxinfer.binary', $builtBinary);
    config()->set('oxcribe.oxinfer.working_directory', $fixtureRoot);
}
