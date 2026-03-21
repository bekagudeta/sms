<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('manage students');
    }

    public function view(User $user, Student $student)
    {
        return $user->can('manage students');
    }

    public function create(User $user)
    {
        return $user->can('manage students');
    }

    public function update(User $user, Student $student)
    {
        return $user->can('manage students');
    }

    public function delete(User $user, Student $student)
    {
        return $user->can('manage students');
    }
}
