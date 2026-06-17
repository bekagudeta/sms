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
        'specialization',
        'max_hours_per_week',
    ];

    protected $appends = ['full_name', 'section_ids'];

    protected $casts = [
        'max_hours_per_week' => 'integer'
    ];

    public function getSectionIdsAttribute()
    {
        return $this->sections()->pluck('sections.id')->all();
    }

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
        return $this->sections->load('schedule')->pluck('schedule')->flatten();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
