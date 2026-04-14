<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Oxhq\Oxcribe\Bridge\AnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessOxinferClient;
use Oxhq\Oxcribe\Console\AnalyzeCommand;
use Oxhq\Oxcribe\Console\DoctorCommand;
use Oxhq\Oxcribe\Console\ExportOpenApiCommand;
use Oxhq\Oxcribe\Console\InstallBinaryCommand;
use Oxhq\Oxcribe\Console\PublishCommand;
use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Docs\DocsPayloadFactory;
use Oxhq\Oxcribe\Http\Middleware\VisibilityMarkerMiddleware;
use Oxhq\Oxcribe\Merge\OperationGraphMerger;
use Oxhq\Oxcribe\OpenApi\OpenApiDocumentFactory;
use Oxhq\Oxcribe\Overrides\OverrideApplier;
use Oxhq\Oxcribe\Overrides\OverrideLoader;
use Oxhq\Oxcribe\Runtime\LaravelRuntimeSnapshotFactory;
use Oxhq\Oxcribe\Support\FormRequestFieldResolver;
use Oxhq\Oxcribe\Support\InstalledPackageDetector;
use Oxhq\Oxcribe\Support\ManifestFactory;
use Oxhq\Oxcribe\Support\RequestSerializer;
use Oxhq\Oxcribe\Support\RouteIdFactory;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;

final class OxcribeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oxcribe.php', 'oxcribe');

        $this->app->singleton(RouteIdFactory::class);
        $this->app->singleton(ManifestFactory::class);
        $this->app->singleton(RouteSnapshotExtractor::class);
        $this->app->singleton(RequestSerializer::class);
        $this->app->singleton(FormRequestFieldResolver::class);
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
        $this->app->singleton(OperationGraphMerger::class, function ($app): OperationGraphMerger {
            return new OperationGraphMerger(
                formRequestFieldResolver: $app->make(FormRequestFieldResolver::class),
            );
        });
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

        $router = $this->app->make(Router::class);
        foreach ([
            'oxcribe.publish',
            'ox.publish',
            'oxcribe.private',
            'ox.private',
        ] as $alias) {
            $router->aliasMiddleware($alias, VisibilityMarkerMiddleware::class);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'oxcribe');
        $this->loadRoutesFrom(__DIR__.'/../routes/oxcribe.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyzeCommand::class,
                DoctorCommand::class,
                ExportOpenApiCommand::class,
                InstallBinaryCommand::class,
                PublishCommand::class,
            ]);
        }
    }
}

if (! class_exists(\Garaekz\Oxcribe\OxcribeServiceProvider::class, false)) {
    class_alias(OxcribeServiceProvider::class, \Garaekz\Oxcribe\OxcribeServiceProvider::class);
}
