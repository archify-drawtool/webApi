<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sketch>
 */
class SketchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'canvas_state' => null,
        ];
    }

    public function withNodes(): static
    {
        return $this->state(fn () => [
            'canvas_state' => [
                'nodes' => [
                    [
                        'id' => '1',
                        'type' => 'application',
                        'position' => ['x' => 100, 'y' => 150],
                        'data' => ['label' => 'Frontend (Vue.js)'],
                    ],
                    [
                        'id' => '2',
                        'type' => 'server',
                        'position' => ['x' => 400, 'y' => 150],
                        'data' => ['label' => 'API Gateway'],
                    ],
                    [
                        'id' => '3',
                        'type' => 'server',
                        'position' => ['x' => 700, 'y' => 50],
                        'data' => ['label' => 'Auth Service'],
                    ],
                    [
                        'id' => '4',
                        'type' => 'server',
                        'position' => ['x' => 700, 'y' => 250],
                        'data' => ['label' => 'Project Service'],
                    ],
                    [
                        'id' => '5',
                        'type' => 'database',
                        'position' => ['x' => 1000, 'y' => 150],
                        'data' => ['label' => 'Database (MySQL)'],
                    ],
                ],
                'edges' => [
                    ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
                    ['id' => 'e2-3', 'source' => '2', 'target' => '3'],
                    ['id' => 'e2-4', 'source' => '2', 'target' => '4'],
                    ['id' => 'e3-5', 'source' => '3', 'target' => '5'],
                    ['id' => 'e4-5', 'source' => '4', 'target' => '5'],
                ],
            ],
        ]);
    }
}
