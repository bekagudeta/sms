<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Teacher;

class TeacherRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'email' => strtolower($this->email),
            'teacher_id' => trim($this->teacher_id),
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
        ]);
    }

    public function rules()
    {
        $teacherId = $this->route('teacher')?->id;

        return [
            'teacher_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new Teacher)->getTable(), 'teacher_id')->ignore($teacherId),
            ],
            'user_id' => ['required', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique((new Teacher)->getTable(), 'email')->ignore($teacherId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['required', 'exists:departments,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'max_hours_per_week' => ['required', 'integer', 'min:1', 'max:38'],
        ];
    }
}
