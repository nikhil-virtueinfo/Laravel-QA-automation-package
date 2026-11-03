<?php

namespace SystemTestFramework\Reviewer;

use Illuminate\Support\Str;
use ReflectionClass;

class ControllerAstInspector
{
    /**
     * Inspect a Controller class file directly and extract validation rules, views, and flash messages.
     */
    public function inspectController(string $controllerClass): ?array
    {
        if (!class_exists($controllerClass)) {
            return null;
        }

        try {
            $reflector = new ReflectionClass($controllerClass);
            $fileName = $reflector->getFileName();

            if (!$fileName || !file_exists($fileName)) {
                return null;
            }

            $sourceCode = file_get_contents($fileName);

            return [
                'class' => $controllerClass,
                'file' => $fileName,
                'views' => $this->extractRenderedViews($sourceCode),
                'validations' => $this->extractInlineValidations($sourceCode),
                'flash_messages' => $this->extractFlashMessages($sourceCode),
                'ajax_actions' => $this->extractAjaxActions($sourceCode),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function extractRenderedViews(string $source): array
    {
        $views = [];
        // Matches: view('admin.listingCategory.create', ...) or View::make(...)
        if (preg_match_all("/view\s*\(\s*['\"]([^'\"]+)['\"]/i", $source, $matches)) {
            $views = array_unique($matches[1]);
        }
        return $views;
    }

    protected function extractInlineValidations(string $source): array
    {
        $validations = [];

        // Matches: $request->validate([ ... ])
        $pattern = '/\$request->validate\s*\(\s*\[(.*?)\]\s*(?:,\s*\[(.*?)\])?\s*\)/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $rawRules = $match[1];
                $rawMessages = $match[2] ?? '';

                $parsedRules = $this->parseArrayEntries($rawRules);
                $parsedMessages = $this->parseArrayEntries($rawMessages);

                $validations[] = [
                    'rules' => $parsedRules,
                    'custom_messages' => $parsedMessages,
                ];
            }
        }

        return $validations;
    }

    protected function parseArrayEntries(string $arrayBody): array
    {
        $entries = [];
        $lines = explode("\n", $arrayBody);

        foreach ($lines as $line) {
            $line = trim($line);
            // Matches: 'name' => 'required' or "spanish_name" => "required"
            if (preg_match("/['\"]([^'\"]+)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $line, $entryMatch)) {
                $key = $entryMatch[1];
                $val = $entryMatch[2];
                $entries[$key] = $val;
            }
        }

        return $entries;
    }

    protected function extractFlashMessages(string $source): array
    {
        $messages = [];

        // 1. notificationMsg('success', ...) or notificationMsg('error', ...)
        if (preg_match_all("/notificationMsg\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*(.*?)\)/s", $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = $m[1];
                $rawMsg = trim($m[2]);
                $cleanMsg = trim($rawMsg, "'\" ");

                $messages[] = [
                    'type' => $type,
                    'expression' => $cleanMsg,
                ];
            }
        }

        // 2. session()->flash('success', '...') or redirect()->with('success', '...')
        if (preg_match_all("/(?:session\(\)->flash|with)\s*\(\s*['\"](success|error|status)['\"]\s*,\s*['\"]([^'\"]+)['\"]/i", $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $messages[] = [
                    'type' => $m[1],
                    'expression' => $m[2],
                ];
            }
        }

        return $messages;
    }

    protected function extractAjaxActions(string $source): array
    {
        $ajaxMethods = [];

        // Inspect methods that return response()->json(...)
        $methodRegex = '/public\s+function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*\{([^}]*response\(\)->json[^}]*)\}/is';
        if (preg_match_all($methodRegex, $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $methodName = $m[1];
                $body = $m[2];

                $ajaxMethods[$methodName] = [
                    'method' => $methodName,
                    'is_status_toggle' => Str::contains($methodName, 'Status') || Str::contains($methodName, 'Active'),
                    'is_sorting' => Str::contains($methodName, 'Sort') || Str::contains($methodName, 'Order'),
                ];
            }
        }

        return $ajaxMethods;
    }
}

