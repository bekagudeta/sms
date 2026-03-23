<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'must_change_password' => false,
                'plain_password' => null,
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
                'plain_password' => null,
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
                'plain_password' => null,
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
                'plain_password' => null,
            ]
        );
        $student->assignRole('student');

        // Ensure this login user is linked to a Student record for schedule lookup
        $studentProfile = Student::firstOrCreate(
            ['email' => $student->email],
            [
                'student_id' => 'STU999',
                'user_id' => $student->id,
                'first_name' => 'System',
                'last_name' => 'Student',
                'department_id' => Department::first()?->id,
                'semester' => 1,
                'level' => 'undergraduate',
                'section' => 'A',
                'enrollment_date' => now()->toDateString(),
            ]
        );
        if (!$studentProfile->user_id) {
            $studentProfile->user_id = $student->id;
            $studentProfile->save();
        }
    }
}