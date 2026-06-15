<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Demo (non-privileged) accounts + linked Teacher/Student records for local
 * development and manual testing only.
 *
 * SECURITY: This seeder refuses to run outside local/testing environments, so
 * sample teacher/student logins can never reach staging or production. Passwords
 * are auto-generated and printed once — nothing is hard-coded.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('⚠️  DemoUserSeeder only runs in local/testing — skipped.');
            return;
        }

        foreach (['teacher', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $generated = [];
        $generated[] = $this->createDemoUser('teacher@example.local', 'Test Teacher', 'teacher');
        $generated[] = $this->createDemoUser('student@example.local', 'Test Student', 'student');

        $this->createTeacherRecord();
        $this->createStudentRecord();

        $this->displayGeneratedCredentials(array_filter($generated));
    }

    /**
     * @return array{email:string,role:string,password:string}|null
     */
    private function createDemoUser(string $email, string $name, string $role): ?array
    {
        if (User::where('email', $email)->exists()) {
            $this->command->line("• {$email} already exists — left untouched.");
            return null;
        }

        $password = Str::password(16, letters: true, numbers: true, symbols: true);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);
        $user->assignRole($role);

        $this->command->line("✓ Created demo {$role}: {$email}");

        return ['email' => $email, 'role' => $role, 'password' => $password];
    }

    private function createTeacherRecord(): void
    {
        $user = User::where('email', 'teacher@example.local')->first();
        if (! $user) {
            return;
        }

        $department = $this->defaultDepartment();

        Teacher::updateOrCreate(
            ['user_id' => $user->id],
            [
                'teacher_id' => 'TCH' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Teacher',
                'email' => $user->email,
                'department_id' => $department->id,
                'phone' => '000-000-0000',
                'qualification' => 'N/A',
                'max_hours_per_week' => 38,
            ]
        );
    }

    private function createStudentRecord(): void
    {
        $user = User::where('email', 'student@example.local')->first();
        if (! $user) {
            return;
        }

        $department = $this->defaultDepartment();

        Student::firstOrCreate(
            ['user_id' => $user->id],
            [
                'student_id' => 'STU999',
                'first_name' => 'Test',
                'last_name' => 'Student',
                'email' => $user->email,
                'phone' => '000-000-0000',
                'department_id' => $department->id,
                'level' => 'Level 1',
                'academic_section' => 'SE-3A',
                'status' => 'active',
                'enrollment_date' => now()->toDateString(),
            ]
        );
    }

    private function defaultDepartment(): Department
    {
        return Department::firstOrCreate(
            ['name' => 'Computer Science'],
            [
                'code' => 'CS',
                'description' => 'Default department for system users',
            ]
        );
    }

    private function displayGeneratedCredentials(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $this->command->newLine();
        $this->command->warn('🔐 DEMO CREDENTIALS (local only) — shown ONCE:');
        foreach ($rows as $row) {
            $this->command->line("{$row['role']}: {$row['email']} / {$row['password']}");
        }
        $this->command->newLine();
    }
}
