<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Student;

class UserObserver
{
    public function updated(User $user)
    {
        // CRITICAL: Only process role-related changes if roles/permissions were actually modified
        // Get the original/previous user state
        $originalRoles = $user->getOriginal() ? collect($user->getOriginal())->only(['role'])->all() : [];
        $currentRoles = $user->only(['role']);
        
        // Check if roles field actually changed
        $rolesChanged = $originalRoles !== $currentRoles;
        
        // Reload roles relationship to ensure they're properly loaded
        $user->loadMissing('roles');
        
        // If the user has student role but no student record, link or create record
        // Only do this if roles actually changed or if we're explicitly assigning student role
        if ($rolesChanged && $user->hasRole('student') && !$user->student) {
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                Student::create([
                    'user_id' => $user->id,
                    'department_id' => null, // Will be set later
                    'level' => 'undergraduate', // Default level
                ]);
            }
        }

        // CRITICAL FIX: Only delete student record if user ACTUALLY lost the student role in THIS update
        // AND the student record exists
        // This prevents accidental deletion during password changes or other updates
        if ($rolesChanged && !$user->hasRole('student') && $user->student) {
            $user->student->delete();
        }
    }
}
