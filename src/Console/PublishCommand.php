<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe\Console;

use Garaekz\Oxcribe\OxcribeManager;
use Garaekz\Oxcribe\Support\PackageVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class PublishCommand extends Command
{
    protected $signature = 'oxcribe:publish {--project-root=} {--publish-version=} {--override-file=*}';

    protected $description = 'Publish the current OpenAPI document and docs payload to oxcloud';

    public function handle(OxcribeManager $manager): int
    {
        $config = (array) config('oxcribe.publish', []);
        $baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $token = trim((string) ($config['token'] ?? ''));

        if ($baseUrl === '') {
            $this->error('Missing OXCLOUD_BASE_URL / oxcribe.publish.base_url.');

            return self::FAILURE;
        }

        if ($token === '') {
            $this->error('Missing OXCLOUD_TOKEN / oxcribe.publish.token.');

            return self::FAILURE;
        }

        $overrideFiles = array_values(array_filter(
            (array) $this->option('override-file'),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        $projectRoot = $this->option('project-root');
        $version = $this->resolveVersion($this->option('publish-version'), $config);
        $openApi = $manager->exportOpenApi($projectRoot, $overrideFiles);
        $docsPayload = $manager->docsPayload($projectRoot, $overrideFiles);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) ($config['timeout'] ?? 30))
            ->post($baseUrl.'/api/publish/v1', [
                'contractVersion' => 'oxcloud.publish.v1',
                'version' => $version,
                'openapi' => $openApi,
                'docsPayload' => $docsPayload,
                'source' => [
                    'appName' => (string) config('app.name', 'Laravel API'),
                    'appUrl' => (string) config('app.url', ''),
                    'framework' => 'laravel',
                    'packageVersion' => PackageVersion::label(),
                ],
            ]);

        if ($response->failed()) {
            $this->error(sprintf(
                'oxcloud publish failed with status %d: %s',
                $response->status(),
                $response->body() !== '' ? $response->body() : 'empty response',
            ));

            return self::FAILURE;
        }

        $body = $response->json();
        $versionUrl = is_array($body) ? (string) ($body['versionUrl'] ?? '') : '';
        $projectUrl = is_array($body) ? (string) ($body['projectUrl'] ?? '') : '';

        $this->info(sprintf('Published %s to oxcloud.', $version));

        if ($versionUrl !== '') {
            $this->line(sprintf('Version URL: %s', $versionUrl));
        }

        if ($projectUrl !== '') {
            $this->line(sprintf('Project URL: %s', $projectUrl));
        }

        return self::SUCCESS;
    }

    /**
     * @param  mixed  $commandVersion
     * @param  array<string, mixed>  $config
     */
    private function resolveVersion(mixed $commandVersion, array $config): string
    {
        if (is_string($commandVersion) && trim($commandVersion) !== '') {
            return trim($commandVersion);
        }

        $configuredVersion = trim((string) ($config['default_version'] ?? ''));
        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $appVersion = trim((string) config('app.version', ''));
        if ($appVersion !== '') {
            return $appVersion;
        }

        return 'dev';
    }
}
