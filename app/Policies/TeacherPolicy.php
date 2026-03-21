<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('manage teachers');
    }

    public function view(User $user, Teacher $teacher)
    {
        return $user->can('manage teachers');
    }

    public function create(User $user)
    {
        return $user->can('manage teachers');
    }

    public function update(User $user, Teacher $teacher)
    {
        return $user->can('manage teachers');
    }

    public function delete(User $user, Teacher $teacher)
    {
        return $user->can('manage teachers');
    }
}
