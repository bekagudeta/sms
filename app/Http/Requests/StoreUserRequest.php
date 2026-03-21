<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',
        ];

        if ($this->input('role') === 'student') {
            $rules['department_id'] = 'required|exists:departments,id';
            $rules['level'] = 'required|integer|min:1';
            $rules['section'] = 'required|string|max:10';
        }

        return $rules;
    }
}
