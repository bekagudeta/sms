<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing roles and permissions
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        // Create permissions
        $permissions = [
            'manage students',
            'manage teachers', 
            'manage courses',
            'manage departments',
            'manage semesters',
            'manage rooms',
            'manage timeslots',
            'manage schedules',
            'view schedule',
            'generate schedule',
            'import data',
            'export schedule',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            'admin' => [
                'manage students',
                'manage teachers', 
                'manage courses',
                'manage departments',
                'manage semesters',
                'manage rooms',
                'manage timeslots',
                'manage schedules',
                'view schedule',
                'generate schedule',
                'import data',
                'export schedule',
            ],
            'scheduler' => [
                'view schedule',
                'generate schedule',
                'manage schedules',
            ],
            'teacher' => [
                'view schedule',
            ],
            'student' => [
                'view schedule',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }

        $this->command->info('Roles and permissions created successfully.');
    }
}
