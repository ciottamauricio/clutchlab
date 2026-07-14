<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TrainingSession> */
class TrainingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => fake()->sentence(3),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
        ];
    }
}
