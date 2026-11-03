<?php

namespace SystemTestFramework\Suites;

use Symfony\Component\Process\Process;

/**
 * Deep Contract Suite
 *
 * Discovers and executes all micro-UI contract & computed CSS Dusk tests
 * under tests/Browser/DeepQA/.
 * Verifies exact typography, computed colors, validation error messages,
 * table headers, and form boundary edge-cases.
 */
class DeepContractSuite
{
    protected ?string $runId;
    protected bool $headless;

    public function __construct(?string $runId = null, bool $headless = true)
    {
        $this->runId = $runId;
        $this->headless = $headless;
    }

    public function run(): array
    {
        $checks = [];
        $deepQaDir = base_path('tests/Browser/DeepQA');

        if (!is_dir($deepQaDir)) {
            $checks[] = [
                'suite' => 'Deep Contract UI',
                'name' => 'Deep QA Test Suite Discovered',
                'status' => 'warnings',
                'message' => 'No Deep QA tests found in tests/Browser/DeepQA. Run "php artisan system-test:generate-deep-tests" to generate.',
                'duration_ms' => 0,
            ];
            return $checks;
        }

        $testFiles = $this->findTestFilesRecursively($deepQaDir);
        if (empty($testFiles)) {
            $checks[] = [
                'suite' => 'Deep Contract UI',
                'name' => 'Deep QA Test Suite Ready',
                'status' => 'warnings',
                'message' => 'Zero Deep QA test files present in tests/Browser/DeepQA/.',
                'duration_ms' => 0,
            ];
            return $checks;
        }

        $evidenceDir = $this->runId
            ? storage_path("app/system-tests/runs/{$this->runId}/evidence")
            : storage_path("app/system-tests/evidence");

        if (!is_dir($evidenceDir)) {
            @mkdir($evidenceDir, 0755, true);
        }

        foreach ($testFiles as $filePath) {
            $testName = basename($filePath, '.php');
            $checks = array_merge($checks, $this->runSingleDeepTest($testName, $filePath, $evidenceDir));
        }

        return $checks;
    }

    protected function runSingleDeepTest(string $testName, string $filePath, string $evidenceDir): array
    {
        $cmd = [PHP_BINARY, 'artisan', 'dusk', $filePath];

        $env = array_merge($_ENV, $_SERVER, [
            'DUSK_HEADLESS' => $this->headless ? 'true' : 'false',
            'SYSTEM_TEST_SCREENSHOT_DIR' => $evidenceDir,
        ]);

        $start = microtime(true);
        $process = new Process($cmd, base_path(), $env, null, 180);

        try {
            $process->run();
            $duration = round(microtime(true) - $start, 2);
            $output = $process->getOutput() . $process->getErrorOutput();
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $start, 2);
            $output = "ERROR: " . $e->getMessage();
        }

        $isPassed = $process->isSuccessful() && (str_contains($output, 'OK (') || str_contains($output, 'PASS'));
        $durationMs = round($duration * 1000, 1);

        $screenshotName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $testName)) . '-failure.png';
        $hasScreenshot = file_exists("{$evidenceDir}/{$screenshotName}");

        $message = $isPassed
            ? "Exact UI text, computed CSS styles, and validation contracts 100% verified."
            : $this->extractFailureMessage($output);

        return [
            [
                'suite' => 'Deep Contract UI',
                'name' => "Contract: {$testName}",
                'status' => $isPassed ? 'passed' : 'failed',
                'message' => $message,
                'duration_ms' => $durationMs,
                'evidence' => $hasScreenshot ? $screenshotName : null,
            ]
        ];
    }

    protected function extractFailureMessage(string $output): string
    {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (str_contains($line, 'Failed asserting that') || str_contains($line, 'Strict text contract violated') || str_contains($line, 'Computed style')) {
                return trim($line);
            }
        }
        return "Contract assertion failed during Dusk execution.";
    }

    protected function findTestFilesRecursively(string $dir): array
    {
        $files = [];
        $items = glob("{$dir}/*") ?: [];

        foreach ($items as $item) {
            if (is_dir($item)) {
                $files = array_merge($files, $this->findTestFilesRecursively($item));
            } elseif (is_file($item) && str_ends_with($item, 'Test.php')) {
                $files[] = $item;
            }
        }

        return $files;
    }
}

