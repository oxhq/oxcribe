<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('downloads and installs the matching oxinfer binary', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';
    $contents = "fake-oxinfer-binary\n";
    $checksum = hash('sha256', $contents);

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');

    Http::fake([
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/checksums.txt' => Http::response(
            "{$checksum}  oxinfer_v0.1.1_linux_amd64\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/oxinfer_v0.1.1_linux_amd64' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.1',
        '--path' => $binaryPath,
        '--os' => 'linux',
        '--arch' => 'amd64',
    ])
        ->expectsOutput('Downloading v0.1.1 for linux/amd64…')
        ->expectsOutput(sprintf('Installed oxinfer v0.1.1 to %s', $binaryPath))
        ->assertSuccessful();

    expect(File::exists($binaryPath))->toBeTrue()
        ->and(File::get($binaryPath))->toBe($contents);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'checksums.txt'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oxinfer_v0.1.1_linux_amd64'));

    File::deleteDirectory($directory);
});

it('fails when the release checksum does not match the downloaded binary', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');

    Http::fake([
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/checksums.txt' => Http::response(
            str_repeat('a', 64).'  oxinfer_v0.1.1_linux_amd64'."\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/oxinfer_v0.1.1_linux_amd64' => Http::response(
            "wrong-binary\n",
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.1',
        '--path' => $binaryPath,
        '--os' => 'linux',
        '--arch' => 'amd64',
    ])
        ->expectsOutputToContain('Checksum verification failed')
        ->assertFailed();

    expect(File::exists($binaryPath))->toBeFalse();
});

it('appends the windows executable suffix when needed', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';
    $contents = "windows-binary\r\n";
    $checksum = hash('sha256', $contents);

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');

    Http::fake([
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/checksums.txt' => Http::response(
            "{$checksum}  oxinfer_v0.1.1_windows_amd64.exe\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/oxinfer_v0.1.1_windows_amd64.exe' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.1',
        '--path' => $binaryPath,
        '--os' => 'windows',
        '--arch' => 'amd64',
    ])->assertSuccessful();

    expect(File::exists($binaryPath.'.exe'))->toBeTrue();

    File::deleteDirectory($directory);
});

it('falls back to a local oxinfer source checkout when release checksums are unavailable', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';
    $sourceRoot = $directory.'/oxinfer-source';

    File::ensureDirectoryExists($sourceRoot.'/cmd/oxinfer');
    File::put($sourceRoot.'/cmd/oxinfer/main.go', "package main\nfunc main() {}\n");

    $cleanupGo = fakeGoBinary(<<<'SH'
#!/bin/sh
out=""
prev=""
for arg in "$@"; do
  if [ "$prev" = "-o" ]; then out="$arg"; fi
  prev="$arg"
done
if [ -z "$out" ]; then
  echo "missing -o" >&2
  exit 1
fi
printf 'source-built-binary\n' > "$out"
SH);

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');
    config()->set('oxcribe.oxinfer.source_root', $sourceRoot);

    Http::fake([
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.1/checksums.txt' => Http::response('', 404),
    ]);

    try {
        $this->artisan('oxcribe:install-binary', [
            'version' => 'v0.1.1',
            '--path' => $binaryPath,
            '--os' => 'linux',
            '--arch' => 'amd64',
        ])
            ->expectsOutput('Downloading v0.1.1 for linux/amd64…')
            ->expectsOutputToContain('Unable to download release checksums')
            ->expectsOutputToContain('Falling back to local oxinfer source')
            ->expectsOutput(sprintf('Installed oxinfer from source to %s', $binaryPath))
            ->assertSuccessful();
    } finally {
        $cleanupGo();
    }

    expect(File::exists($binaryPath))->toBeTrue()
        ->and(File::get($binaryPath))->toBe("source-built-binary\n");

    File::deleteDirectory($directory);
});

it('prefers a local oxinfer source checkout without hitting the network when requested', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';
    $sourceRoot = $directory.'/oxinfer-source';

    File::ensureDirectoryExists($sourceRoot.'/cmd/oxinfer');
    File::put($sourceRoot.'/cmd/oxinfer/main.go', "package main\nfunc main() {}\n");

    $cleanupGo = fakeGoBinary(<<<'SH'
#!/bin/sh
out=""
prev=""
for arg in "$@"; do
  if [ "$prev" = "-o" ]; then out="$arg"; fi
  prev="$arg"
done
printf 'source-preferred-binary\n' > "$out"
SH);

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');

    Http::fake();

    try {
        $this->artisan('oxcribe:install-binary', [
            'version' => 'v0.1.1',
            '--path' => $binaryPath,
            '--os' => 'linux',
            '--arch' => 'amd64',
            '--source-root' => $sourceRoot,
            '--prefer-source' => true,
        ])
            ->expectsOutputToContain('Building oxinfer from source')
            ->expectsOutput(sprintf('Installed oxinfer from source to %s', $binaryPath))
            ->assertSuccessful();
    } finally {
        $cleanupGo();
    }

    expect(File::exists($binaryPath))->toBeTrue()
        ->and(File::get($binaryPath))->toBe("source-preferred-binary\n");

    Http::assertNothingSent();

    File::deleteDirectory($directory);
});

function fakeGoBinary(string $script): callable
{
    $directory = sys_get_temp_dir().'/oxcribe-go-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/go';
    $originalPath = getenv('PATH') ?: '';

    File::ensureDirectoryExists($directory);
    File::put($binaryPath, $script);
    @chmod($binaryPath, 0755);
    putenv(sprintf('PATH=%s%s%s', $directory, PATH_SEPARATOR, $originalPath));

    return static function () use ($directory, $originalPath): void {
        putenv(sprintf('PATH=%s', $originalPath));
        File::deleteDirectory($directory);
    };
}
