<?php

namespace SystemTestFramework;

use Illuminate\Support\ServiceProvider;
use SystemTestFramework\Commands\SystemTestGenerateDeepTests;
use SystemTestFramework\Commands\SystemTestInstallCommand;
use SystemTestFramework\Commands\SystemTestRunCommand;
use SystemTestFramework\Macros\DuskAssertions;

class SystemTestServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/system-test.php',
            'system-test'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // 1. Register Custom Dusk Macros for Micro-UI & Computed CSS assertion
        DuskAssertions::register();

        // 2. Register Console Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SystemTestInstallCommand::class,
                SystemTestGenerateDeepTests::class,
                SystemTestRunCommand::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/system-test.php' => config_path('system-test.php'),
            ], 'system-test-config');
        }

        // 3. Load Views if present
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'system-test');
    }
}

