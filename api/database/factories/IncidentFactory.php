<?php

namespace Database\Factories;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service' => 'api',
            'deployment_id' => null,
            'opened_at' => now()->subDays(2),
            'resolved_at' => null,
            'description' => fake()->sentence(),
        ];
    }
}
