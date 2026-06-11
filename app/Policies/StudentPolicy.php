<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * Authorization policy for student model operations.
 * Defines what actions users can perform on students based on their role and permissions.
 */
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

    /**
     * Determine if user can modify student type (weekend/regular)
     * This is a sensitive operation requiring explicit permission
     */
    public function modifyStudentType(User $user, Student $student): bool
    {
        return $user->can('modify student types');
    }

    /**
     * Determine if user can perform bulk student type changes
     */
    public function bulkModifyStudentTypes(User $user): bool
    {
        return $user->can('bulk modify student types');
    }

    /**
     * Determine if user can view student type information
     */
    public function viewStudentType(User $user, Student $student): bool
    {
        return $user->can('manage students');
    }

    /**
     * Determine if user can view student schedules
     */
    public function viewSchedule(User $user, Student $student): bool
    {
        return $user->can('view student schedules');
    }

    /**
     * Determine if user can manage schedule types for students
     */
    public function manageScheduleType(User $user): bool
    {
        return $user->can('manage schedule types');
    }
}
