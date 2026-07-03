<?php

namespace Database\Seeders\Admin;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** Create super admin */
        $admin = Admin::query()->firstOrCreate(
            ['email' => 'riosirving04@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('1234'),
            ]
        );
        $admin->forceFill(['name' => 'Super Admin'])->save();

        /** Create super admin role */
        $role = Role::query()->firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'admin',
        ]);

        $role->syncPermissions(
            Permission::query()
                ->where('guard_name', 'admin')
                ->pluck('name')
                ->all()
        );

        /** Assign Super Admin Role to Super Admin */
        $admin->syncRoles([$role]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
