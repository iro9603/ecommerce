<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            [
                'name' => 'KYC Management',
                'guard_name' => 'admin',
                'group_name' => 'KYC Management',
            ],
            [
                'name' => 'Role Management',
                'guard_name' => 'admin',
                'group_name' => 'Access Management',
            ],
            [
                'name' => 'Role User Management',
                'guard_name' => 'admin',
                'group_name' => 'Access Management',
            ],

            [
                'name' => 'Category Management',
                'guard_name' => 'admin',
                'group_name' => 'Product Category',
            ],

            [
                'name' => 'Tags Management',
                'guard_name' => 'admin',
                'group_name' => 'Product tags',
            ],

            [
                'name' => 'Brand Management',
                'guard_name' => 'admin',
                'group_name' => 'Product Brands',
            ],

            [
                'name' => 'Product Management',
                'guard_name' => 'admin',
                'group_name' => 'Products',
            ],
            [
                'name' => 'Store Auto-Approval Management',
                'guard_name' => 'admin',
                'group_name' => 'Products',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                [
                    'group_name' => $permission['group_name'],
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
