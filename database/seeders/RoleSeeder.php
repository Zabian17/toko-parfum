<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * All available menu permission slugs.
     * Must match navItem IDs in admin-auth.js.
     */
    public const ALL_PERMISSIONS = [
        'dashboard', 'products', 'categories', 'tiers', 'orders',
        'customers', 'stock', 'cashier', 'reports', 'settings',
        'users', 'roles',
    ];

    public function run(): void
    {
        // Super Admin — full access, bypasses permission checks
        Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name'        => 'Super Admin',
                'permissions' => self::ALL_PERMISSIONS,
                'is_super'    => true,
            ]
        );

        // Admin Toko — limited operational access
        Role::firstOrCreate(
            ['slug' => 'admin_toko'],
            [
                'name'        => 'Admin Toko',
                'permissions' => ['dashboard', 'products', 'categories', 'orders', 'customers', 'stock', 'cashier', 'reports'],
                'is_super'    => false,
            ]
        );
    }
}
