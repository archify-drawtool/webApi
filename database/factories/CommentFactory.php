<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Sketch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sketch_id' => Sketch::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'x' => fake()->randomFloat(2, 0, 1000),
            'y' => fake()->randomFloat(2, 0, 1000),
            'body' => fake()->sentence(),
        ];
    }
}
