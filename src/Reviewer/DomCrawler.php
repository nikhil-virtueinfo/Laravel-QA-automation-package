<?php

namespace SystemTestFramework\Reviewer;

use Symfony\Component\Process\Process;

class DomCrawler
{
    protected string $contractsDir;
    protected array $trackedStyles;

    public function __construct()
    {
        $this->contractsDir = config('system-test.deep_qa.contracts_dir', storage_path('app/system-tests/contracts'));
        $this->trackedStyles = config('system-test.deep_qa.tracked_styles', [
            'background-color',
            'color',
            'font-size',
            'font-weight'
        ]);

        if (!is_dir($this->contractsDir)) {
            @mkdir($this->contractsDir, 0755, true);
        }
    }

    /**
     * Generate the JavaScript snippet injected into the browser to extract the DOM contract.
     */
    public static function getExtractionScript(): string
    {
        return <<<'JS'
(function() {
    var elements = [];
    var targets = document.querySelectorAll("button, a.btn, input, select, textarea, th, .badge, h1, h2");
    
    function getCleanSelector(el) {
        if (el.id) return '#' + el.id;
        if (el.getAttribute('name')) return el.tagName.toLowerCase() + '[name="' + el.getAttribute('name') + '"]';
        if (el.className && typeof el.className === 'string') {
            var classes = el.className.trim().split(/\s+/).filter(function(c) {
                return !c.startsWith('hover:') && !c.startsWith('focus:') && c.length > 2;
            });
            if (classes.length > 0) {
                return el.tagName.toLowerCase() + '.' + classes.slice(0, 2).join('.');
            }
        }
        return el.tagName.toLowerCase();
    }

    targets.forEach(function(el) {
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return;

        var text = (el.innerText || el.textContent || '').trim();
        var placeholder = el.getAttribute('placeholder');

        elements.push({
            tag: el.tagName.toLowerCase(),
            selector: getCleanSelector(el),
            exact_text: text ? text.substring(0, 100) : null,
            placeholder: placeholder || null,
            styles: {
                'background-color': style.backgroundColor,
                'color': style.color,
                'font-size': style.fontSize,
                'font-weight': style.fontWeight
            }
        });
    });

    return elements;
})();
JS;
    }

    /**
     * Save the extracted contract to disk for regression comparison.
     */
    public function saveContract(string $routeUri, array $elements, array $validations = []): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($routeUri, '/')) ?: 'root';
        $filePath = "{$this->contractsDir}/{$safeName}.json";

        $contract = [
            'route' => $routeUri,
            'captured_at' => now()->toIso8601String(),
            'elements_count' => count($elements),
            'elements' => $elements,
            'validations' => $validations,
        ];

        file_put_contents($filePath, json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $filePath;
    }

    public function loadContract(string $routeUri): ?array
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($routeUri, '/')) ?: 'root';
        $filePath = "{$this->contractsDir}/{$safeName}.json";

        if (!file_exists($filePath)) {
            return null;
        }

        return json_decode(file_get_contents($filePath), true);
    }
}

