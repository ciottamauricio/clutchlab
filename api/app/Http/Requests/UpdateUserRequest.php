<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'in:member,admin'],
            // SteamID64 is exactly 17 digits; a blank value clears the link. Unique across users
            // (a SteamID identifies one account), ignoring the user being edited.
            'steam_id' => ['sometimes', 'nullable', 'regex:/^\d{17}$/', Rule::unique('users', 'steam_id')->ignore($this->route('user'))],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'user.invalid_role',
            'steam_id.regex' => 'user.invalid_steam_id',
            'steam_id.unique' => 'user.steam_id_taken',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Treat a blank SteamID from the form as "clear it" rather than a validation failure.
        if ($this->has('steam_id') && trim((string) $this->input('steam_id')) === '') {
            $this->merge(['steam_id' => null]);
        }
    }
}
