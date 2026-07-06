<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTeamPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'steam_id' => ['required', 'string', 'max:32'],
            'nickname' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'steam_id.required' => 'team.steam_id_required',
        ];
    }
}
