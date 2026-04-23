<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Oxhq\Oxcribe\Support\PackageVersion;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class InstallBinaryCommand extends Command
{
    protected $signature = 'oxcribe:install-binary
        {version? : Release version or tag to install}
        {--path= : Install destination relative to the app base path}
        {--os= : Override operating system (linux, darwin, windows)}
        {--arch= : Override CPU architecture (amd64, arm64)}
        {--source-root= : Build oxinfer from a local source checkout}
        {--prefer-source : Prefer building from local source when one is configured}
        {--force : Replace an existing binary at the destination}';

    protected $description = 'Download and install the matching oxinfer binary for this machine';

    public function handle(): int
    {
        try {
            $config = (array) config('oxcribe.oxinfer', []);
            $release = (array) ($config['release'] ?? []);
            $tag = $this->resolveTag($this->argument('version'), $release);
            $os = $this->resolveOperatingSystem($this->option('os'));
            $arch = $this->resolveArchitecture($this->option('arch'));
            $repository = trim((string) ($release['repository'] ?? ''));
            $baseUrl = rtrim(trim((string) ($release['base_url'] ?? 'https://github.com')), '/');
            $sourceRoot = $this->resolveSourceRoot($this->option('source-root'), $config);

            $installPath = $this->resolveInstallPath(
                (string) ($this->option('path') ?: ($config['install_path'] ?? 'bin/oxinfer')),
                $os,
            );

            if (is_file($installPath) && ! $this->option('force')) {
                throw new RuntimeException(sprintf(
                    'A binary already exists at "%s". Re-run with --force to replace it.',
                    $installPath,
                ));
            }

            if ($repository === '') {
                if ($sourceRoot === null) {
                    throw new RuntimeException('Missing oxcribe.oxinfer.release.repository / OXINFER_RELEASE_REPOSITORY.');
                }

                $this->buildFromSource($sourceRoot, $installPath, $os);

                return self::SUCCESS;
            }

            $asset = $this->assetName($tag, $os, $arch);
            $checksumsUrl = sprintf('%s/%s/releases/download/%s/checksums.txt', $baseUrl, $repository, $tag);
            $assetUrl = sprintf('%s/%s/releases/download/%s/%s', $baseUrl, $repository, $tag, $asset);

            if ($this->option('prefer-source') && $sourceRoot !== null) {
                $this->buildFromSource($sourceRoot, $installPath, $os);

                return self::SUCCESS;
            }

            try {
                $this->installFromRelease($tag, $os, $arch, $asset, $checksumsUrl, $assetUrl, $installPath);
            } catch (RuntimeException $exception) {
                if ($sourceRoot === null) {
                    throw $exception;
                }

                $this->warn($exception->getMessage());
                $this->line(sprintf('Falling back to local oxinfer source at %s...', $sourceRoot));
                $this->buildFromSource($sourceRoot, $installPath, $os);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function resolveTag(mixed $value, array $release): string
    {
        $resolved = is_string($value) && trim($value) !== ''
            ? trim($value)
            : trim((string) ($release['version'] ?? ''));

        if ($resolved === '') {
            $resolved = PackageVersion::TAG;
        }

        return str_starts_with($resolved, 'v') ? $resolved : 'v'.$resolved;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveSourceRoot(mixed $value, array $config): ?string
    {
        $resolved = trim((string) $value);

        if ($resolved === '') {
            $resolved = trim((string) ($config['source_root'] ?? ''));
        }

        if ($resolved === '') {
            return null;
        }

        return $this->isAbsolutePath($resolved)
            ? $resolved
            : base_path($resolved);
    }

    private function resolveOperatingSystem(mixed $value): string
    {
        $resolved = strtolower(trim((string) $value));

        if ($resolved !== '') {
            return match ($resolved) {
                'mac', 'macos', 'darwin' => 'darwin',
                'win', 'windows' => 'windows',
                'linux' => 'linux',
                default => throw new RuntimeException(sprintf('Unsupported operating system "%s".', $resolved)),
            };
        }

        return match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            'Linux' => 'linux',
            default => throw new RuntimeException(sprintf('Unsupported operating system family "%s".', PHP_OS_FAMILY)),
        };
    }

    private function resolveArchitecture(mixed $value): string
    {
        $resolved = strtolower(trim((string) $value));

        if ($resolved === '') {
            $resolved = strtolower((string) php_uname('m'));
        }

        return match ($resolved) {
            'x86_64', 'amd64' => 'amd64',
            'arm64', 'aarch64' => 'arm64',
            default => throw new RuntimeException(sprintf('Unsupported architecture "%s".', $resolved)),
        };
    }

    private function resolveInstallPath(string $path, string $os): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            throw new RuntimeException('Install path is empty. Set OXINFER_INSTALL_PATH or pass --path.');
        }

        $resolved = $this->isAbsolutePath($trimmed)
            ? $trimmed
            : base_path($trimmed);

        if ($os === 'windows' && ! str_ends_with(strtolower($resolved), '.exe')) {
            return $resolved.'.exe';
        }

        return $resolved;
    }

    private function assetName(string $tag, string $os, string $arch): string
    {
        return sprintf(
            'oxinfer_%s_%s_%s%s',
            $tag,
            $os,
            $arch,
            $os === 'windows' ? '.exe' : '',
        );
    }

    private function installFromRelease(
        string $tag,
        string $os,
        string $arch,
        string $asset,
        string $checksumsUrl,
        string $assetUrl,
        string $installPath,
    ): void {
        $this->line(sprintf('Downloading %s for %s/%s...', $tag, $os, $arch));

        $checksumsResponse = Http::accept('text/plain')
            ->timeout(30)
            ->get($checksumsUrl);
        if ($checksumsResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Unable to download release checksums from %s (status %d).',
                $checksumsUrl,
                $checksumsResponse->status(),
            ));
        }

        $expectedChecksum = $this->resolveChecksum($checksumsResponse->body(), $asset);
        $binaryResponse = Http::timeout(120)->get($assetUrl);
        if ($binaryResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Unable to download %s from %s (status %d).',
                $asset,
                $assetUrl,
                $binaryResponse->status(),
            ));
        }

        $binaryContents = $binaryResponse->body();
        $actualChecksum = hash('sha256', $binaryContents);

        if (! hash_equals($expectedChecksum, $actualChecksum)) {
            throw new RuntimeException(sprintf(
                'Checksum verification failed for %s. Expected %s, received %s.',
                $asset,
                $expectedChecksum,
                $actualChecksum,
            ));
        }

        $this->writeInstalledBinary($installPath, $os, $binaryContents);
        $this->info(sprintf('Installed oxinfer %s to %s', $tag, $installPath));
    }

    private function buildFromSource(string $sourceRoot, string $installPath, string $os): void
    {
        $this->assertSourceRoot($sourceRoot);

        $finder = new ExecutableFinder;
        $cargoBinary = $finder->find('cargo');

        if ($cargoBinary === null) {
            throw new RuntimeException(
                'Unable to build oxinfer from source because the `cargo` executable is not available on PATH.'
            );
        }

        $command = [$cargoBinary, 'build', '--release'];
        if (is_file($sourceRoot.'/Cargo.lock')) {
            $command[] = '--locked';
        }

        $this->line(sprintf('Building oxinfer from source at %s...', $sourceRoot));

        $build = new Process($command, $sourceRoot, null, null, 300);
        $build->run();

        if (! $build->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "Unable to build oxinfer from source at %s.\n%s",
                $sourceRoot,
                trim($build->getErrorOutput() !== '' ? $build->getErrorOutput() : $build->getOutput()),
            ));
        }

        $builtBinary = $this->builtBinaryPath($sourceRoot, $os);
        if (! is_file($builtBinary)) {
            throw new RuntimeException(sprintf(
                'Cargo build completed, but the expected oxinfer binary was not found at "%s".',
                $builtBinary,
            ));
        }

        $binaryContents = File::get($builtBinary);
        $this->writeInstalledBinary($installPath, $os, $binaryContents);
        $this->info(sprintf('Installed oxinfer from source to %s', $installPath));
    }

    private function assertSourceRoot(string $sourceRoot): void
    {
        if (! is_dir($sourceRoot)) {
            throw new RuntimeException(sprintf(
                'Configured oxinfer source root "%s" does not exist.',
                $sourceRoot,
            ));
        }

        if (! is_file($sourceRoot.'/Cargo.toml')) {
            throw new RuntimeException(sprintf(
                'Configured oxinfer source root "%s" is missing Cargo.toml.',
                $sourceRoot,
            ));
        }
    }

    private function builtBinaryPath(string $sourceRoot, string $os): string
    {
        $requested = $sourceRoot.'/target/release/oxinfer'.($os === 'windows' ? '.exe' : '');
        $host = $sourceRoot.'/target/release/oxinfer'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        foreach (array_values(array_unique([$requested, $host])) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $requested;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function writeInstalledBinary(string $installPath, string $os, string $binaryContents): void
    {
        File::ensureDirectoryExists(dirname($installPath));
        File::put($installPath, $binaryContents);

        if ($os !== 'windows') {
            @chmod($installPath, 0755);
        }
    }

    private function resolveChecksum(string $body, string $asset): string
    {
        foreach (preg_split("/(\r?\n)/", trim($body)) ?: [] as $line) {
            if (! preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/i', trim($line), $matches)) {
                continue;
            }

            if (trim($matches[2]) === $asset) {
                return strtolower($matches[1]);
            }
        }

        throw new RuntimeException(sprintf(
            'Unable to find checksum entry for %s in checksums.txt.',
            $asset,
        ));
    }
}
