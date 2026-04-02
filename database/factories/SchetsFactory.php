<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Schets;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schets>
 */
class SchetsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
        ];
    }
}
