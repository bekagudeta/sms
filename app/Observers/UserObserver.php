<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Student;

class UserObserver
{
    public function updated(User $user)
    {
        // If user is assigned student role and has no student record, create one
        if ($user->hasRole('student') && !$user->student) {
            Student::create([
                'user_id' => $user->id,
                // You may want to set department_id, level, section, etc. here
            ]);
        }
        // If user lost student role, delete student record
        if (!$user->hasRole('student') && $user->student) {
            $user->student->delete();
        }
    }
}
