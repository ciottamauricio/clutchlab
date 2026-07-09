<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('auth.current_password_incorrect');
                }
            }],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'auth.current_password_required',
            'password.required' => 'auth.password_required',
            'password.confirmed' => 'auth.password_mismatch',
            'password.min' => 'auth.password_too_short',
        ];
    }
}
