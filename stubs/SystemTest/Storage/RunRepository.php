<?php

namespace App\SystemTest\Storage;

use Illuminate\Support\Facades\File;

class RunRepository
{
    protected string $baseDir;

    public function __construct()
    {
        $this->baseDir = storage_path('app/system-tests');
        if (!File::isDirectory($this->baseDir . '/runs')) {
            File::makeDirectory($this->baseDir . '/runs', 0755, true);
        }
    }

    public function createRun(string $trigger = 'manual'): array
    {
        $runId = date('ymdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
        $runDir = $this->baseDir . "/runs/{$runId}";
        File::makeDirectory($runDir . '/evidence', 0755, true);

        $initialData = [
            'id' => $runId,
            'trigger' => $trigger,
            'status' => 'running', // running, passed, failed
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
            'duration_seconds' => 0,
            'environment' => config('app.env', 'local'),
            'current_suite' => 'Initializing',
            'current_check' => 'Starting synthetic world',
            'counts' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0,
                'skipped' => 0,
            ],
            'checks' => [],
            'defects' => [],
            'metadata' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ]
        ];

        File::put($runDir . '/run.json', json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $initialData;
    }

    public function getRun(string $runId): ?array
    {
        $file = $this->baseDir . "/runs/{$runId}/run.json";
        if (!File::exists($file)) {
            return null;
        }
        return json_decode(File::get($file), true);
    }

    public function updateRun(string $runId, array $data): void
    {
        $existing = $this->getRun($runId);
        if (!$existing) {
            return;
        }
        $merged = array_merge($existing, $data);
        File::put($this->baseDir . "/runs/{$runId}/run.json", json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addCheck(string $runId, array $check): void
    {
        $run = $this->getRun($runId);
        if (!$run) {
            return;
        }

        $status = strtolower($check['status'] ?? 'passed');
        $run['counts']['total']++;
        if (isset($run['counts'][$status])) {
            $run['counts'][$status]++;
        } else {
            $run['counts']['passed']++;
        }

        $run['current_suite'] = $check['suite'] ?? $run['current_suite'];
        $run['current_check'] = $check['name'] ?? $run['current_check'];

        if ($status === 'failed') {
            $run['defects'][] = [
                'suite' => $check['suite'] ?? 'General',
                'name' => $check['name'] ?? 'Unnamed Check',
                'error' => $check['message'] ?? 'Check failed',
                'evidence' => $check['evidence'] ?? null,
            ];
        }

        $run['checks'][] = $check;
        File::put($this->baseDir . "/runs/{$runId}/run.json", json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function listRuns(int $limit = 25): array
    {
        $runs = [];
        $dirs = glob($this->baseDir . '/runs/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);

        foreach (array_slice($dirs, 0, $limit) as $dir) {
            $file = $dir . '/run.json';
            if (File::exists($file)) {
                $data = json_decode(File::get($file), true);
                if ($data) {
                    $runs[] = $data;
                }
            }
        }
        return $runs;
    }

    public function latestRun(): ?array
    {
        $runs = $this->listRuns(1);
        return $runs[0] ?? null;
    }

    public function cleanupOldRuns(int $days = 1): int
    {
        $deleted = 0;
        $dirs = glob($this->baseDir . '/runs/*', GLOB_ONLYDIR) ?: [];
        $cutoff = time() - ($days * 86400);

        foreach ($dirs as $dir) {
            if (filemtime($dir) < $cutoff) {
                File::deleteDirectory($dir);
                $deleted++;
            }
        }
        return $deleted;
    }

    public function deleteRun(string $runId): bool
    {
        $safeRunId = basename($runId);
        $dir = $this->baseDir . "/runs/{$safeRunId}";
        if (File::isDirectory($dir)) {
            return File::deleteDirectory($dir);
        }
        return false;
    }

    public function getEvidenceDir(string $runId): string
    {
        return $this->baseDir . "/runs/{$runId}/evidence";
    }
}

