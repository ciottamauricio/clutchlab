<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'player_role' => ['sometimes', 'nullable', 'in:awper,rifler,igl,entry,lurker,support,coach'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Gear — one free-text line each.
            'pc' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mouse' => ['sometimes', 'nullable', 'string', 'max:120'],
            'keyboard' => ['sometimes', 'nullable', 'string', 'max:120'],
            'headset' => ['sometimes', 'nullable', 'string', 'max:120'],
            'monitor' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mousepad' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'auth.name_required',
            'player_role.in' => 'profile.invalid_role',
            'bio.max' => 'profile.bio_too_long',
        ];
    }
}
