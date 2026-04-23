<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Student;

class StudentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'email' => strtolower($this->email),
            'student_id' => trim($this->student_id),
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
        ]);
    }

    public function rules()
    {
        $studentId = $this->route('student')?->id;

        return [
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new Student)->getTable(), 'student_id')->ignore($studentId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique((new Student)->getTable(), 'email')->ignore($studentId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['required', 'exists:departments,id'],
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'enrollment_date' => ['required', 'date'],
        ];
    }
}