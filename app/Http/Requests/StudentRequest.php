<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $studentId = $this->route('student')?->id;

        return [
            'student_id' => 'required|string|unique:students,student_id,' . $studentId,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,' . $studentId,
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'semester' => 'required|integer|min:1|max:12',
            'enrollment_date' => 'required|date'
        ];
    }
}