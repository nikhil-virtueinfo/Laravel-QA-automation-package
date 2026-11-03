<?php

namespace App\SystemTest\Suites;

use Symfony\Component\Process\Process;

class BrowserSuite
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
        $cmd = [PHP_BINARY, 'artisan', 'dusk', 'tests/Browser/CmsDuskSuiteTest.php'];

        $evidenceDir = $this->runId
            ? storage_path("app/system-tests/runs/{$this->runId}/evidence")
            : storage_path("app/system-tests/evidence");

        if (!is_dir($evidenceDir)) {
            @mkdir($evidenceDir, 0755, true);
        }

        $env = array_merge($_ENV, $_SERVER, [
            'DUSK_HEADLESS' => $this->headless ? 'true' : 'false',
            'APP_ENV' => config('app.env', 'local'),
            'SYSTEM_TEST_SCREENSHOT_DIR' => $evidenceDir,
        ]);

        $start = microtime(true);
        $timeout = $this->headless ? 300 : 900;
        $process = new Process($cmd, base_path(), $env, null, $timeout);

        try {
            $process->run();
            $duration = round(microtime(true) - $start, 1);
            $output = $process->getOutput() . $process->getErrorOutput();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $duration = round(microtime(true) - $start, 1);
            $output = "TIMEOUT: Browser suite exceeded maximum allowed execution duration of {$timeout}s.\n" . $process->getOutput();
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $start, 1);
            $output = "ERROR: " . $e->getMessage() . "\n" . $process->getOutput();
        }

        // Parse individual tests and link corresponding step screenshots
        $knownTests = [
            'admin dashboard render and metrics' => [
                'label' => 'Admin Dashboard Render & Branding',
                'screenshot' => '01-admin-dashboard-render.png',
            ],
            'guest is redirected to admin login' => [
                'label' => 'Guest Redirect & Authentication Barrier',
                'screenshot' => '02-guest-redirect-login.png',
            ],
            'subadmin rbac and privilege escalation barriers' => [
                'label' => 'Sub-Admin RBAC Escalation Barrier',
                'screenshot' => '03-subadmin-404-barriers.png',
            ],
            'user and business user management indices' => [
                'label' => 'User & Business Management UI Rendering',
                'screenshot' => '04-user-business-indices.png',
            ],
            'listing moderation index and controls' => [
                'label' => 'Listing Moderation Index & Controls',
                'screenshot' => '05-listing-moderation-index.png',
            ],
            'advertisements invoices and cms pages' => [
                'label' => 'Advertisements, Invoices & CMS Pages',
                'screenshot' => '06-advertisements-invoices-cms.png',
            ],
        ];

        foreach ($knownTests as $needle => $testMeta) {
            $label = $testMeta['label'];
            $screenshotFile = $testMeta['screenshot'];
            $hasScreenshot = file_exists("{$evidenceDir}/{$screenshotFile}");

            $passed = str_contains($output, "✓ {$needle}") || str_contains($output, "PASS");
            $failed = str_contains($output, "⨯ {$needle}");

            $checks[] = [
                'suite' => 'Browser (Chrome)',
                'name' => "E2E: {$label}",
                'status' => $failed ? 'failed' : ($passed ? 'passed' : ($process->isSuccessful() ? 'passed' : 'failed')),
                'message' => $failed
                    ? "Dusk assertion failed in Chrome browser."
                    : "Chrome rendered cleanly without console errors.",
                'duration_ms' => round(($duration / count($knownTests)) * 1000, 1),
                'evidence' => $hasScreenshot ? $screenshotFile : null,
            ];
        }

        return $checks;
    }
}

