<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use JsonException;
use Oxhq\Oxcribe\OxcribeManager;

final class ExportOpenApiCommand extends Command
{
    protected $signature = 'oxcribe:export-openapi {--project-root=} {--write=} {--pretty} {--override-file=*}';

    protected $description = 'Export an OpenAPI document assembled from Laravel runtime data, oxinfer analysis, and override files';

    public function handle(OxcribeManager $manager): int
    {
        $overrideFiles = array_values(array_filter((array) $this->option('override-file'), static fn (mixed $value): bool => is_string($value) && $value !== ''));
        $document = $manager->exportOpenApi($this->option('project-root'), $overrideFiles);

        try {
            $json = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($this->option('pretty') ? JSON_PRETTY_PRINT : 0));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $target = $this->option('write');
        if (is_string($target) && $target !== '') {
            file_put_contents($target, $json.PHP_EOL);
            $this->info(sprintf('OpenAPI document written to %s', $target));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
