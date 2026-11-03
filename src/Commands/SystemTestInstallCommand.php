<?php

namespace SystemTestFramework\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SystemTestInstallCommand extends Command
{
    protected $signature = 'system-test:install {--force : Overwrite existing configuration and scaffolds}';
    protected $description = 'Auto-detect project dependencies, install Laravel Dusk if missing, and scaffold the complete System-Test engine & Admin UI';

    public function handle(): int
    {
        $this->line("");
        $this->info("================================================================================");
        $this->info("🛠️  AUTONOMOUS SYSTEM-TEST FRAMEWORK INSTALLER");
        $this->info("================================================================================");

        // 1. Check PHP and Laravel versions
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();
        $this->line("• Host Environment: PHP {$phpVersion} | Laravel {$laravelVersion}");

        // 2. Check and provision Laravel Dusk
        $this->line("\n[1/6] Verifying Laravel Dusk & Chrome Engine...");
        $duskInstalled = class_exists(\Laravel\Dusk\DuskServiceProvider::class) || class_exists(\Laravel\Dusk\Browser::class);

        if (!$duskInstalled) {
            $this->comment("⚠️  Laravel Dusk is not installed. Auto-installing as dev dependency...");
            $composerProcess = new Process(['composer', 'require', '--dev', 'laravel/dusk:^8.0'], base_path(), null, null, 300);
            $composerProcess->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$composerProcess->isSuccessful()) {
                $this->error("❌ Failed to install Laravel Dusk via composer. Please run: composer require --dev laravel/dusk");
                return self::FAILURE;
            }

            $this->info("✓ Laravel Dusk installed successfully.");
        } else {
            $this->info("✓ Laravel Dusk is already present.");
        }

        // Ensure tests/Browser exists & Chrome Driver is provisioned
        if (!is_dir(base_path('tests/Browser'))) {
            $this->comment("Setting up tests/Browser scaffolding...");
            $this->callSilent('dusk:install');
        }

        $this->comment("Checking Chrome Driver binaries...");
        try {
            $this->call('dusk:chrome-driver', ['--detect' => true]);
        } catch (\Throwable $e) {
            $this->line("  Chrome Driver detection note: " . $e->getMessage());
        }

        // 3. Create required directories
        $this->line("\n[2/6] Initializing Storage & Test Directories...");
        $directories = [
            base_path('tests/Browser/DeepQA/Admin'),
            base_path('tests/Browser/DeepQA/Front'),
            base_path('tests/Feature/DeepQA/Api'),
            storage_path('app/system-tests/evidence'),
            storage_path('app/system-tests/contracts'),
            storage_path('app/system-tests/runs'),
            resource_path('views/admin/system_tests'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
                $this->line("  Created: " . str_replace(base_path() . '/', '', $dir));
            }
        }
        $this->info("✓ Storage & Test directories initialized.");

        // 4. Publish configuration if not present
        $this->line("\n[3/6] Publishing Configuration...");
        $configDest = config_path('system-test.php');
        $configSrc = __DIR__ . '/../../config/system-test.php';

        if (!file_exists($configDest) || $this->option('force')) {
            @copy($configSrc, $configDest);
            $this->info("✓ Configuration published to config/system-test.php");
        } else {
            $this->line("✓ config/system-test.php already exists.");
        }

        // 5. Publish Admin Dashboard Views (index & report)
        $this->line("\n[4/6] Publishing Admin Panel Web Dashboard Views...");
        $viewsDestDir = resource_path('views/admin/system_tests');
        $viewsSrcDir = __DIR__ . '/../../resources/views/admin/system_tests';

        $views = ['index.blade.php', 'report.blade.php'];
        foreach ($views as $view) {
            $src = "{$viewsSrcDir}/{$view}";
            $dest = "{$viewsDestDir}/{$view}";
            if (file_exists($src) && (!file_exists($dest) || $this->option('force'))) {
                @copy($src, $dest);
                $this->info("✓ Published: resources/views/admin/system_tests/{$view}");
            } else {
                $this->line("✓ resources/views/admin/system_tests/{$view} already present.");
            }
        }

        // 6. Publish SystemTest Core Engine (Runner, Suites, World, Storage)
        $this->line("\n[5/7] Publishing SystemTest Core Engine (app/SystemTest/)...");
        $engineDestDir = app_path('SystemTest');
        $engineSrcDir = __DIR__ . '/../../stubs/SystemTest';

        if (is_dir($engineSrcDir)) {
            $this->copyDirectoryRecursively($engineSrcDir, $engineDestDir, $this->option('force'));
            $this->info("✓ Published SystemTest Engine to app/SystemTest/");
        }

        // 7. Publish Admin Controller
        $this->line("\n[6/7] Publishing SystemTestController...");
        $controllerDest = app_path('Http/Controllers/Admin/SystemTestController.php');
        $controllerStub = __DIR__ . '/../../stubs/SystemTestController.stub';

        if (file_exists($controllerStub) && (!file_exists($controllerDest) || $this->option('force'))) {
            @copy($controllerStub, $controllerDest);
            $this->info("✓ Published: app/Http/Controllers/Admin/SystemTestController.php");
        } else {
            $this->line("✓ app/Http/Controllers/Admin/SystemTestController.php already present.");
        }

        // 8. Auto-Register Routes in routes/web.php
        $this->line("\n[7/7] Verifying Web Routes Registration...");
        $webRoutesPath = base_path('routes/web.php');
        if (file_exists($webRoutesPath)) {
            $webContent = file_get_contents($webRoutesPath);
            if (!str_contains($webContent, 'system-tests')) {
                $routesSnippet = <<<'PHP'

// --- Autonomous System-Test Dashboard Routes ---
Route::group(['prefix' => 'admin', 'middleware' => ['web']], function () {
    Route::get('system-tests', 'Admin\SystemTestController@index')->name('admin.system-tests.index');
    Route::post('system-tests/run', 'Admin\SystemTestController@run')->name('admin.system-tests.run');
    Route::get('system-tests/status/{run_id}', 'Admin\SystemTestController@status')->name('admin.system-tests.status');
    Route::get('system-tests/report/{run_id}', 'Admin\SystemTestController@report')->name('admin.system-tests.report');
    Route::get('system-tests/download/{run_id}', 'Admin\SystemTestController@download')->name('admin.system-tests.download');
    Route::get('system-tests/screenshot/{run_id}/{filename}', 'Admin\SystemTestController@screenshot')->name('admin.system-tests.screenshot')->where('filename', '.*');
    Route::post('system-tests/cleanup', 'Admin\SystemTestController@cleanup')->name('admin.system-tests.cleanup');
    Route::delete('system-tests/run/{run_id}', 'Admin\SystemTestController@destroyRun')->name('admin.system-tests.destroy-run');
});
PHP;
                file_put_contents($webRoutesPath, $webContent . "\n" . $routesSnippet);
                $this->info("✓ Added System-Test routes to routes/web.php");
            } else {
                $this->line("✓ System-Test routes are already registered in routes/web.php.");
            }
        }

        // Verification Summary
        $this->line("\n================================================================================");
        $this->info("🎉 System-Test Framework is fully installed and ready!");
        $this->info("================================================================================");
        $this->line("• Admin Dashboard URL : " . url('/admin/system-tests'));
        $this->line("• Run Deep Review     : php artisan system-test:generate-deep-tests");
        $this->line("• Run System Tests    : php artisan system-test:run");
        $this->line("");

        return self::SUCCESS;
    }

    protected function copyDirectoryRecursively(string $src, string $dest, bool $force = false): void
    {
        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = "{$src}/{$item}";
            $destPath = "{$dest}/{$item}";

            if (is_dir($srcPath)) {
                $this->copyDirectoryRecursively($srcPath, $destPath, $force);
            } elseif (!file_exists($destPath) || $force) {
                @copy($srcPath, $destPath);
            }
        }
    }
}
