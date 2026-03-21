<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $courseId = $this->route('course')?->id;

        return [
            'course_code' => 'required|string|unique:courses,course_code,' . $courseId,
            'course_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'credits' => 'required|integer|min:1|max:6',
            'hours_per_week' => 'required|integer|min:1|max:6',
            'department_id' => 'required|exists:departments,id',
            'semester_id' => 'required|exists:semesters,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'level' => 'required|in:undergraduate,graduate,diploma'
        ];
    }
}