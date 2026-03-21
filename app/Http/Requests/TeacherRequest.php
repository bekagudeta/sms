<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $teacherId = $this->route('teacher')?->id;

        return [
            'teacher_id' => 'required|string|unique:teachers,teacher_id,' . $teacherId,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email,' . $teacherId,
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'qualification' => 'nullable|string',
            'max_hours_per_week' => 'required|integer|min:1|max:40'
        ];
    }
}