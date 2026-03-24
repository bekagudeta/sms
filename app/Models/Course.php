<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_code',
        'course_name',
        'description',
        'credits',
        'hours_per_week',
        'department_id',
        'level',
        'required_room_type',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function courseOfferings()
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function getTeacherAttribute()
    {
        $teacher = $this->courseOfferings
            ->flatMap(fn($offering) => $offering->sections)
            ->flatMap(fn($section) => $section->teachers)
            ->first();

        return $teacher?->user;
    }
}