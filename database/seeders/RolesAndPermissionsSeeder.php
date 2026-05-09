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
            'manage students', 'view students', 'create students', 'edit students', 'delete students', 'import students',
            'manage teachers', 'view teachers', 'create teachers', 'edit teachers', 'delete teachers', 'import teachers',
            'manage courses', 'view courses', 'create courses', 'edit courses', 'delete courses', 'import courses',
            'manage departments', 'view departments', 'create departments', 'edit departments', 'delete departments', 'import departments',
            'manage semesters', 'view semesters', 'create semesters', 'edit semesters', 'delete semesters', 'import semesters',
            'manage rooms', 'view rooms', 'create rooms', 'edit rooms', 'delete rooms', 'import rooms',
            'manage timeslots', 'view timeslots', 'create timeslots', 'edit timeslots', 'delete timeslots', 'import timeslots',
            'manage schedules', 'view schedules',
            'generate schedule',
            'import data',
            'export schedule',
            'manage sections', 'view sections', 'create sections', 'edit sections', 'delete sections', 'import sections',
            'manage course-offerings', 'view course-offerings', 'create course-offerings', 'edit course-offerings', 'delete course-offerings', 'import course-offerings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'admin' => [
                'manage students', 'view students', 'create students', 'edit students', 'delete students', 'import students',
                'manage teachers', 'view teachers', 'create teachers', 'edit teachers', 'delete teachers', 'import teachers',
                'manage courses', 'view courses', 'create courses', 'edit courses', 'delete courses', 'import courses',
                'manage departments', 'view departments', 'create departments', 'edit departments', 'delete departments', 'import departments',
                'manage semesters', 'view semesters', 'create semesters', 'edit semesters', 'delete semesters', 'import semesters',
                'manage rooms', 'view rooms', 'create rooms', 'edit rooms', 'delete rooms', 'import rooms',
                'manage timeslots', 'view timeslots', 'create timeslots', 'edit timeslots', 'delete timeslots', 'import timeslots',
                'manage schedules', 'view schedules',
                'generate schedule',
                'import data',
                'export schedule',
                'manage sections', 'view sections', 'create sections', 'edit sections', 'delete sections', 'import sections',
                'manage course-offerings', 'view course-offerings', 'create course-offerings', 'edit course-offerings', 'delete course-offerings', 'import course-offerings',
            ],
            'scheduler' => [
                'view schedules',
                'generate schedule',
                'manage schedules',
                'view students', 'view teachers', 'view courses', 'view rooms', 'view timeslots',
            ],
            'teacher' => [
                'view schedules',
            ],
            'student' => [
                'view schedules',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }


        
        $this->command->info('Roles and permissions created successfully.');
    }
}
