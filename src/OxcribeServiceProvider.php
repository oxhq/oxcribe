<?php

declare(strict_types=1);

namespace Garaekz\Oxcribe;

use Garaekz\Oxcribe\Bridge\AnalysisRequestFactory;
use Garaekz\Oxcribe\Bridge\ProcessOxinferClient;
use Garaekz\Oxcribe\Console\AnalyzeCommand;
use Garaekz\Oxcribe\Console\ExportOpenApiCommand;
use Garaekz\Oxcribe\Console\PublishCommand;
use Garaekz\Oxcribe\Contracts\OxinferClient;
use Garaekz\Oxcribe\Contracts\PackageInventoryDetector;
use Garaekz\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Garaekz\Oxcribe\Docs\DocsPayloadFactory;
use Garaekz\Oxcribe\Merge\OperationGraphMerger;
use Garaekz\Oxcribe\Overrides\OverrideApplier;
use Garaekz\Oxcribe\Overrides\OverrideLoader;
use Garaekz\Oxcribe\OpenApi\OpenApiDocumentFactory;
use Garaekz\Oxcribe\Runtime\LaravelRuntimeSnapshotFactory;
use Garaekz\Oxcribe\Support\InstalledPackageDetector;
use Garaekz\Oxcribe\Support\ManifestFactory;
use Garaekz\Oxcribe\Support\RequestSerializer;
use Garaekz\Oxcribe\Support\RouteIdFactory;
use Garaekz\Oxcribe\Support\RouteSnapshotExtractor;
use Illuminate\Support\ServiceProvider;

final class OxcribeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oxcribe.php', 'oxcribe');

        $this->app->singleton(RouteIdFactory::class);
        $this->app->singleton(ManifestFactory::class);
        $this->app->singleton(RouteSnapshotExtractor::class);
        $this->app->singleton(RequestSerializer::class);
        $this->app->singleton(PackageInventoryDetector::class, function ($app): PackageInventoryDetector {
            return new InstalledPackageDetector((array) $app['config']->get('oxcribe', []));
        });
        $this->app->singleton(RuntimeSnapshotFactory::class, LaravelRuntimeSnapshotFactory::class);
        $this->app->singleton(AnalysisRequestFactory::class, function ($app): AnalysisRequestFactory {
            return new AnalysisRequestFactory(
                manifestFactory: $app->make(ManifestFactory::class),
                config: (array) $app['config']->get('oxcribe', []),
            );
        });
        $this->app->singleton(OxinferClient::class, function ($app): OxinferClient {
            return new ProcessOxinferClient((array) $app['config']->get('oxcribe.oxinfer', []));
        });
        $this->app->singleton(OperationGraphMerger::class);
        $this->app->singleton(OverrideLoader::class, function ($app): OverrideLoader {
            return new OverrideLoader((array) $app['config']->get('oxcribe', []));
        });
        $this->app->singleton(OverrideApplier::class);
        $this->app->singleton(OpenApiDocumentFactory::class);
        $this->app->singleton(DocsPayloadFactory::class);
        $this->app->singleton(OxcribeManager::class, function ($app): OxcribeManager {
            return new OxcribeManager(
                runtimeSnapshotFactory: $app->make(RuntimeSnapshotFactory::class),
                analysisRequestFactory: $app->make(AnalysisRequestFactory::class),
                oxinferClient: $app->make(OxinferClient::class),
                operationGraphMerger: $app->make(OperationGraphMerger::class),
                overrideLoader: $app->make(OverrideLoader::class),
                overrideApplier: $app->make(OverrideApplier::class),
                openApiDocumentFactory: $app->make(OpenApiDocumentFactory::class),
                docsPayloadFactory: $app->make(DocsPayloadFactory::class),
                config: (array) $app['config']->get('oxcribe', []),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oxcribe.php' => config_path('oxcribe.php'),
        ], 'oxcribe-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'oxcribe');
        $this->loadRoutesFrom(__DIR__.'/../routes/oxcribe.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyzeCommand::class,
                ExportOpenApiCommand::class,
                PublishCommand::class,
            ]);
        }
    }
}
