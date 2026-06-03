<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'room_id',
        'timeslot_id',
        'status',
    ];

    protected $appends = [
        'course',
        'teacher',
        'teacher_name',
        'semester',
        'academic_year',
        'department_name',
        'year_level',
        'time_range',
        'display',
    ];

    protected $casts = [
        'section_id' => 'integer',
        'room_id' => 'integer',
        'timeslot_id' => 'integer',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function getCourseAttribute()
    {
        return $this->section?->courseOffering?->course;
    }

    public function getTeacherAttribute()
    {
        $teacher = $this->section?->teachers?->first();

        return $teacher ? $teacher->load('user') : null;
    }

    public function getTeacherNameAttribute()
    {
        $teacher = $this->teacher;

        if ($teacher) {
            if (! empty($teacher->full_name)) {
                return $teacher->full_name;
            }
            return $teacher->user?->name;
        }

        return null;
    }

    public function getSemesterAttribute()
    {
        return $this->section?->courseOffering?->semester;
    }

    public function getAcademicYearAttribute(): ?string
    {
        return \App\Support\AcademicYear::forSemester($this->semester);
    }

    public function getDepartmentNameAttribute(): ?string
    {
        $course = $this->course;

        return $course?->department?->name ?? $course?->department?->code;
    }

    public function getYearLevelAttribute(): ?string
    {
        return \App\Support\ScheduleDisplay::yearLevelForSection($this->section);
    }

    public function getTimeRangeAttribute(): ?string
    {
        return \App\Support\ScheduleDisplay::formatTimeRange(
            $this->timeslot?->start_time,
            $this->timeslot?->end_time
        );
    }

    public function getDisplayAttribute(): array
    {
        return \App\Support\ScheduleDisplay::for($this);
    }

    public function getTeachersAttribute()
    {
        return $this->section?->teachers;
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function timeslot()
    {
        return $this->belongsTo(Timeslot::class);
    }
}