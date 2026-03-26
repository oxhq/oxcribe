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
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/checksums.txt' => Http::response(
            "{$checksum}  oxinfer_v0.1.0_linux_amd64\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/oxinfer_v0.1.0_linux_amd64' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.0',
        '--path' => $binaryPath,
        '--os' => 'linux',
        '--arch' => 'amd64',
    ])
        ->expectsOutput('Downloading v0.1.0 for linux/amd64…')
        ->expectsOutput(sprintf('Installed oxinfer v0.1.0 to %s', $binaryPath))
        ->assertSuccessful();

    expect(File::exists($binaryPath))->toBeTrue()
        ->and(File::get($binaryPath))->toBe($contents);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'checksums.txt'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oxinfer_v0.1.0_linux_amd64'));

    File::deleteDirectory($directory);
});

it('fails when the release checksum does not match the downloaded binary', function () {
    $directory = sys_get_temp_dir().'/oxcribe-install-'.bin2hex(random_bytes(6));
    $binaryPath = $directory.'/bin/oxinfer';

    config()->set('oxcribe.oxinfer.release.repository', 'oxhq/oxinfer');
    config()->set('oxcribe.oxinfer.release.base_url', 'https://github.com');

    Http::fake([
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/checksums.txt' => Http::response(
            str_repeat('a', 64).'  oxinfer_v0.1.0_linux_amd64'."\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/oxinfer_v0.1.0_linux_amd64' => Http::response(
            "wrong-binary\n",
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.0',
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
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/checksums.txt' => Http::response(
            "{$checksum}  oxinfer_v0.1.0_windows_amd64.exe\n",
        ),
        'https://github.com/oxhq/oxinfer/releases/download/v0.1.0/oxinfer_v0.1.0_windows_amd64.exe' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('oxcribe:install-binary', [
        'version' => 'v0.1.0',
        '--path' => $binaryPath,
        '--os' => 'windows',
        '--arch' => 'amd64',
    ])->assertSuccessful();

    expect(File::exists($binaryPath.'.exe'))->toBeTrue();

    File::deleteDirectory($directory);
});
