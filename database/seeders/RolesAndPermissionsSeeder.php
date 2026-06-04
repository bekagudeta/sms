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
            'manage schedules', 'view schedule', 'view schedules',
            'generate schedule',
            'import data',
            'export schedule',
            'view own students',
            'export own students',
            'manage sections', 'view sections', 'create sections', 'edit sections', 'delete sections', 'import sections',
            'manage course-offerings', 'view course-offerings', 'create course-offerings', 'edit course-offerings', 'delete course-offerings', 'import course-offerings',
            'view enrollments', 'create enrollments', 'edit enrollments', 'delete enrollments', 'import enrollments',
            'view section-teachers', 'create section-teachers', 'edit section-teachers', 'delete section-teachers', 'import section-teachers',
            'create schedules', 'edit schedules', 'delete schedules',
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
            'view own students',
            'export own students',
                'manage sections', 'view sections', 'create sections', 'edit sections', 'delete sections', 'import sections',
                'manage course-offerings', 'view course-offerings', 'create course-offerings', 'edit course-offerings', 'delete course-offerings', 'import course-offerings',
            ],
            'scheduler' => self::schedulerPermissions(),
            'teacher' => [
                'view schedules',
                'view own students',
                'export own students',
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

    /**
     * Permissions for the scheduler role (timetable prep + data, no admin/system).
     *
     * @return list<string>
     */
    public static function schedulerPermissions(): array
    {
        return [
            'view schedule',
            'view schedules',
            'generate schedule',
            'manage schedules',
            'import data',
            'export schedule',
            'view students', 'create students', 'edit students', 'delete students', 'import students',
            'view teachers', 'create teachers', 'edit teachers', 'delete teachers', 'import teachers',
            'view departments', 'create departments', 'edit departments', 'delete departments', 'import departments',
            'view semesters', 'create semesters', 'edit semesters', 'delete semesters', 'import semesters',
            'view courses', 'create courses', 'edit courses', 'delete courses', 'import courses',
            'import course-offerings', 'import sections',
            'view rooms', 'create rooms', 'edit rooms', 'delete rooms', 'import rooms',
            'view timeslots', 'create timeslots', 'edit timeslots', 'delete timeslots', 'import timeslots',
            'view enrollments', 'create enrollments', 'edit enrollments', 'delete enrollments', 'import enrollments',
            'view section-teachers', 'create section-teachers', 'edit section-teachers', 'delete section-teachers', 'import section-teachers',
        ];
    }
}
