<?php

namespace Database\Factories;

use App\Models\ParseEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ParseEvent> */
class ParseEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => fake()->numberBetween(1, 1000),
            'status' => 'success',
            'duration_ms' => fake()->numberBetween(1000, 60000),
            'error_code' => null,
        ];
    }
}
