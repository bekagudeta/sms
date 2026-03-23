<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Student;

class UserObserver
{
    public function updated(User $user)
    {
        // If the user has student role but no student record, link or create record
        if ($user->hasRole('student') && !$user->student) {
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                Student::create([
                    'user_id' => $user->id,
                    'department_id' => null, // Will be set later
                    'level' => 'undergraduate', // Default level
                ]);
            }
        }

        // If user lost student role, delete student record
        if (!$user->hasRole('student') && $user->student) {
            $user->student->delete();
        }
    }
}
