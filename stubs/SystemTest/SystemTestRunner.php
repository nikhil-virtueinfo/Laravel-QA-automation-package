<?php

namespace App\SystemTest;

use App\SystemTest\Storage\RunRepository;
use App\SystemTest\Suites\ApiMatrixSuite;
use App\SystemTest\Suites\BrowserSuite;
use App\SystemTest\Suites\BusinessFlowsSuite;
use App\SystemTest\Suites\DeploySuite;
use App\SystemTest\Suites\PagesMatrixSuite;
use App\SystemTest\Suites\TicketRegressionSuite;
use App\SystemTest\World\SyntheticWorld;

class SystemTestRunner
{
    protected RunRepository $repository;
    protected SyntheticWorld $world;
    protected ?\Closure $progressCallback = null;

    public function __construct(RunRepository $repository = null, SyntheticWorld $world = null)
    {
        $this->repository = $repository ?? new RunRepository();
        $this->world = $world ?? new SyntheticWorld();
    }

    public function onProgress(\Closure $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    public function run(array $options = []): array
    {
        $trigger = $options['trigger'] ?? 'manual';
        $providedRunId = $options['run_id'] ?? null;

        if ($providedRunId && ($existingRun = $this->repository->getRun($providedRunId))) {
            $run = $existingRun;
            $runId = $providedRunId;
        } else {
            $run = $this->repository->createRun($trigger);
            $runId = $run['id'];
        }

        $startTime = microtime(true);
        $includeBrowser = $options['include_browser'] ?? true;
        $headless = $options['headless'] ?? true;
        $keepData = $options['keep_data'] ?? false;

        $previousRun = $this->repository->listRuns(2)[1] ?? null;

        try {
            $this->notifyProgress($runId, 'Synthetic World', 'Creating ZZ_TEST entities');
            $this->world->setup();

            // 1. Deploy Checks
            $this->notifyProgress($runId, 'Deploy Suite', 'Checking migrations & composer lock');
            $deployChecks = (new DeploySuite())->run();
            foreach ($deployChecks as $check) {
                $this->repository->addCheck($runId, $check);
            }

            // 2. Web Pages Matrix Suite (All routes across all roles)
            $this->notifyProgress($runId, 'Web Matrix', 'Crawling CMS & Web routes across all 5 roles');
            $pageChecks = (new PagesMatrixSuite($this->world))->run();
            foreach ($pageChecks as $check) {
                $this->repository->addCheck($runId, $check);
            }

            // 3. REST API Matrix Suite
            $this->notifyProgress($runId, 'REST API', 'Validating API endpoints and secret leaks');
            $apiChecks = (new ApiMatrixSuite($this->world))->run();
            foreach ($apiChecks as $check) {
                $this->repository->addCheck($runId, $check);
            }

            // 4. Core Business Flows
            $this->notifyProgress($runId, 'Business Flows', 'Executing cross-role lifecycles');
            $flowChecks = (new BusinessFlowsSuite($this->world))->run();
            foreach ($flowChecks as $check) {
                $this->repository->addCheck($runId, $check);
            }

            // 5. Browser E2E Suite (Laravel Dusk + Chrome) — Generic CMS Health
            if ($includeBrowser) {
                $this->notifyProgress($runId, 'Browser (Chrome)', 'Running Laravel Dusk in Chrome');
                $browserChecks = (new BrowserSuite($runId, $headless))->run();
                foreach ($browserChecks as $check) {
                    $this->repository->addCheck($runId, $check);
                }

                // 6. Ticket Regression Suite — Auto-discovers all LSBA*Test.php files
                $ticketFiles = glob(base_path('tests/Browser/') . '*Test.php') ?: [];
                $ticketCount = count(array_filter($ticketFiles, fn($f) =>
                    !in_array(basename($f), ['CmsDuskSuiteTest.php', 'ExampleTest.php'])
                    && !preg_match('/Subadmin|SubAdmin/i', basename($f))
                    && preg_match('/^[A-Z]+\d+Test\.php$/i', basename($f))
                ));

                if ($ticketCount > 0) {
                    $this->notifyProgress($runId, 'Ticket Regression', "Running {$ticketCount} Jira ticket regression suites");
                    $ticketChecks = (new TicketRegressionSuite($runId, $headless))->run();
                    foreach ($ticketChecks as $check) {
                        $this->repository->addCheck($runId, $check);
                    }
                }

                // 7. Deep Contract UI Suite — Strict Micro-UI, Typography & Computed Colors
                $deepQaDir = base_path('tests/Browser/DeepQA');
                if (is_dir($deepQaDir) && !empty(glob("{$deepQaDir}/*Test.php"))) {
                    $this->notifyProgress($runId, 'Deep Contract UI', 'Verifying micro-UI typography, exact texts, and computed colors');
                    $contractChecks = (new \SystemTestFramework\Suites\DeepContractSuite($runId, $headless))->run();
                    foreach ($contractChecks as $check) {
                        $this->repository->addCheck($runId, $check);
                    }
                }
            }

            // 6. Safe Teardown & Zero Data Loss Verification
            if (!$keepData) {
                $this->notifyProgress($runId, 'Cleanup', 'Purging synthetic test rows');
                $this->world->teardown();
            }

            $zeroLoss = $this->world->verifyZeroCustomerDataLoss();
            $this->repository->addCheck($runId, [
                'suite' => 'Safety Guard',
                'name' => 'Zero Customer Data Loss Verification',
                'status' => $zeroLoss ? 'passed' : 'failed',
                'message' => $zeroLoss
                    ? 'All customer tables verified intact. 100% clean row count.'
                    : 'Safety Warning! Potential unexpected table count change detected.',
                'duration_ms' => 10,
            ]);

            // Finalize Run
            $duration = round(microtime(true) - $startTime, 1);
            $finalRun = $this->repository->getRun($runId);
            $hasFailures = ($finalRun['counts']['failed'] ?? 0) > 0;

            // Compute New vs Recurring vs Resolved compared to previous run
            $defectComparison = $this->computeDefectComparison($finalRun, $previousRun);

            $this->repository->updateRun($runId, [
                'status' => $hasFailures ? 'failed' : 'passed',
                'completed_at' => now()->toIso8601String(),
                'duration_seconds' => $duration,
                'current_suite' => 'Completed',
                'current_check' => 'All suites finished',
                'defect_summary' => $defectComparison,
            ]);

            return $this->repository->getRun($runId);

        } catch (\Throwable $e) {
            if (!$keepData) {
                $this->world->teardown();
            }

            $this->repository->updateRun($runId, [
                'status' => 'failed',
                'completed_at' => now()->toIso8601String(),
                'duration_seconds' => round(microtime(true) - $startTime, 1),
                'current_suite' => 'Error',
                'current_check' => 'Aborted due to exception: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function notifyProgress(string $runId, string $suite, string $check): void
    {
        $this->repository->updateRun($runId, [
            'current_suite' => $suite,
            'current_check' => $check,
        ]);

        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $suite, $check);
        }
    }

    protected function computeDefectComparison(array $currentRun, ?array $previousRun): array
    {
        $currentDefects = collect($currentRun['defects'] ?? [])->pluck('name')->toArray();
        $prevDefects = collect($previousRun['defects'] ?? [])->pluck('name')->toArray();

        $new = array_values(array_diff($currentDefects, $prevDefects));
        $recurring = array_values(array_intersect($currentDefects, $prevDefects));
        $resolved = array_values(array_diff($prevDefects, $currentDefects));

        return [
            'new_count' => count($new),
            'recurring_count' => count($recurring),
            'resolved_count' => count($resolved),
            'new' => $new,
            'recurring' => $recurring,
            'resolved' => $resolved,
        ];
    }
}

