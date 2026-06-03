<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Teachers belong to sections via section_teachers.teacher_id → teachers.id.
 * The teachers table also has a business key column named teacher_id (e.g. T001).
 * Always qualify filters with teachers.id in joined relation queries.
 */
class TeacherScope
{
    public static function wherePrimaryKey(Builder $query, int $teacherId): Builder
    {
        return $query->where('teachers.id', $teacherId);
    }
}
