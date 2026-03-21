<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teacher_id',
        'room_id',
        'timeslot_id',
        'semester_id',
        'day',
        'start_time',
        'end_time',
        'section',
        'student_group',
        'max_students',
        'status'
    ];

    protected $casts = [
        'max_students' => 'integer',
        'status' => 'string'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function timeslot()
    {
        return $this->belongsTo(Timeslot::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}