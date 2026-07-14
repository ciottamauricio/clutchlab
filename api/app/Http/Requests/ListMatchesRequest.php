<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Optional filters for the match list. `player` narrows the list to matches where any
// player's display name contains the value (case-insensitive).
class ListMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'player.string' => 'match.invalid_player_filter',
            'player.max' => 'match.invalid_player_filter',
        ];
    }
}
