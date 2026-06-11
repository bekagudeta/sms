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
        'academic_section',
        'student_type',
        'status',
        'enrollment_date',
    ];

    protected $appends = ['full_name'];

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

    public function getFullNameAttribute()
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

    public function schedules()
    {
        // Get schedules through sections pivot table
        $sectionIds = $this->sections()->pluck('sections.id');
        return Schedule::whereIn('section_id', $sectionIds);
    }

    public function getSchedulesAttribute()
    {
        return $this->sections->load('schedule')->pluck('schedule')->flatten();
    }
}
