<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timeslot extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'slot_code'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i'
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function getFormattedTimeAttribute()
    {
        return date('h:i A', strtotime($this->start_time)) . ' - ' . 
               date('h:i A', strtotime($this->end_time));
    }
}