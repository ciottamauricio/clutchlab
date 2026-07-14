<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Team> */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
