<?php

namespace SystemTestFramework\Commands;

use App\SystemTest\SystemTestRunner;
use Illuminate\Console\Command;

class SystemTestRunCommand extends Command
{
    protected $signature = 'system-test:run 
                            {--no-browser : Skip browser Dusk suite for faster execution}
                            {--headed : Run Dusk browser in visible headed mode}
                            {--keep-data : Keep synthetic test world data after run}
                            {--run-id= : Attach to an existing run ID}';

    protected $description = 'Execute autonomous enterprise system test suite (Deploy, Web Matrix, API, Browser & Safety)';

    public function handle(): int
    {
        $this->line("");
        $this->info("================================================================================");
        $this->info("🚀 LA SUBASTA AUTONOMOUS SYSTEM TEST ENGINE");
        $this->info("================================================================================");
        $this->line("Environment: " . config('app.env') . " | PHP: " . PHP_VERSION);

        $noBrowser = $this->option('no-browser');
        $headed = $this->option('headed');
        $keepData = $this->option('keep-data');
        $runId = $this->option('run-id');

        $runner = new SystemTestRunner();
        $runner->onProgress(function ($suite, $check) {
            $this->line("⏳ [{$suite}] -> {$check}");
        });

        $this->info("\nStarting execution...");
        $startTime = microtime(true);

        $run = $runner->run([
            'trigger' => $runId ? 'web' : 'cli',
            'run_id' => $runId,
            'include_browser' => !$noBrowser,
            'headless' => !$headed,
            'keep_data' => $keepData,
        ]);

        $duration = round(microtime(true) - $startTime, 1);

        $this->line("\n================================================================================");
        $this->info("🏁 SYSTEM TEST EXECUTION SUMMARY — Run ID: [{$run['id']}]");
        $this->info("================================================================================");

        $counts = $run['counts'];
        $this->table(
            ['Total Checks', 'Passed ✅', 'Failed ❌', 'Warnings ⚠️', 'Duration'],
            [[
                $counts['total'],
                $counts['passed'],
                $counts['failed'],
                $counts['warnings'],
                "{$duration}s",
            ]]
        );

        if (!empty($run['defects'])) {
            $this->error("\n❌ Discovered Defects (" . count($run['defects']) . "):");
            foreach ($run['defects'] as $i => $d) {
                $this->line(" " . ($i + 1) . ". [{$d['suite']}] {$d['name']}");
                $this->comment("    Reason: {$d['error']}");
            }
        } else {
            $this->info("\n🎉 100% HEALTHY: All checks passed with zero regressions!");
        }

        $url = url("/admin/system-tests");
        $this->line("\n📊 Live Admin Panel: {$url}");

        return ($counts['failed'] === 0) ? self::SUCCESS : self::FAILURE;
    }
}

