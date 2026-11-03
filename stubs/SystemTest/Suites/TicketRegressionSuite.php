<?php

namespace App\SystemTest\Suites;

use Symfony\Component\Process\Process;

/**
 * Ticket Regression Suite
 *
 * Auto-discovers all Jira ticket-specific Dusk test files from tests/Browser/
 * (e.g. LSBA2615Test.php, LSBA2616Test.php) and runs each one individually
 * as part of the system-test full run. This ensures every merged ticket fix
 * is continuously regression-tested on every system-test execution.
 *
 * Discovery Pattern: tests/Browser/{TICKETKEY}Test.php
 * Excluded:         CmsDuskSuiteTest.php (handled by BrowserSuite)
 *                   ExampleTest.php
 *                   *SubadminDuskTest.php (sub-test variant, grouped under parent)
 */
class TicketRegressionSuite
{
    protected ?string $runId;
    protected bool $headless;

    /** Files that should be skipped (handled by BrowserSuite or not ticket-specific) */
    protected array $excludeFiles = [
        'CmsDuskSuiteTest.php',
        'ExampleTest.php',
    ];

    public function __construct(?string $runId = null, bool $headless = true)
    {
        $this->runId    = $runId;
        $this->headless = $headless;
    }

    public function run(): array
    {
        $checks     = [];
        $ticketFiles = $this->discoverTicketTestFiles();

        if (empty($ticketFiles)) {
            $checks[] = [
                'suite'       => 'Ticket Regression',
                'name'        => 'No Ticket Test Files Discovered',
                'status'      => 'warnings',
                'message'     => 'No LSBA/ticket-specific Dusk test files found in tests/Browser/. Create one to enable ticket regression.',
                'duration_ms' => 0,
            ];
            return $checks;
        }

        $evidenceBase = $this->runId
            ? storage_path("app/system-tests/runs/{$this->runId}/evidence")
            : storage_path('app/system-tests/evidence');

        foreach ($ticketFiles as $ticketKey => $filePath) {
            $checks = array_merge(
                $checks,
                $this->runTicketFile($ticketKey, $filePath, $evidenceBase)
            );
        }

        return $checks;
    }

    /**
     * Discover all ticket-specific test files.
     * Returns [ 'LSBA-2615' => 'tests/Browser/LSBA2615Test.php', ... ]
     */
    protected function discoverTicketTestFiles(): array
    {
        $browserDir = base_path('tests/Browser');
        $pattern    = $browserDir . '/*.php';
        $allFiles   = glob($pattern) ?: [];

        $ticketFiles = [];

        foreach ($allFiles as $absolutePath) {
            $filename = basename($absolutePath);

            // Skip excluded files
            if (in_array($filename, $this->excludeFiles)) {
                continue;
            }

            // Skip sub-test variants (e.g. LSBA2615SubadminDuskTest.php)
            // They are already triggered by the parent file run
            if (preg_match('/Subadmin|SubAdmin|sub_admin/i', $filename)) {
                continue;
            }

            // Must match ticket pattern: e.g. LSBA2615Test.php or PG399Test.php
            if (!preg_match('/^([A-Z]+)(\d+)Test\.php$/i', $filename, $m)) {
                continue;
            }

            // Format ticket key: LSBA2615 → LSBA-2615
            $ticketKey = strtoupper($m[1]) . '-' . $m[2];
            $relativePath = 'tests/Browser/' . $filename;

            $ticketFiles[$ticketKey] = $relativePath;
        }

        // Sort by ticket number ascending
        ksort($ticketFiles);

        return $ticketFiles;
    }

    /**
     * Run a single ticket's Dusk test file and return structured checks.
     */
    protected function runTicketFile(string $ticketKey, string $relativeFile, string $evidenceBase): array
    {
        $checks = [];
        $clean  = str_replace('-', '', $ticketKey); // LSBA2615

        // Per-ticket evidence dir inside the system-test run evidence folder
        $ticketEvidenceDir = $evidenceBase . '/' . strtolower($clean);
        if (!is_dir($ticketEvidenceDir)) {
            @mkdir($ticketEvidenceDir, 0755, true);
        }

        $env = array_merge($_ENV, $_SERVER, [
            'DUSK_HEADLESS'              => $this->headless ? 'true' : 'false',
            'APP_ENV'                    => config('app.env', 'local'),
            'SYSTEM_TEST_SCREENSHOT_DIR' => $ticketEvidenceDir,
        ]);

        $cmd   = [PHP_BINARY, 'artisan', 'dusk', $relativeFile];
        $start = microtime(true);
        $timeout = $this->headless ? 300 : 900;

        $process = new Process($cmd, base_path(), $env, null, $timeout);

        try {
            $process->run();
            $duration = round(microtime(true) - $start, 1);
            $output   = $process->getOutput() . $process->getErrorOutput();
            $passed   = $process->isSuccessful();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $duration = round(microtime(true) - $start, 1);
            $output   = "TIMEOUT: Ticket regression test exceeded maximum duration of {$timeout}s.\n" . $process->getOutput();
            $passed   = false;
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $start, 1);
            $output   = "ERROR: " . $e->getMessage() . "\n" . $process->getOutput();
            $passed   = false;
        }

        // Parse individual test methods from Dusk output
        $parsedTests = $this->parseTestMethodsFromOutput($output);

        if (!empty($parsedTests)) {
            // Emit one check per parsed test method for fine-grained reporting
            foreach ($parsedTests as $test) {
                $screenshotFile = $this->findScreenshotForStep($ticketEvidenceDir, $test['index']);

                $checks[] = [
                    'suite'       => "Ticket Regression [{$ticketKey}]",
                    'name'        => "{$ticketKey}: " . $test['name'],
                    'status'      => $test['status'],
                    'message'     => $test['status'] === 'passed'
                        ? "Ticket scenario verified in Chrome (exit 0)."
                        : "Ticket scenario FAILED — Defect regression detected!",
                    'duration_ms' => round(($duration / count($parsedTests)) * 1000, 1),
                    'evidence'    => $screenshotFile ? (strtolower($clean) . '/' . $screenshotFile) : null,
                ];
            }
        } else {
            // Fallback: emit one aggregate check for the whole ticket
            $evidence = $this->findFirstScreenshot($ticketEvidenceDir, $clean);

            $checks[] = [
                'suite'       => "Ticket Regression [{$ticketKey}]",
                'name'        => "{$ticketKey}: Full E2E Regression Suite",
                'status'      => $passed ? 'passed' : 'failed',
                'message'     => $passed
                    ? "All {$ticketKey} browser scenarios passed regression. Zero regressions."
                    : "Regression failure in {$ticketKey}! Check evidence screenshots.",
                'duration_ms' => round($duration * 1000, 1),
                'evidence'    => $evidence ? (strtolower($clean) . '/' . $evidence) : null,
            ];
        }

        return $checks;
    }

    /**
     * Parse individual test method results from Dusk/PHPUnit CLI output.
     * Returns array of [ 'name' => '...', 'status' => 'passed|failed', 'index' => N ]
     */
    protected function parseTestMethodsFromOutput(string $output): array
    {
        $tests = [];
        $lines = explode("\n", $output);
        $index = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Match PHPUnit/Pest PASS lines: ✓ description  1.23s
            if (preg_match('/^[✓✔]\s+(.+?)(?:\s+\d+\.\d+s)?$/u', $trimmed, $m)) {
                $index++;
                $rawName = trim($m[1]);
                $tests[] = [
                    'name'   => $this->humanizeName($rawName),
                    'status' => 'passed',
                    'index'  => $index,
                ];
            }
            // Match FAIL lines: ⨯ description
            elseif (preg_match('/^[⨯✗×]\s+(.+?)(?:\s+\d+\.\d+s)?$/u', $trimmed, $m)) {
                $index++;
                $rawName = trim($m[1]);
                $tests[] = [
                    'name'   => $this->humanizeName($rawName),
                    'status' => 'failed',
                    'index'  => $index,
                ];
            }
        }

        return $tests;
    }

    /**
     * Convert snake_case or camelCase test name to human-readable label.
     */
    protected function humanizeName(string $name): string
    {
        // Remove "test_" prefix typical of PHPUnit method names
        $name = preg_replace('/^test[_\s]+/i', '', $name);
        // Replace underscores with spaces
        $name = str_replace('_', ' ', $name);
        return ucfirst(trim($name));
    }

    /**
     * Find the Nth screenshot in the evidence directory (sorted).
     */
    protected function findScreenshotForStep(string $dir, int $n): ?string
    {
        $files = glob("{$dir}/*.png") ?: [];
        sort($files);
        $file = $files[$n - 1] ?? null;
        return $file ? basename($file) : null;
    }

    /**
     * Find the first screenshot in the evidence directory.
     */
    protected function findFirstScreenshot(string $dir, string $clean): ?string
    {
        $files = glob("{$dir}/*.png") ?: [];
        sort($files);
        return $files ? basename($files[0]) : null;
    }
}

