<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'academic_year',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $appends = ['resolved_academic_year'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean'
    ];

    public function courseOfferings()
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function getResolvedAcademicYearAttribute(): ?string
    {
        return \App\Support\AcademicYear::forSemester($this);
    }
}