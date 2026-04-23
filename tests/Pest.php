<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Tests\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class)->in('Feature', 'Unit');

function resolveOxinferSourceRoot(): string
{
    $candidates = array_filter([
        trim((string) getenv('OXINFER_SOURCE_ROOT')),
        dirname(__DIR__).'/../oxinfer',
        dirname(__DIR__, 4).'/go/oxinfer',
    ]);

    foreach ($candidates as $candidate) {
        if (is_dir($candidate) && is_file($candidate.'/Cargo.toml')) {
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
        $oxinferBinary = $oxinferRoot.'/target/release/oxinfer'.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
        if (! is_file($oxinferBinary)) {
            $command = ['cargo', 'build', '--release'];

            if (is_file($oxinferRoot.'/Cargo.lock')) {
                $command[] = '--locked';
            }

            $build = new Process($command, $oxinferRoot, null, null, 300);
            $build->mustRun();
        }

        $builtBinary = $oxinferBinary;
    }

    config()->set('oxcribe.oxinfer.binary', $builtBinary);
    config()->set('oxcribe.oxinfer.working_directory', $fixtureRoot);
    config()->set('oxcribe.analysis.cache.enabled', false);
}

function makePortablePhpCommand(string $directory, string $name, string $phpBody): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $implementationPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'-impl.php';
    file_put_contents($implementationPath, "<?php\n".$phpBody);

    if (DIRECTORY_SEPARATOR === '\\') {
        $wrapperPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'.cmd';
        $phpBinary = str_replace('"', '""', PHP_BINARY);
        file_put_contents(
            $wrapperPath,
            "@echo off\r\n\"{$phpBinary}\" \"%~dp0{$name}-impl.php\" %*\r\n"
        );

        return $wrapperPath;
    }

    $wrapperPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;
    $command = sprintf(
        "#!/bin/sh\nexec %s \"$(dirname \"$0\")/%s\" \"$@\"\n",
        escapeshellarg(PHP_BINARY),
        basename($implementationPath),
    );
    file_put_contents($wrapperPath, $command);
    chmod($wrapperPath, 0755);

    return $wrapperPath;
}
