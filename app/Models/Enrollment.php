<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_code',
        'section_id',
        'enrolled_at',
    ];

    protected $appends = ['student_code_value'];

    public function getStudentCodeValueAttribute()
    {
        return $this->student ? $this->student->student_id : $this->student_code;
    }

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function courseOffering()
    {
        return $this->hasOneThrough(CourseOffering::class, Section::class);
    }

    public function course()
    {
        return $this->hasOneThrough(Course::class, [Section::class, CourseOffering::class]);
    }
}
