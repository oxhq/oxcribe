<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Oxhq\Oxcribe\OxcribeManager;
use Oxhq\Oxcribe\Support\PackageVersion;
use Oxhq\Oxcribe\Support\PublishVersionResolver;

final class PublishCommand extends Command
{
    protected $signature = 'oxcribe:publish {--project-root=} {--publish-version=} {--override-file=*}';

    protected $description = 'Publish the current OpenAPI document and docs payload to Oxcribe Cloud';

    public function handle(OxcribeManager $manager): int
    {
        $config = (array) config('oxcribe.publish', []);
        $baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $token = trim((string) ($config['token'] ?? ''));
        $versionResolver = new PublishVersionResolver;

        if ($baseUrl === '') {
            $this->error('Missing OXCLOUD_BASE_URL / oxcribe.publish.base_url.');
            $this->line('Run `php artisan oxcribe:doctor` to inspect local and cloud publish readiness.');

            return self::FAILURE;
        }

        if ($token === '') {
            $this->error('Missing OXCLOUD_TOKEN / oxcribe.publish.token.');
            $this->line('Run `php artisan oxcribe:doctor` to inspect local and cloud publish readiness.');

            return self::FAILURE;
        }

        $overrideFiles = array_values(array_filter(
            (array) $this->option('override-file'),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        $projectRoot = $this->option('project-root');
        $version = $versionResolver->resolve($this->option('publish-version'), $config);
        $openApi = $manager->exportOpenApi($projectRoot, $overrideFiles);
        $docsPayload = $manager->docsPayload($projectRoot, $overrideFiles);
        $payload = [
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
        ];
        $request = Http::acceptJson()
            ->withToken($token)
            ->timeout((int) ($config['timeout'] ?? 30));

        $response = $this->postPayload($request, $baseUrl, $payload);

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

        $this->info(sprintf('Published %s to Oxcribe Cloud.', $version));

        if ($versionUrl !== '') {
            $this->line(sprintf('Version URL: %s', $versionUrl));
            $this->line(sprintf('Explorer URL: %s/explorer', rtrim($versionUrl, '/')));
            $this->line(sprintf('Changelog URL: %s/changelog', rtrim($versionUrl, '/')));
        }

        if ($projectUrl !== '') {
            $this->line(sprintf('Project URL: %s', $projectUrl));
        }

        $this->newLine();
        $this->line('Next: open the version URL and review docs, explorer, and changelog.');
        $this->line('Then: decide whether to keep it public, require review, or share it by link/domain.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postPayload(PendingRequest $request, string $baseUrl, array $payload): Response
    {
        if (! function_exists('gzencode')) {
            return $request
                ->asJson()
                ->post($baseUrl.'/api/publish/v1', $payload);
        }

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $request
                ->asJson()
                ->post($baseUrl.'/api/publish/v1', $payload);
        }

        $compressed = gzencode($json, 9);

        if (! is_string($compressed) || $compressed === '') {
            return $request
                ->asJson()
                ->post($baseUrl.'/api/publish/v1', $payload);
        }

        return $request
            ->withHeaders([
                'Content-Encoding' => 'gzip',
                'Content-Type' => 'application/json',
            ])
            ->withBody($compressed, 'application/json')
            ->post($baseUrl.'/api/publish/v1');
    }
}
