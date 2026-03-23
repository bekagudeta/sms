<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'user_id',
        'department_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'qualification',
        'max_hours_per_week',
    ];

    protected $appends = ['full_name'];

    protected $casts = [
        'max_hours_per_week' => 'integer'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'section_teachers');
    }

    public function getSchedulesAttribute()
    {
        return $this->sections()->with('schedule')->get()->pluck('schedule');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute()
    {
        return $this->user?->name ?? 'Unknown';
    }
}