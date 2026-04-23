<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

final class OxinferBinaryResolver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(array $config, string $workingDirectory): string
    {
        $configured = trim((string) ($config['binary'] ?? 'oxinfer'));

        if ($configured === '') {
            throw new RuntimeException(
                'Unable to find the oxinfer binary: oxcribe.oxinfer.binary is empty. '.
                'Set OXINFER_BINARY or configure oxcribe.oxinfer.binary.'
            );
        }

        if ($this->looksLikePath($configured)) {
            foreach ($this->pathCandidates($configured, $workingDirectory) as $candidate) {
                if ($this->isRunnableFile($candidate)) {
                    return $candidate;
                }
            }

            throw new RuntimeException(sprintf(
                'Unable to find the oxinfer binary at "%s". Set OXINFER_BINARY or oxcribe.oxinfer.binary to an executable path.',
                $this->normalizePath($configured, $workingDirectory),
            ));
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

        throw new RuntimeException(sprintf(
            'Unable to find the oxinfer binary "%s". Add it to PATH, or set OXINFER_BINARY / oxcribe.oxinfer.binary to an executable path.',
            $configured,
        ));
    }

    public function suggestedInstallPath(array $config, string $workingDirectory, string $osFamily = PHP_OS_FAMILY): string
    {
        $path = trim((string) ($config['install_path'] ?? 'bin/oxinfer'));
        $resolved = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : rtrim($workingDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);

        if ($osFamily === 'Windows' && ! str_ends_with(strtolower($resolved), '.exe')) {
            return $resolved.'.exe';
        }

        return $resolved;
    }

    private function looksLikePath(string $binary): bool
    {
        return str_contains($binary, DIRECTORY_SEPARATOR)
            || str_contains($binary, '/')
            || str_starts_with($binary, '.')
            || str_starts_with($binary, '~');
    }

    private function normalizePath(string $binary, string $workingDirectory): string
    {
        if (str_starts_with($binary, '~/')) {
            $home = getenv('HOME') ?: '';

            return ($home !== '' ? rtrim($home, DIRECTORY_SEPARATOR) : '').DIRECTORY_SEPARATOR.ltrim(substr($binary, 2), DIRECTORY_SEPARATOR);
        }

        if (
            str_starts_with($binary, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $binary) === 1
        ) {
            return $binary;
        }

        return rtrim($workingDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($binary, DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<string>
     */
    private function pathCandidates(string $binary, string $workingDirectory): array
    {
        $candidate = $this->normalizePath($binary, $workingDirectory);
        $candidates = [$candidate];

        if (PHP_OS_FAMILY === 'Windows' && pathinfo($candidate, PATHINFO_EXTENSION) === '') {
            foreach (['.exe', '.cmd', '.bat'] as $suffix) {
                $candidates[] = $candidate.$suffix;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function isRunnableFile(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return is_executable($path);
        }

        if (is_executable($path)) {
            return true;
        }

        return in_array(strtolower('.'.pathinfo($path, PATHINFO_EXTENSION)), ['.exe', '.cmd', '.bat'], true);
    }
}
