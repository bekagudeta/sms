<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

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
            'view schedule'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $scheduler = Role::firstOrCreate(['name' => 'scheduler']);
        $teacher = Role::firstOrCreate(['name' => 'teacher']);
        $student = Role::firstOrCreate(['name' => 'student']);

        $admin->syncPermissions(Permission::all());

        $scheduler->syncPermissions([
            'import data',
            'generate schedule',
            'export schedule',
            'view schedule'
        ]);

        $teacher->syncPermissions(['view schedule']);
        $student->syncPermissions(['view schedule']);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password')
            ]
        );

        $adminUser->assignRole('admin');
    }
}