<?php

namespace Database\Factories;

use App\Models\Tactic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tactic> */
class TacticFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(2),
            'board' => ['pieces' => []],
        ];
    }
}
