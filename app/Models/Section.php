<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'section_name',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    protected $appends = ['enrolled_count', 'course_name'];

    protected $with = ['courseOffering.course'];

    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            CourseOffering::class,
            'id', // Foreign key on course_offerings table
            'id', // Foreign key on courses table
            'course_offering_id', // Local key on sections table
            'course_id' // Local key on course_offerings table
        );
    }

    public function semester()
    {
        return $this->hasOneThrough(
            Semester::class,
            CourseOffering::class,
            'id', // Foreign key on course_offerings table
            'id', // Foreign key on semesters table
            'course_offering_id', // Local key on sections table
            'semester_id' // Local key on course_offerings table
        );
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'section_teachers');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments');
    }

    public function schedule()
    {
        return $this->hasOne(Schedule::class);
    }

    public function getEnrolledCountAttribute()
    {
        return $this->enrollments()->count();
    }

    public function getCourseNameAttribute()
    {
        return $this->courseOffering?->course?->course_name ?? $this->course?->course_name ?? null;
    }

    public function getAvailableSeatsAttribute()
    {
        return $this->capacity - $this->getEnrolledCountAttribute();
    }
}
