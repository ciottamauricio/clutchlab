<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingSession> */
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
