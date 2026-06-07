<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'import data',
            'export schedule',
            'manage students',
            'manage teachers',
            'manage courses',
            'generate schedule',
            'view schedule',
            'view schedules',
            'manage schedules',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Ensure scheduler entity permissions exist (RolesAndPermissionsSeeder may have created them first).
        foreach (RolesAndPermissionsSeeder::schedulerPermissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $scheduler = Role::firstOrCreate(['name' => 'scheduler']);
        $teacher = Role::firstOrCreate(['name' => 'teacher']);
        $student = Role::firstOrCreate(['name' => 'student']);

        $admin->syncPermissions(Permission::all());

        $scheduler->syncPermissions(RolesAndPermissionsSeeder::schedulerPermissions());

        $teacher->syncPermissions(['view schedule']);
        $student->syncPermissions(['view schedule']);
    }
}
