<?php

namespace SystemTestFramework\Macros;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert as PHPUnit;

class DuskAssertions
{
    /**
     * Register custom Dusk macros for deep UI contracts & computed CSS checking.
     */
    public static function register(): void
    {
        if (!class_exists(Browser::class)) {
            return;
        }

        // 1. Assert Computed CSS Style (e.g. background-color, color, font-size)
        Browser::macro('assertComputedStyle', function (string $selector, string $property, string $expectedValue) {
            /** @var Browser $this */
            $actual = $this->script(
                "var el = document.querySelector('{$selector}');" .
                "return el ? window.getComputedStyle(el).getPropertyValue('{$property}').trim() : null;"
            )[0];

            PHPUnit::assertNotNull(
                $actual,
                "Target element '{$selector}' was not found in the DOM while checking style '{$property}'."
            );

            // Normalization: clean whitespace and lowercase
            $expectedClean = strtolower(trim($expectedValue));
            $actualClean = strtolower(trim($actual));

            PHPUnit::assertEquals(
                $expectedClean,
                $actualClean,
                "Computed style '{$property}' for '{$selector}' mismatch! Expected: '{$expectedValue}', Actual rendered: '{$actual}'."
            );

            return $this;
        });

        // 2. Assert Exact Inner Text (Strict Contract without partial matching)
        Browser::macro('assertExactText', function (string $selector, string $expectedText) {
            /** @var Browser $this */
            $actual = $this->script(
                "var el = document.querySelector('{$selector}');" .
                "return el ? el.innerText.trim() : null;"
            )[0];

            PHPUnit::assertNotNull(
                $actual,
                "Target element '{$selector}' was not found in the DOM while checking exact text."
            );

            PHPUnit::assertEquals(
                trim($expectedText),
                $actual,
                "Strict text contract violated at '{$selector}'! Expected: '{$expectedText}', Actual rendered: '{$actual}'."
            );

            return $this;
        });

        // 3. Step Screenshot with automatic evidence directory detection
        Browser::macro('evidenceScreenshot', function (string $filename) {
            /** @var Browser $this */
            $dir = env('SYSTEM_TEST_SCREENSHOT_DIR', storage_path('app/system-tests/evidence'));
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
            if (!str_ends_with($cleanName, '.png')) {
                $cleanName .= '.png';
            }

            $this->driver->takeScreenshot("{$dir}/{$cleanName}");
            return $this;
        });

        // 4. Assert zero JavaScript console errors
        Browser::macro('assertNoJavaScriptErrors', function () {
            /** @var Browser $this */
            $logs = $this->driver->manage()->getLog('browser');
            $errors = [];

            foreach ($logs as $log) {
                if (isset($log['level']) && in_array(strtoupper($log['level']), ['SEVERE', 'ERROR'])) {
                    $errors[] = "[{$log['level']}] " . ($log['message'] ?? 'Unknown JS error');
                }
            }

            PHPUnit::assertEmpty(
                $errors,
                "JavaScript console errors detected during page execution:\n" . implode("\n", $errors)
            );

            return $this;
        });
    }
}

