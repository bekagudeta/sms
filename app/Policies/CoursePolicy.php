<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user)
    {
        return $user->can('manage courses');
    }

    public function view(User $user, Course $course)
    {
        return $user->can('manage courses');
    }

    public function create(User $user)
    {
        return $user->can('manage courses');
    }

    public function update(User $user, Course $course)
    {
        return $user->can('manage courses');
    }

    public function delete(User $user, Course $course)
    {
        return $user->can('manage courses');
    }
}
