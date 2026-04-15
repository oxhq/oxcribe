<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Oxhq\Oxcribe\Support\OxinferBinaryResolver;
use Oxhq\Oxcribe\Support\PackageVersion;
use Oxhq\Oxcribe\Support\PublishVersionResolver;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DoctorCommand extends Command
{
    protected $signature = 'oxcribe:doctor
        {--project-root= : Override the Laravel app root to inspect}
        {--skip-cloud : Skip Oxcribe Cloud publish checks}';

    protected $description = 'Run a first-publish preflight for oxcribe, oxinfer, and Oxcribe Cloud config';

    public function handle(): int
    {
        $oxinferConfig = (array) config('oxcribe.oxinfer', []);
        $publishConfig = (array) config('oxcribe.publish', []);
        $projectRoot = $this->resolveProjectRoot($oxinferConfig);
        $workingDirectory = (string) ($oxinferConfig['working_directory'] ?? $projectRoot);
        $binaryResolver = new OxinferBinaryResolver;
        $versionResolver = new PublishVersionResolver;

        $blocking = false;
        $cloudBlocking = false;

        $this->components->twoColumnDetail('Package', PackageVersion::label());
        $this->newLine();

        if (is_dir($projectRoot)) {
            $this->report('PASS', 'Project root', $projectRoot);
        } else {
            $blocking = true;
            $this->report('FAIL', 'Project root', sprintf(
                'Directory "%s" does not exist. Re-run with --project-root=/absolute/path.',
                $projectRoot,
            ));
        }

        $composerJson = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';
        if (is_file($composerJson)) {
            $this->report('PASS', 'composer.json', $composerJson);
        } else {
            $blocking = true;
            $this->report('FAIL', 'composer.json', 'Missing composer.json in the resolved project root.');
        }

        $docsEnabled = (bool) config('oxcribe.docs.enabled', false);
        $docsRoute = (string) config('oxcribe.docs.route', 'oxcribe/docs');
        $docsMessage = $docsEnabled
            ? sprintf('Local viewer enabled at /%s', ltrim($docsRoute, '/'))
            : 'Local viewer is disabled. Set OXCRIBE_DOCS_ENABLED=true if you want package-owned local docs.';
        $this->report($docsEnabled ? 'PASS' : 'WARN', 'Local docs', $docsMessage);

        try {
            $binary = $binaryResolver->resolve($oxinferConfig, $workingDirectory);
            $this->report('PASS', 'Oxinfer binary', $binary);

            $version = $this->resolveBinaryVersion($binary, $workingDirectory);
            if ($version !== null) {
                $this->report('PASS', 'Oxinfer version', $version);
            } else {
                $this->report('WARN', 'Oxinfer version', 'Binary is executable, but --version did not return cleanly.');
            }
        } catch (RuntimeException $exception) {
            $blocking = true;
            $suggestedPath = $binaryResolver->suggestedInstallPath($oxinferConfig, $workingDirectory);
            $this->report('FAIL', 'Oxinfer binary', $exception->getMessage());
            $sourceRoot = trim((string) ($oxinferConfig['source_root'] ?? ''));
            $installCommand = sprintf('php artisan oxcribe:install-binary %s', PackageVersion::TAG);
            if ($sourceRoot !== '') {
                $installCommand .= sprintf(' --source-root=%s', $sourceRoot);
            }
            $this->line(sprintf('      Next: run `%s` or point OXINFER_BINARY to an executable path.', $installCommand));
            $this->line(sprintf('      Hint: the default app-local install path is %s', $suggestedPath));
        }

        if (! $this->option('skip-cloud')) {
            $baseUrl = rtrim(trim((string) ($publishConfig['base_url'] ?? '')), '/');
            $token = trim((string) ($publishConfig['token'] ?? ''));

            if ($baseUrl === '') {
                $cloudBlocking = true;
                $this->report('FAIL', 'Oxcribe Cloud URL', 'Missing OXCLOUD_BASE_URL / oxcribe.publish.base_url.');
            } elseif (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
                $cloudBlocking = true;
                $this->report('FAIL', 'Oxcribe Cloud URL', sprintf('"%s" is not a valid URL.', $baseUrl));
            } else {
                $this->report('PASS', 'Oxcribe Cloud URL', $baseUrl);
            }

            if ($token === '') {
                $cloudBlocking = true;
                $this->report('FAIL', 'Publish token', 'Missing OXCLOUD_TOKEN / oxcribe.publish.token.');
            } else {
                $this->report('PASS', 'Publish token', sprintf('Configured (%s)', $this->maskSecret($token)));
            }

            $this->report('PASS', 'Resolved publish version', $versionResolver->resolve(null, $publishConfig));
        } else {
            $this->report('WARN', 'Oxcribe Cloud', 'Skipped with --skip-cloud. Only local analyze/docs readiness was checked.');
        }

        $this->newLine();

        if (! $blocking && ($this->option('skip-cloud') || ! $cloudBlocking)) {
            $this->info('Oxcribe is ready for the next step.');
            $this->line($this->option('skip-cloud')
                ? 'Next: run `php artisan oxcribe:analyze` or `php artisan oxcribe:export-openapi`.'
                : 'Next: run `php artisan oxcribe:publish --publish-version=<version>` and open the URLs it prints.');

            return self::SUCCESS;
        }

        $this->error('Oxcribe preflight found blocking issues.');
        $this->line('Next: fix the failed checks above, then rerun `php artisan oxcribe:doctor`.');
        $this->line('Docs: installation.md and troubleshooting.md cover the common first-publish failures.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $oxinferConfig
     */
    private function resolveProjectRoot(array $oxinferConfig): string
    {
        $fromOption = trim((string) $this->option('project-root'));
        if ($fromOption !== '') {
            return $fromOption;
        }

        $fromConfig = trim((string) ($oxinferConfig['working_directory'] ?? ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $fromDocs = trim((string) config('oxcribe.docs.project_root', ''));
        if ($fromDocs !== '') {
            return $fromDocs;
        }

        return base_path();
    }

    private function resolveBinaryVersion(string $binary, string $workingDirectory): ?string
    {
        $process = new Process([$binary, '--version'], $workingDirectory, null, null, 5);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output !== '' ? $output : null;
    }

    private function maskSecret(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('*', max(strlen($value) - 8, 4)).substr($value, -4);
    }

    private function report(string $status, string $label, string $message): void
    {
        $this->line(sprintf('%-5s %s: %s', $status, $label, $message));
    }
}
