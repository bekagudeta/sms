<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist before assigning
        $roles = ['admin', 'scheduler', 'teacher', 'student'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'must_change_password' => false,
                'plain_password' => 'password',
            ]
        );
        $admin->assignRole('admin');

        // Scheduler user
        $scheduler = User::firstOrCreate(
            ['email' => 'scheduler@example.com'],
            [
                'name' => 'Scheduler',
                'password' => Hash::make('password'),
                'must_change_password' => false,
                'plain_password' => 'password',
            ]
        );
        $scheduler->assignRole('scheduler');

        // Teacher user
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'Teacher',
                'password' => Hash::make('password'),
                'must_change_password' => false,
                'plain_password' => 'password',
            ]
        );
        $teacher->assignRole('teacher');

        // Student user
        $student = User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Student',
                'password' => Hash::make('password'),
                'must_change_password' => false,
                'plain_password' => 'password',
            ]
        );
        $student->assignRole('student');

        // Ensure this login user is linked to Teacher and Student records for schedule lookup
        // Create a default department if none exists
        $department = Department::firstOrCreate(
            ['name' => 'Computer Science'],
            [
                'code' => 'CS',
                'description' => 'Default department for system users',
            ]
        );

        Teacher::updateOrCreate(
            ['user_id' => $teacher->id],
            [
                'teacher_id' => 'TCH' . str_pad($teacher->id, 6, '0', STR_PAD_LEFT),
                'first_name' => 'Teacher',
                'last_name' => 'User',
                'email' => $teacher->email,
                'department_id' => $department->id,
                'phone' => '000-000-0000',
                'qualification' => 'N/A',
                'max_hours_per_week' => 20,
            ]
        );

        Student::firstOrCreate(
            ['user_id' => $student->id],
            [
                'student_id' => 'STU999',
                'first_name' => 'System',
                'last_name' => 'Student',
                'email' => $student->email,
                'department_id' => $department->id,
                'level' => 'Level 1',
                'academic_section' => 'SE-3A',
                'enrollment_date' => now()->toDateString(),
            ]
        );
    }
}