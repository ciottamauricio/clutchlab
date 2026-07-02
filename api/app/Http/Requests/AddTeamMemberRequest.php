<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:owner,igl,player,coach'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'team.email_required',
            'email.email' => 'auth.email_invalid',
            'email.exists' => 'team.user_not_found',
            'role.required' => 'team.role_required',
            'role.in' => 'team.role_invalid',
        ];
    }
}
