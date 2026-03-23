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
        // Create permissions and roles in an idempotent manner
        // Older seed data may remain from partial runs. Avoid duplicates by using firstOrCreate.

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
            Permission::firstOrCreate(['name' => $permission]);
        }

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
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
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
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }

        $this->command->info('Roles and permissions created successfully.');
    }
}
