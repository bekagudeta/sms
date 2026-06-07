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
    /**
     * Security: Prevent accidental seeding in production
     */
    public function run(): void
    {
        // SECURITY: Never seed default users in production
        if ($this->isProduction()) {
            $this->command->warn('⚠️  Seeding is DISABLED in production environment.');
            $this->command->warn('Use manual user creation via CLI commands instead.');
            return;
        }

        $this->command->info('📋 Creating seed users...');

        // Ensure roles exist before assigning
        $roles = ['admin', 'scheduler', 'teacher', 'student'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Define seed users with better password practices
        $seedUsers = [
            [
                'email' => 'admin@example.local',
                'name' => 'System Administrator',
                'role' => 'admin',
                'defaultPassword' => 'Admin@123456', // Will be changed on first login
                'mustChange' => true, // SECURITY: Force password change
            ],
            [
                'email' => 'scheduler@example.local',
                'name' => 'Scheduler',
                'role' => 'scheduler',
                'defaultPassword' => 'Scheduler@123456',
                'mustChange' => true,
            ],
            [
                'email' => 'teacher@example.local',
                'name' => 'Test Teacher',
                'role' => 'teacher',
                'defaultPassword' => 'Teacher@123456',
                'mustChange' => true,
            ],
            [
                'email' => 'student@example.local',
                'name' => 'Test Student',
                'role' => 'student',
                'defaultPassword' => 'Student@123456',
                'mustChange' => true,
            ],
        ];

        foreach ($seedUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['defaultPassword']),
                    'must_change_password' => $userData['mustChange'],
                ]
            );
            $user->assignRole($userData['role']);

            $this->command->line("✓ Created user: {$userData['email']}");
        }

        // Create related records for teacher and student roles
        $this->createTeacherRecord();
        $this->createStudentRecord();

        $this->command->info('✅ Seed users created successfully!');
        $this->displayCredentials($seedUsers);
    }

    /**
     * Create a default teacher record for testing
     */
    private function createTeacherRecord(): void
    {
        $user = User::where('email', 'teacher@example.local')->first();
        if (!$user) return;

        $department = Department::firstOrCreate(
            ['name' => 'Computer Science'],
            [
                'code' => 'CS',
                'description' => 'Default department for system users',
            ]
        );

        Teacher::updateOrCreate(
            ['user_id' => $user->id],
            [
                'teacher_id' => 'TCH' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
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

    /**
     * Create a default student record for testing
     */
    private function createStudentRecord(): void
    {
        $user = User::where('email', 'student@example.local')->first();
        if (!$user) return;

        // Get or create default department
        $department = Department::firstOrCreate(
            ['name' => 'Computer Science'],
            [
                'code' => 'CS',
                'description' => 'Default department for system users',
            ]
        );

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

    /**
     * SECURITY: Check if running in production
     */
    private function isProduction(): bool
    {
        return app()->environment(['production', 'prod']);
    }

    /**
     * Display credentials for development (development only!)
     */
    private function displayCredentials(array $seedUsers): void
    {
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->warn('🔐 SEED CREDENTIALS (Development Only - Change on First Login!)');
        $this->command->info('═══════════════════════════════════════════════════════════');

        foreach ($seedUsers as $user) {
            $this->command->line("Email:    {$user['email']}");
            $this->command->line("Password: {$user['defaultPassword']}");
            $this->command->line("Role:     {$user['role']}");
            $this->command->line("---");
        }

        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->warn('⚠️  IMPORTANT SECURITY NOTES:');
        $this->command->line('• These credentials are ONLY for local development');
        $this->command->line('• Users MUST change password on first login');
        $this->command->line('• Never use .local domain in production');
        $this->command->line('• Never commit seed files with real credentials to git');
        $this->command->newLine();
    }
}
