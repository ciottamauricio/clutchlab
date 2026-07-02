<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'auth.name_required',
            'email.required' => 'auth.email_required',
            'email.email' => 'auth.email_invalid',
            'email.unique' => 'auth.email_taken',
            'password.required' => 'auth.password_required',
            'password.confirmed' => 'auth.password_mismatch',
            'password.min' => 'auth.password_too_short',
        ];
    }
}
