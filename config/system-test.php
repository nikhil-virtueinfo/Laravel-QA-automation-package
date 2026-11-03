<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Test Authentication
    |--------------------------------------------------------------------------
    | Credentials or User IDs used by the browser crawler and test suites
    | to authenticate as different roles during test runs.
    */
    'auth' => [
        'admin_id' => env('SYSTEM_TEST_ADMIN_ID', 1),
        'admin_guard' => 'web',
        'customer_id' => env('SYSTEM_TEST_CUSTOMER_ID', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Filters
    |--------------------------------------------------------------------------
    | Route URI patterns to exclude from automated crawling and matrix checks.
    */
    'ignore_routes' => [
        '_ignition/*',
        'telescope/*',
        'horizon/*',
        'sanctum/*',
        'livewire/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep QA Contract Specifications
    |--------------------------------------------------------------------------
    | Computed CSS styles and attributes tracked for pixel/DOM contract validation.
    */
    'deep_qa' => [
        'tracked_styles' => [
            'background-color',
            'color',
            'font-size',
            'font-weight',
            'border-color',
        ],
        'output_dir' => base_path('tests/Browser/DeepQA'),
        'contracts_dir' => storage_path('app/system-tests/contracts'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Dashboard & Evidence Storage
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => true,
        'route_prefix' => 'admin/system-tests',
        'layout' => env('SYSTEM_TEST_LAYOUT', null), // null = auto-detect host project's admin layout
        'middleware' => ['web'],
    ],

    'evidence_dir' => storage_path('app/system-tests/evidence'),
    'runs_dir' => storage_path('app/system-tests/runs'),
];

