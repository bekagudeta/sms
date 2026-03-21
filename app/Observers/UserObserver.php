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
            $student = Student::where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->first();

            if ($student) {
                $student->update(['user_id' => $user->id]);
            } else {
                Student::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'first_name' => explode(' ', trim($user->name))[0] ?? null,
                    'last_name' => str_contains($user->name, ' ') ? trim(substr($user->name, strpos($user->name, ' ') + 1)) : null,
                    'semester' => 1,
                    'section' => 'A',
                ]);
            }
        }

        // If user lost student role, delete student record
        if (!$user->hasRole('student') && $user->student) {
            $user->student->delete();
        }
    }
}
