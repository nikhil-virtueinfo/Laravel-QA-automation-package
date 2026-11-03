<?php

namespace SystemTestFramework\Reviewer;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class RouteInspector
{
    protected array $ignoredPatterns;

    public function __construct(array $ignoredPatterns = [])
    {
        $this->ignoredPatterns = !empty($ignoredPatterns)
            ? $ignoredPatterns
            : config('system-test.ignore_routes', ['_ignition/*', 'telescope/*', 'horizon/*']);
    }

    /**
     * Inspect all registered application routes categorized by type.
     */
    public function discoverRoutes(): array
    {
        $allRoutes = RouteFacade::getRoutes();
        $discovered = [
            'cms' => [],
            'web' => [],
            'api' => [],
        ];

        /** @var Route $route */
        foreach ($allRoutes as $route) {
            $uri = $route->uri();
            $methods = array_diff($route->methods(), ['HEAD']);

            if ($this->shouldIgnore($uri)) {
                continue;
            }

            // Exclude parameterized routes without default bindings for initial crawler
            $hasParameters = Str::contains($uri, '{');

            $meta = [
                'uri' => '/' . ltrim($uri, '/'),
                'methods' => $methods,
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
                'has_parameters' => $hasParameters,
                'requires_auth' => $this->requiresAuthentication($route->middleware()),
            ];

            if (Str::startsWith($uri, 'api/') || in_array('api', $route->middleware())) {
                $discovered['api'][] = $meta;
            } elseif (Str::startsWith($uri, 'admin') || Str::contains($uri, 'cms')) {
                $discovered['cms'][] = $meta;
            } else {
                $discovered['web'][] = $meta;
            }
        }

        return $discovered;
    }

    protected function shouldIgnore(string $uri): bool
    {
        foreach ($this->ignoredPatterns as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    protected function requiresAuthentication(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (Str::startsWith($m, 'auth') || $m === 'verified') {
                return true;
            }
        }
        return false;
    }
}

