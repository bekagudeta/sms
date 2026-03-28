<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'department_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'level',
        'section',
        'grade',
        'status',
        'enrollment_date',
    ];

    protected $appends = ['name'];

    protected $casts = [
        'enrollment_date' => 'datetime:Y-m-d',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'enrollments');
    }

    public function getSchedulesAttribute()
    {
        return $this->sections()->with('schedule')->get()->pluck('schedule');
    }
}