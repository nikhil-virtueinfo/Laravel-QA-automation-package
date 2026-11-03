<?php

namespace App\SystemTest\Suites;

use App\SystemTest\World\SyntheticWorld;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiMatrixSuite
{
    protected SyntheticWorld $world;

    public function __construct(SyntheticWorld $world)
    {
        $this->world = $world;
    }

    public function run(): array
    {
        $checks = [];

        // 1. Comprehensive Public Data APIs
        $publicEndpoints = [
            ['path' => '/api/state/list', 'name' => 'USA States Directory API'],
            ['path' => '/api/city', 'name' => 'Cities List API'],
            ['path' => '/api/faq', 'name' => 'FAQs Catalog API'],
            ['path' => '/api/cms-list', 'name' => 'Public CMS Content List API'],
            ['path' => '/api/contact-us', 'name' => 'Contact Us Info API'],
            ['path' => '/api/register/category', 'name' => 'Registration Categories API'],
            ['path' => '/api/advertisement/sponsored', 'name' => 'Sponsored Ads Catalog API'],
            ['path' => '/api/advertisement/bigbox', 'name' => 'Bigbox Ads Catalog API'],
            ['path' => '/api/advertisement/billboard', 'name' => 'Billboard Ads Catalog API'],
            ['path' => '/api/map', 'name' => 'Map Markers & Radius API'],
            ['path' => '/api/app-allow/guest', 'name' => 'Guest App Permissions API'],
        ];

        // 2. Protected Endpoints (Requires auth:api)
        $protectedEndpoints = [
            ['path' => '/api/subscribe-plan', 'name' => 'Membership & Subscription Plans API'],
            ['path' => '/api/category', 'name' => 'Secured Categories Index API'],
            ['path' => '/api/user/view', 'name' => 'Current User Profile API'],
            ['path' => '/api/notification', 'name' => 'User Notifications List API'],
            ['path' => '/api/notification/unread/count', 'name' => 'Unread Notifications Count API'],
            ['path' => '/api/blocked-users', 'name' => 'Blocked Chat Users API'],
            ['path' => '/api/useraddresslist', 'name' => 'Saved User Addresses API'],
            ['path' => '/api/listing_count', 'name' => 'User Listings Count Metric API'],
        ];

        // --- SECTION A: Public APIs ---
        foreach ($publicEndpoints as $endpoint) {
            $start = microtime(true);
            $req = Request::create($endpoint['path'], 'GET');
            $req->headers->set('Accept', 'application/json');
            $res = app()->handle($req);
            $duration = round((microtime(true) - $start) * 1000, 1);
            $status = $res->getStatusCode();

            $leakCheck = $this->assertNoSecretsLeaked($res->getContent());

            $checks[] = [
                'suite' => 'REST API',
                'name' => "Public: {$endpoint['name']}",
                'path' => $endpoint['path'],
                'status' => ($status === 200 && $leakCheck['ok']) ? 'passed' : 'failed',
                'message' => ($status === 200 && $leakCheck['ok'])
                    ? "HTTP 200 JSON OK | Zero secrets leaked"
                    : "Status HTTP {$status} | " . ($leakCheck['error'] ?? 'API error'),
                'duration_ms' => $duration,
            ];
        }

        // --- SECTION B: Authentication & POST Operations ---
        $checks[] = $this->testLoginValidation();
        $checks[] = $this->testInvalidPasswordRejection();
        $checks[] = $this->testForgetPasswordValidation();
        $checks[] = $this->testLatLongValidation();

        // --- SECTION C: Protected APIs (Unauthenticated Guest Guard) ---
        foreach ($protectedEndpoints as $endpoint) {
            $start = microtime(true);
            $req = Request::create($endpoint['path'], 'GET');
            $req->headers->set('Accept', 'application/json');
            $res = app()->handle($req);
            $duration = round((microtime(true) - $start) * 1000, 1);
            $status = $res->getStatusCode();

            $checks[] = [
                'suite' => 'REST API',
                'name' => "Guest Guard: {$endpoint['name']}",
                'path' => $endpoint['path'],
                'status' => in_array($status, [401, 403]) ? 'passed' : 'failed',
                'message' => in_array($status, [401, 403])
                    ? "Unauthenticated caller strictly refused (HTTP {$status})"
                    : "Security Flaw! Protected endpoint returned HTTP {$status} without token!",
                'duration_ms' => $duration,
            ];
        }

        // --- SECTION D: Protected APIs (Authenticated with Test User) ---
        $testUser = $this->world->getUser('standard_user');
        if ($testUser) {
            foreach ($protectedEndpoints as $endpoint) {
                $start = microtime(true);
                Auth::guard('api')->setUser($testUser);
                $req = Request::create($endpoint['path'], 'GET');
                $req->headers->set('Accept', 'application/json');
                $res = app()->handle($req);
                $duration = round((microtime(true) - $start) * 1000, 1);
                $status = $res->getStatusCode();
                $leakCheck = $this->assertNoSecretsLeaked($res->getContent());

                $checks[] = [
                    'suite' => 'REST API',
                    'name' => "Auth User: {$endpoint['name']}",
                    'path' => $endpoint['path'],
                    'status' => ($status === 200 && $leakCheck['ok']) ? 'passed' : ($status < 500 ? 'warnings' : 'failed'),
                    'message' => "HTTP {$status} | " . ($leakCheck['ok'] ? 'No secrets exposed' : $leakCheck['error']),
                    'duration_ms' => $duration,
                ];
            }
        }

        return $checks;
    }

    protected function testLoginValidation(): array
    {
        $start = microtime(true);
        $req = Request::create('/api/login', 'POST', []);
        $req->headers->set('Accept', 'application/json');
        $res = app()->handle($req);
        $duration = round((microtime(true) - $start) * 1000, 1);

        $body = json_decode($res->getContent(), true) ?: [];
        $hasValidationMsg = str_contains($body['message'] ?? '', 'required') || ($res->getStatusCode() === 422);

        return [
            'suite' => 'REST API',
            'name' => 'POST /api/login: Empty Payload Input Validation',
            'path' => '/api/login',
            'status' => ($res->getStatusCode() < 500 && $hasValidationMsg) ? 'passed' : 'failed',
            'message' => ($res->getStatusCode() < 500 && $hasValidationMsg)
                ? 'Input validation cleanly caught missing email/password without 500 crash.'
                : 'Login failed to return expected validation message.',
            'duration_ms' => $duration,
        ];
    }

    protected function testInvalidPasswordRejection(): array
    {
        $start = microtime(true);
        $testUser = $this->world->getUser('standard_user');
        $email = $testUser ? $testUser->email : 'test@example.com';

        $req = Request::create('/api/login', 'POST', [
            'email' => $email,
            'password' => 'CompletelyWrongPassword!123',
        ]);
        $req->headers->set('Accept', 'application/json');
        $res = app()->handle($req);
        $duration = round((microtime(true) - $start) * 1000, 1);

        $statusCode = $res->getStatusCode();
        $isBlocked = in_array($statusCode, [400, 401, 403, 404, 422]) || (str_contains($res->getContent(), 'Wrong') || str_contains($res->getContent(), 'invalid'));

        return [
            'suite' => 'REST API',
            'name' => 'POST /api/login: Invalid Password Security Rejection',
            'path' => '/api/login',
            'status' => ($statusCode < 500 && $isBlocked) ? 'passed' : 'failed',
            'message' => ($statusCode < 500 && $isBlocked)
                ? "Invalid credentials safely rejected (HTTP {$statusCode}) with error response."
                : "Security failure: Invalid password did not trigger safe rejection.",
            'duration_ms' => $duration,
        ];
    }

    protected function testForgetPasswordValidation(): array
    {
        $start = microtime(true);
        $req = Request::create('/api/forget-password', 'POST', []);
        $req->headers->set('Accept', 'application/json');
        $res = app()->handle($req);
        $duration = round((microtime(true) - $start) * 1000, 1);

        $body = json_decode($res->getContent(), true) ?: [];
        $hasValidationMsg = str_contains($body['message'] ?? '', 'required') || ($res->getStatusCode() === 422);

        return [
            'suite' => 'REST API',
            'name' => 'POST /api/forget-password: Input Validation Barrier',
            'path' => '/api/forget-password',
            'status' => ($res->getStatusCode() < 500 && $hasValidationMsg) ? 'passed' : 'failed',
            'message' => ($res->getStatusCode() < 500 && $hasValidationMsg)
                ? 'Missing email parameter safely caught with validation message.'
                : 'Forget password threw unhandled response on empty input.',
            'duration_ms' => $duration,
        ];
    }

    protected function testLatLongValidation(): array
    {
        $start = microtime(true);
        $req = Request::create('/api/latlong-to-address', 'POST', [
            'latitude' => 29.7604,
            'longitude' => -95.3698,
        ]);
        $req->headers->set('Accept', 'application/json');
        $res = app()->handle($req);
        $duration = round((microtime(true) - $start) * 1000, 1);

        $statusCode = $res->getStatusCode();

        return [
            'suite' => 'REST API',
            'name' => 'POST /api/latlong-to-address: Geocoding Coordinate Conversion',
            'path' => '/api/latlong-to-address',
            'status' => ($statusCode < 500) ? 'passed' : 'failed',
            'message' => ($statusCode < 500)
                ? "Coordinates safely handled (HTTP {$statusCode}) without server exception."
                : "Geocoding conversion threw HTTP {$statusCode}.",
            'duration_ms' => $duration,
        ];
    }

    protected function assertNoSecretsLeaked(string $content): array
    {
        $forbiddenKeywords = ['password', 'remember_token', 'client_secret'];
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return ['ok' => true];
        }

        foreach ($forbiddenKeywords as $keyword) {
            if ($this->arrayHasKeyRecursive($decoded, $keyword)) {
                return ['ok' => false, 'error' => "Forbidden field [{$keyword}] leaked in API JSON!"];
            }
        }

        return ['ok' => true];
    }

    protected function arrayHasKeyRecursive(array $array, string $key): bool
    {
        if (array_key_exists($key, $array) && !empty($array[$key])) {
            return true;
        }
        foreach ($array as $value) {
            if (is_array($value) && $this->arrayHasKeyRecursive($value, $key)) {
                return true;
            }
        }
        return false;
    }
}
