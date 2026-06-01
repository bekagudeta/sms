<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Student;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class FixStudentUserIntegrity extends Command
{
    protected $signature = 'fix:student-user-integrity';
    protected $description = 'Ensure every user with student role has a corresponding students table record.';

    public function handle()
    {
        $studentRole = Role::where('name', 'student')->first();
        if (!$studentRole) {
            $this->error('No student role found.');
            return 1;
        }

        $invalidUsers = User::role('student')
            ->whereDoesntHave('student')
            ->get();

        if ($invalidUsers->isEmpty()) {
            $this->info('All student users have corresponding students records.');
            return 0;
        }

        $this->warn('Found ' . $invalidUsers->count() . ' users with student role but no students record.');
        if ($this->confirm('Do you want to create missing students records for them now?')) {
            foreach ($invalidUsers as $user) {
                // You may want to prompt for department/level/section, or set defaults
                Student::create([
                    'user_id' => $user->id,
                    'department_id' => null, // Set to a default or prompt
                    'level' => null,         // Set to a default or prompt
                    'academic_section' => 'Unassigned',
                ]);
                $this->info("Created students record for user ID {$user->id} ({$user->name})");
            }
        } else if ($this->confirm('Do you want to remove the student role from these users instead?')) {
            foreach ($invalidUsers as $user) {
                $user->removeRole('student');
                $this->info("Removed student role from user ID {$user->id} ({$user->name})");
            }
        } else {
            $this->info('No changes made.');
        }
        return 0;
    }
}
