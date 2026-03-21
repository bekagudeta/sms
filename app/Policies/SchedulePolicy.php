<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Schedule;

class SchedulePolicy
{
    public function viewAny(User $user)
    {
        return $user->can('view schedule');
    }

    public function view(User $user, Schedule $schedule)
    {
        if ($user->can('manage schedules')) {
            return true;
        }

        if ($user->can('view schedule')) {
            if ($user->hasRole('teacher')) {
                return $schedule->teacher_id === $user->teacher?->id;
            }
            // students can view any schedule for now
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        return $user->can('generate schedule');
    }

    public function update(User $user, Schedule $schedule)
    {
        return $user->can('manage schedules');
    }

    public function delete(User $user, Schedule $schedule)
    {
        return $user->can('manage schedules');
    }
}