<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateUserCommand extends Command
{
    protected $signature = 'create:user
                            {--name= : The name of the user}
                            {--email= : The email address}
                            {--role=admin : The role (admin, scheduler, teacher, student)}
                            {--password= : Override generated password}';

    protected $description = 'Create a new user securely (production-safe)';

    public function handle()
    {
        // Validate environment (warn if production)
        if (app()->environment('production')) {
            if (!$this->confirm('⚠️  You are in PRODUCTION. Continue?')) {
                $this->error('Aborted.');
                return 1;
            }
        }

        // Get input with validation
        $name = $this->option('name') ?? $this->ask('Enter user name');
        $email = $this->option('email') ?? $this->ask('Enter email address');
        $role = $this->option('role') ?? $this->ask('Enter role (admin/scheduler/teacher/student)', 'admin');

        // Validate role
        if (!Role::where('name', $role)->exists()) {
            $this->error("Role '{$role}' does not exist. Available roles: admin, scheduler, teacher, student");
            return 1;
        }

        // Check if user exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");
            return 1;
        }

        // Generate or use provided password
        $password = $this->option('password') ?? Str::random(16) . '@Pwd123';

        // Validate password strength if provided
        if (!$this->isStrongPassword($password)) {
            $this->error('Password must be at least 8 characters with uppercase, lowercase, number, and symbol.');
            return 1;
        }

        try {
            // Create user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'must_change_password' => true, // Force password change on first login
            ]);

            // Assign role
            $user->assignRole($role);

            // Log audit entry
            \App\Services\AuditLogService::log(
                'User created via CLI',
                'user_created',
                [
                    'user_id' => $user->id,
                    'email' => $email,
                    'role' => $role,
                    'command' => 'create:user',
                ]
            );

            $this->newLine();
            $this->info('✅ User created successfully!');
            $this->info('═══════════════════════════════════════════════════════════');
            $this->line("Name:     {$name}");
            $this->line("Email:    {$email}");
            $this->line("Role:     {$role}");
            $this->line("Password: {$password}");
            $this->info('═══════════════════════════════════════════════════════════');
            $this->warn('⚠️  IMPORTANT:');
            $this->line('• User MUST change password on first login');
            $this->line('• Password change is enforced');
            $this->line('• Action is logged in audit trail');
            if (app()->environment('production')) {
                $this->warn('• In production, send password securely (email, secure delivery)');
            }
            $this->newLine();

            return 0;

        } catch (\Exception $e) {
            $this->error('Error creating user: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Validate password strength
     */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[!@#$%^&*]/', $password);
    }
}
