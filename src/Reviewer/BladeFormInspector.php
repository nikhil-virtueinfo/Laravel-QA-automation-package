<?php

namespace SystemTestFramework\Reviewer;

use Illuminate\Support\Str;

class BladeFormInspector
{
    /**
     * Inspect a Blade view file and extract all forms, inputs, conditional show/hide fields, and submit buttons.
     */
    public function inspectView(string $viewName): ?array
    {
        $viewPath = $this->resolveViewPath($viewName);
        if (!$viewPath || !file_exists($viewPath)) {
            return null;
        }

        $content = file_get_contents($viewPath);

        // Also check if this view includes sub-views (like @include('admin.listingCategory.form'))
        $includedViews = $this->extractIncludedViews($content);
        foreach ($includedViews as $included) {
            $subPath = $this->resolveViewPath($included);
            if ($subPath && file_exists($subPath)) {
                $content .= "\n" . file_get_contents($subPath);
            }
        }

        return [
            'view_path' => $viewPath,
            'forms' => $this->extractForms($content),
            'inputs' => $this->extractInputs($content),
            'conditional_fields' => $this->extractConditionalFields($content),
            'buttons' => $this->extractButtons($content),
            'has_ajax_triggers' => $this->detectAjaxTriggers($content),
        ];
    }

    protected function resolveViewPath(string $viewName): ?string
    {
        $normalized = str_replace('.', '/', $viewName);
        $standardPath = resource_path("views/{$normalized}.blade.php");

        if (file_exists($standardPath)) {
            return $standardPath;
        }

        // Try themes or adminTheme
        $themeMatches = glob(resource_path("views/**/{$normalized}.blade.php"));
        return !empty($themeMatches) ? $themeMatches[0] : null;
    }

    protected function extractIncludedViews(string $content): array
    {
        $matches = [];
        preg_match_all("/@include\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $matches);
        return $matches[1] ?? [];
    }

    protected function extractForms(string $content): array
    {
        $forms = [];

        // Laravel Collective Form::open
        if (preg_match_all("/Form::open\s*\(\s*(array\(.*?\)|\[.*?\])\s*\)/s", $content, $matches)) {
            foreach ($matches[1] as $formDef) {
                $forms[] = [
                    'type' => 'collective',
                    'raw' => $formDef,
                    'method' => Str::contains($formDef, "'method'=>'POST'") || Str::contains($formDef, '"method"=>"POST"') ? 'POST' : 'GET',
                ];
            }
        }

        // Standard HTML form tags
        if (preg_match_all('/<form\s+([^>]+)>/i', $content, $matches)) {
            foreach ($matches[1] as $attributes) {
                $forms[] = [
                    'type' => 'html',
                    'attributes' => $attributes,
                ];
            }
        }

        return $forms;
    }

    protected function extractInputs(string $content): array
    {
        $inputs = [];

        // 1. Form::text, Form::select, Form::textarea, Form::file
        $collectiveRegex = "/Form::(text|password|email|number|select|textarea|file)\s*\(\s*['\"]([^'\"]+)['\"]/i";
        if (preg_match_all($collectiveRegex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = strtolower($m[1]);
                $name = $m[2];
                $inputs[$name] = [
                    'type' => $type,
                    'name' => $name,
                    'is_required' => Str::contains($content, "name=\"{$name}\"") && Str::contains($content, 'text-danger'),
                ];
            }
        }

        // 2. Standard HTML inputs
        $htmlRegex = '/<(input|select|textarea)\s+[^>]*name=["\']([^"\']+)["\'][^>]*>/i';
        if (preg_match_all($htmlRegex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $tag = strtolower($m[1]);
                $name = $m[2];
                if (!isset($inputs[$name])) {
                    $inputs[$name] = [
                        'type' => $tag === 'input' ? 'text' : $tag,
                        'name' => $name,
                        'is_required' => Str::contains($m[0], 'required'),
                    ];
                }
            }
        }

        return array_values($inputs);
    }

    protected function extractConditionalFields(string $content): array
    {
        $conditionals = [];

        // Match elements with d-none or hide classes alongside custom identifiers (e.g. "d-none price-required")
        $regex = '/class=["\']([^"\']*d-none[^"\']*)["\']/i';
        if (preg_match_all($regex, $content, $matches)) {
            foreach ($matches[1] as $classList) {
                $classes = explode(' ', trim($classList));
                $targetClass = array_values(array_filter($classes, fn($c) => $c !== 'd-none' && !empty($c)));
                if (!empty($targetClass)) {
                    $conditionals[] = [
                        'container_class' => $classList,
                        'trigger_identifier' => $targetClass[0],
                    ];
                }
            }
        }

        return $conditionals;
    }

    protected function extractButtons(string $content): array
    {
        $buttons = [];

        // Search submit buttons and styled anchors
        $regex = '/<(button|a)\s+[^>]*(type=["\']submit["\']|class=["\'][^"\']*btn-[^"\']*["\'])[^>]*>(.*?)<\/\1>/is';
        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $tag = $m[1];
                $rawHtml = $m[3];
                $cleanText = trim(strip_tags($rawHtml));

                if (!empty($cleanText)) {
                    $buttons[] = [
                        'tag' => $tag,
                        'text' => $cleanText,
                        'is_submit' => Str::contains($m[0], 'type="submit"') || Str::contains($m[0], "type='submit'"),
                    ];
                }
            }
        }

        return $buttons;
    }

    protected function detectAjaxTriggers(string $content): array
    {
        $triggers = [];

        if (Str::contains($content, 'price-available')) {
            $triggers[] = 'price_available_toggle';
        }
        if (Str::contains($content, 'selact2basic') || Str::contains($content, 'select2')) {
            $triggers[] = 'select2_dynamic_loading';
        }
        if (Str::contains($content, 'swal') || Str::contains($content, 'SweetAlert')) {
            $triggers[] = 'sweetalert_confirmation';
        }

        return $triggers;
    }
}

