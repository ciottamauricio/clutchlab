<?php

namespace Database\Factories;

use App\Models\Deployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deployment> */
class DeploymentFactory extends Factory
{
    public function definition(): array
    {
        $finished = now()->subDays(fake()->numberBetween(0, 20));

        return [
            'service' => fake()->randomElement(['api', 'worker', 'web']),
            'environment' => 'production',
            'commit_sha' => fake()->sha1(),
            'commit_authored_at' => $finished->copy()->subHours(3),
            'deploy_started_at' => $finished->copy()->subMinutes(4),
            'deploy_finished_at' => $finished,
            'status' => 'success',
            'caused_failure' => false,
            'actions_run_id' => (string) fake()->unique()->numberBetween(1, 999999),
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }
}
