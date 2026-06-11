<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Support\TimeslotDuration;

class Timeslot extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'slot_code',
        'student_type'
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function getDurationMinutesAttribute(): int
    {
        return TimeslotDuration::minutes($this);
    }

    public function getTeachingHoursAttribute(): float
    {
        return TimeslotDuration::teachingHours($this);
    }

    public function getFormattedTimeAttribute()
    {
        return date('h:i A', strtotime($this->start_time)) . ' - ' . 
               date('h:i A', strtotime($this->end_time));
    }
}