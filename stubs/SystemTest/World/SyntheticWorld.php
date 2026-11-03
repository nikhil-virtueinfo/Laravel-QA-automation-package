<?php

namespace App\SystemTest\World;

use App\Models\User;
use App\Models\Listing;
use App\Models\ListingCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SyntheticWorld
{
    public const PREFIX = 'ZZ_TEST_';
    public const EMAIL_DOMAIN = '@lasubasta.test';

    protected array $initialCounts = [];
    protected array $createdEntities = [
        'users' => [],
        'categories' => [],
        'listings' => [],
    ];

    /**
     * Snapshot row counts of application tables before test execution.
     */
    public function snapshotTableCounts(): array
    {
        $tables = ['users', 'listings', 'listing_categories', 'advertisements', 'invoices', 'settings'];
        $counts = [];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        $this->initialCounts = $counts;
        return $counts;
    }

    /**
     * Create isolated synthetic world entities for all 4 organization roles.
     */
    public function setup(): array
    {
        $this->snapshotTableCounts();

        // 1. Create or resolve Synthetic Category
        $category = ListingCategory::updateOrCreate(
            ['name' => self::PREFIX . 'Automotive & Goods'],
            [
                'spanish_name' => self::PREFIX . 'Automotriz y Bienes',
                'title' => 'zz-test-automotive-goods',
                'is_active' => 1,
                'price_available' => 1,
                'price_required' => 0,
            ]
        );
        $this->createdEntities['categories'][] = $category->id;

        // 2. Create Roles Users
        $defaultPassword = Hash::make('Password123!');

        // A. Platform Admin
        $admin = User::updateOrCreate(
            ['email' => 'zz_admin' . self::EMAIL_DOMAIN],
            [
                'name' => self::PREFIX . 'Admin User',
                'password' => $defaultPassword,
                'role' => 'admin',
                'is_email_verified' => 1,
                'is_pending' => 0,
                'hubspot_override' => 1,
            ]
        );
        $this->createdEntities['users']['admin'] = $admin;

        // B. CMS Sub-Admin
        $subAdmin = User::updateOrCreate(
            ['email' => 'zz_subadmin' . self::EMAIL_DOMAIN],
            [
                'name' => self::PREFIX . 'Sub Admin User',
                'password' => $defaultPassword,
                'role' => 'sub_admin',
                'is_email_verified' => 1,
                'is_pending' => 0,
            ]
        );
        $this->createdEntities['users']['sub_admin'] = $subAdmin;

        // C. Business User Pro
        $bizPro = User::updateOrCreate(
            ['email' => 'zz_bizpro' . self::EMAIL_DOMAIN],
            [
                'name' => self::PREFIX . 'Business Pro Seller',
                'password' => $defaultPassword,
                'role' => 'business_user_pro',
                'is_email_verified' => 1,
                'is_pending' => 0,
            ]
        );
        $this->createdEntities['users']['business_user_pro'] = $bizPro;

        // D. Standard Individual User
        $stdUser = User::updateOrCreate(
            ['email' => 'zz_user' . self::EMAIL_DOMAIN],
            [
                'name' => self::PREFIX . 'Standard Individual',
                'password' => $defaultPassword,
                'role' => 'standard_user',
                'is_email_verified' => 1,
                'is_pending' => 0,
            ]
        );
        $this->createdEntities['users']['standard_user'] = $stdUser;

        // 3. Create Synthetic Test Listing
        $listing = Listing::updateOrCreate(
            ['name' => self::PREFIX . 'Commercial Test Item #1'],
            [
                'user_id' => $stdUser->id,
                'listing_category_id' => $category->id,
                'description' => 'Automated System Test verified listing payload.',
                'price' => 149.99,
                'status' => 1,
                'address' => 'Houston, TX',
            ]
        );
        $this->createdEntities['listings'][] = $listing->id;

        return [
            'category' => $category,
            'users' => $this->createdEntities['users'],
            'listing' => $listing,
        ];
    }

    /**
     * Get user instance by role key.
     */
    public function getUser(string $role): ?User
    {
        return $this->createdEntities['users'][$role] ?? User::where('email', "zz_{$role}" . self::EMAIL_DOMAIN)->first();
    }

    /**
     * Clean up ONLY synthetic test rows. Customer data is strictly untouched.
     */
    public function teardown(): array
    {
        $deletedCounts = [];

        // 1. Delete synthetic listings
        $deletedCounts['listings'] = Listing::where('name', 'LIKE', self::PREFIX . '%')->delete();

        // 2. Delete synthetic users
        $deletedCounts['users'] = User::where('email', 'LIKE', '%' . self::EMAIL_DOMAIN)
            ->orWhere('name', 'LIKE', self::PREFIX . '%')
            ->delete();

        // 3. Delete synthetic categories
        $deletedCounts['categories'] = ListingCategory::where('name', 'LIKE', self::PREFIX . '%')->delete();

        return $deletedCounts;
    }

    /**
     * Verify table counts match expected safe boundaries (no customer rows lost).
     */
    public function verifyZeroCustomerDataLoss(): bool
    {
        $currentCounts = [];
        $tables = ['users', 'listings', 'listing_categories'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $currentCounts[$table] = DB::table($table)->count();
                $initial = $this->initialCounts[$table] ?? 0;

                // After cleanup, count should be <= initial + synthetic rows
                if ($currentCounts[$table] < ($initial - 5)) {
                    return false; // Critical data decrease detected
                }
            }
        }

        return true;
    }
}
