<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
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

        // Resolve the password. Precedence:
        //   1. --password option (non-interactive / automation)
        //   2. hidden interactive prompt (never echoed, never in shell history)
        //   3. auto-generated strong password
        $password = $this->option('password');
        $wasGenerated = false;

        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('Enter password (leave blank to auto-generate)') ?: null;
            if ($password) {
                $confirm = $this->secret('Confirm password');
                if ($password !== $confirm) {
                    $this->error('Passwords do not match.');
                    return 1;
                }
            }
        }

        if (! $password) {
            $password = Str::password(16, letters: true, numbers: true, symbols: true);
            $wasGenerated = true;
        }

        // Validate password strength (skipped for auto-generated, which is strong by construction).
        if (! $wasGenerated && ! $this->isStrongPassword($password)) {
            $this->error('Password must be at least 12 characters with uppercase, lowercase, number, and symbol.');
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
                'User',
                $user->id,
                [
                    'email' => $email,
                    'role' => $role,
                    'command' => 'create:user',
                ],
                "Created {$role} account {$email} via create:user"
            );

            $this->newLine();
            $this->info('✅ User created successfully!');
            $this->info('═══════════════════════════════════════════════════════════');
            $this->line("Name:     {$name}");
            $this->line("Email:    {$email}");
            $this->line("Role:     {$role}");
            if ($wasGenerated) {
                $this->line("Password: {$password}  (auto-generated — capture it now, shown once)");
            } else {
                $this->line("Password: (set by operator — not displayed)");
            }
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
     * Validate password strength using Laravel's password rule.
     */
    private function isStrongPassword(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]
        )->passes();
    }
}
