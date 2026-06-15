<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Seeds the privileged bootstrap accounts (admin, scheduler).
 *
 * SECURITY MODEL
 * --------------
 * - No password is ever hard-coded in this file (nothing ends up in git history).
 * - Credentials are read from the environment (SEED_ADMIN_*, SEED_SCHEDULER_*).
 * - If a password is NOT supplied via env, a strong random one is generated and
 *   printed to the console exactly once so the operator can capture it.
 * - Operator-supplied passwords are validated and NEVER echoed back.
 * - Every account is flagged must_change_password, so the temporary secret is
 *   only ever valid until first login.
 *
 * Demo teacher/student accounts live in DemoUserSeeder (dev/local only).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->isProduction()) {
            $this->command->warn('⚠️  Seeding is DISABLED in production.');
            $this->command->warn('Bootstrap the first admin with: php artisan create:user --role=admin');
            return;
        }

        $this->command->info('📋 Creating privileged bootstrap users...');

        // Ensure roles exist before assigning.
        foreach (['admin', 'scheduler', 'teacher', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $generated = [];

        $generated[] = $this->createPrivilegedUser(
            email: env('SEED_ADMIN_EMAIL', 'admin@example.local'),
            name: env('SEED_ADMIN_NAME', 'System Administrator'),
            role: 'admin',
            envPassword: env('SEED_ADMIN_PASSWORD'),
        );

        $generated[] = $this->createPrivilegedUser(
            email: env('SEED_SCHEDULER_EMAIL', 'scheduler@example.local'),
            name: env('SEED_SCHEDULER_NAME', 'Scheduler'),
            role: 'scheduler',
            envPassword: env('SEED_SCHEDULER_PASSWORD'),
        );

        $this->command->info('✅ Privileged users ready.');
        $this->displayGeneratedCredentials(array_filter($generated));
    }

    /**
     * Create (idempotently) a privileged user.
     *
     * @return array{email:string,role:string,password:string}|null
     *         Returns the credential row ONLY when the password was auto-generated
     *         (so it can be shown once); null when the operator supplied it via env
     *         or the user already existed.
     */
    private function createPrivilegedUser(string $email, string $name, string $role, ?string $envPassword): ?array
    {
        // Idempotent: never reset the password of an already-seeded account.
        if (User::where('email', $email)->exists()) {
            $this->command->line("• {$email} already exists — left untouched.");
            return null;
        }

        $wasGenerated = empty($envPassword);
        $password = $wasGenerated ? $this->generateStrongPassword() : $envPassword;

        if (! $wasGenerated && ! $this->passwordIsStrong($password)) {
            $this->command->error(
                "✗ SEED password for {$email} is too weak (need 12+ chars, mixed case, number, symbol). Skipped."
            );
            return null;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);
        $user->assignRole($role);

        $this->command->line("✓ Created {$role}: {$email}" . ($wasGenerated ? ' (generated password)' : ' (env password)'));

        return $wasGenerated
            ? ['email' => $email, 'role' => $role, 'password' => $password]
            : null;
    }

    private function generateStrongPassword(): string
    {
        return Str::password(16, letters: true, numbers: true, symbols: true);
    }

    private function passwordIsStrong(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]
        )->passes();
    }

    private function isProduction(): bool
    {
        return app()->environment(['production', 'prod']);
    }

    /**
     * Show auto-generated passwords once. Operator-supplied (env) passwords are
     * intentionally never printed.
     */
    private function displayGeneratedCredentials(array $rows): void
    {
        if (empty($rows)) {
            $this->command->line('No generated passwords to display (all supplied via env or already existed).');
            return;
        }

        $this->command->newLine();
        $this->command->warn('🔐 GENERATED CREDENTIALS — shown ONCE, capture them now:');
        $this->command->info('═══════════════════════════════════════════════════════════');
        foreach ($rows as $row) {
            $this->command->line("Role:     {$row['role']}");
            $this->command->line("Email:    {$row['email']}");
            $this->command->line("Password: {$row['password']}");
            $this->command->line('---');
        }
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->line('• These accounts must change their password on first login.');
        $this->command->line('• Set SEED_ADMIN_PASSWORD / SEED_SCHEDULER_PASSWORD in .env to control these.');
        $this->command->newLine();
    }
}
