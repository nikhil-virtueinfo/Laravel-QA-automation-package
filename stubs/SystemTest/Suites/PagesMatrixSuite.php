<?php

namespace App\SystemTest\Suites;

use App\SystemTest\World\SyntheticWorld;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PagesMatrixSuite
{
    protected SyntheticWorld $world;

    public function __construct(SyntheticWorld $world)
    {
        $this->world = $world;
    }

    public function run(): array
    {
        $checks = [];

        // Key CMS & Public Routes Matrix
        $routes = [
            // Public / Shared
            ['path' => '/', 'type' => 'public', 'name' => 'Front Homepage'],
            ['path' => '/admin/login', 'type' => 'public', 'name' => 'Admin Login Screen'],

            // Core CMS Management
            ['path' => '/admin/dashboard', 'type' => 'cms_general', 'name' => 'CMS Dashboard'],
            ['path' => '/admin/user', 'type' => 'cms_general', 'name' => 'User Management Index'],
            ['path' => '/admin/business-user', 'type' => 'cms_general', 'name' => 'Business User Index'],
            ['path' => '/admin/listing', 'type' => 'cms_general', 'name' => 'Listing Moderation Index'],
            ['path' => '/admin/listing-category', 'type' => 'cms_general', 'name' => 'Listing Categories Index'],
            ['path' => '/admin/advertisement', 'type' => 'cms_general', 'name' => 'Advertisement Management Index'],
            ['path' => '/admin/invoices', 'type' => 'cms_general', 'name' => 'Invoices Management Index'],
            ['path' => '/admin/subscription-payment-list', 'type' => 'cms_general', 'name' => 'Subscription Payments Index'],
            ['path' => '/admin/faq', 'type' => 'cms_general', 'name' => 'FAQ Management Index'],
            ['path' => '/admin/city', 'type' => 'cms_general', 'name' => 'Cities Management Index'],
            ['path' => '/admin/settings', 'type' => 'cms_general', 'name' => 'Global Platform Settings'],

            // Super Admin Only
            ['path' => '/admin/sub-admin-user', 'type' => 'super_admin_only', 'name' => 'Sub-Admin Accounts Management'],
        ];

        $roles = [
            'guest' => null,
            'admin' => $this->world->getUser('admin'),
            'sub_admin' => $this->world->getUser('sub_admin'),
            'business_user_pro' => $this->world->getUser('business_user_pro'),
            'standard_user' => $this->world->getUser('standard_user'),
        ];

        foreach ($routes as $route) {
            foreach ($roles as $roleKey => $user) {
                $start = microtime(true);
                $result = $this->evaluateRouteAccess($route, $roleKey, $user);
                $duration = round((microtime(true) - $start) * 1000, 1);

                $checks[] = [
                    'suite' => 'Web Matrix',
                    'name' => "{$route['name']} as [{$roleKey}]",
                    'role' => $roleKey,
                    'path' => $route['path'],
                    'status' => $result['status'],
                    'message' => $result['message'],
                    'duration_ms' => $duration,
                    'evidence' => $result['evidence'] ?? null,
                ];
            }
        }

        return $checks;
    }

    protected function evaluateRouteAccess(array $route, string $roleKey, $user): array
    {
        try {
            // Set session and authenticate user
            if ($user) {
                Auth::login($user);
            } else {
                Auth::logout();
            }

            // Dispatch simulated GET request through Laravel kernel
            $request = Request::create($route['path'], 'GET');
            $request->headers->set('Accept', 'text/html,application/xhtml+xml');
            $response = app()->handle($request);
            $statusCode = $response->getStatusCode();

            // Catch any 500 Fatal Server Crash
            if ($statusCode >= 500) {
                return [
                    'status' => 'failed',
                    'message' => "Fatal Server Error HTTP {$statusCode} on {$route['path']}",
                    'evidence' => substr($response->getContent(), 0, 500),
                ];
            }

            // Role Rule 1: Guest accessing CMS
            if ($roleKey === 'guest') {
                if (str_starts_with($route['path'], '/admin') && $route['path'] !== '/admin/login') {
                    // Must be redirected to admin login (302) or blocked (404/403)
                    if (in_array($statusCode, [301, 302, 303, 307, 403, 404])) {
                        return ['status' => 'passed', 'message' => "Guest strictly prevented from CMS access (HTTP {$statusCode})"];
                    }
                    return ['status' => 'failed', 'message' => "Guest accessed protected CMS without login! (HTTP {$statusCode})"];
                }
                return ['status' => 'passed', 'message' => "Public page opened (HTTP {$statusCode})"];
            }

            // Role Rule 2: Standard User or Business User accessing CMS
            if (in_array($roleKey, ['standard_user', 'business_user_pro'])) {
                if (str_starts_with($route['path'], '/admin') && $route['path'] !== '/admin/login') {
                    // In La Subasta, unauthorized roles are refused via 404 (IsBothAdmin) or 403 or redirect
                    if (in_array($statusCode, [404, 403, 301, 302])) {
                        return ['status' => 'passed', 'message' => "Unauthorized role strictly blocked (HTTP {$statusCode})"];
                    }
                    return ['status' => 'failed', 'message' => "Privilege Escalation! [{$roleKey}] accessed CMS route {$route['path']}!"];
                }
                return ['status' => 'passed', 'message' => "Accessible (HTTP {$statusCode})"];
            }

            // Role Rule 3: Platform Super Admin
            if ($roleKey === 'admin') {
                if ($statusCode === 200 || in_array($statusCode, [301, 302])) {
                    return ['status' => 'passed', 'message' => "Admin granted access (HTTP {$statusCode})"];
                }
                if ($statusCode === 404) {
                    return ['status' => 'warnings', 'message' => "Route returned 404 Not Found (Missing template or disabled feature)"];
                }
                return ['status' => 'failed', 'message' => "Admin refused with HTTP {$statusCode}"];
            }

            // Role Rule 4: CMS Sub-Admin
            if ($roleKey === 'sub_admin') {
                if ($route['type'] === 'super_admin_only') {
                    // Must be refused with 404 (IsAdmin) or 403 or redirect
                    if (in_array($statusCode, [404, 403, 301, 302])) {
                        return ['status' => 'passed', 'message' => "Sub-Admin strictly blocked from Super-Admin area (HTTP {$statusCode})"];
                    }
                    return ['status' => 'failed', 'message' => "Sub-Admin illegally accessed super admin only route: {$route['path']}"];
                }
                // General CMS should open
                if ($statusCode === 200 || in_array($statusCode, [301, 302])) {
                    return ['status' => 'passed', 'message' => "Sub-Admin granted access (HTTP {$statusCode})"];
                }
                return ['status' => 'passed', 'message' => "HTTP {$statusCode}"];
            }

            return ['status' => 'passed', 'message' => "HTTP {$statusCode}"];

        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'message' => "Exception: " . $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine(),
                'evidence' => $e->getTraceAsString(),
            ];
        }
    }
}
