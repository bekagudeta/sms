<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
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
            ]
        );
        $admin->assignRole('admin');

        // Scheduler user
        $scheduler = User::firstOrCreate(
            ['email' => 'scheduler@admin.com'],
            [
                'name' => 'Scheduler',
                'password' => Hash::make('password'),
            ]
        );
        $scheduler->assignRole('scheduler');

        // Teacher user
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@admin.com'],
            [
                'name' => 'Teacher',
                'password' => Hash::make('password'),
            ]
        );
        $teacher->assignRole('teacher');

        // Student user
        $student = User::firstOrCreate(
            ['email' => 'student@admin.com'],
            [
                'name' => 'Student',
                'password' => Hash::make('password'),
            ]
        );
        $student->assignRole('student');
    }
}