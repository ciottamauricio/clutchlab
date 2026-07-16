<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Optional filters for the match list. `player` narrows the list to matches where any
// player's display name contains the value (case-insensitive); `month` (YYYY-MM) and
// `day` (YYYY-MM-DD) narrow to matches played in that window (they intersect — the
// frontend keeps them in sync); `page` walks the 10-per-page pagination.
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
            'month' => ['sometimes', 'date_format:Y-m'],
            'day' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'player.string' => 'match.invalid_player_filter',
            'player.max' => 'match.invalid_player_filter',
            'month.date_format' => 'match.invalid_month',
            'day.date_format' => 'match.invalid_day',
            'page.integer' => 'match.invalid_page',
            'page.min' => 'match.invalid_page',
        ];
    }
}
