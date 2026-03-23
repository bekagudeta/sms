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
        'teacher_name',
        'semester'
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
        return $this->section?->teachers?->first();
    }

    public function getTeacherNameAttribute()
    {
        $teacher = $this->teacher;

        if ($teacher) {
            if (!empty($teacher->full_name)) {
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