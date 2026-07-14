<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\GameMatch> */
class GameMatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->slug(4).'.dem',
            'demo_key' => fake()->uuid().'.dem',
            'status' => 'parsed',
            'team_id' => null,
        ];
    }
}
