<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use JsonException;
use Oxhq\Oxcribe\OxcribeManager;

final class AnalyzeCommand extends Command
{
    protected $signature = 'oxcribe:analyze {--project-root=} {--write=} {--pretty}';

    protected $description = 'Boot Laravel, capture the runtime route graph, and enrich it with oxinfer';

    public function handle(OxcribeManager $manager): int
    {
        $response = $manager->analyze($this->option('project-root'));

        try {
            $json = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($this->option('pretty') ? JSON_PRETTY_PRINT : 0));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->writeOrOutput($json);
    }

    private function writeOrOutput(string $json): int
    {
        $target = $this->option('write');

        if (is_string($target) && $target !== '') {
            file_put_contents($target, $json.PHP_EOL);
            $this->info(sprintf('AnalysisResponse written to %s', $target));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
