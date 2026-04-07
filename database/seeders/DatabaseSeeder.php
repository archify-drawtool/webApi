<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $projects = Project::factory(30)->create([
            'created_by' => $user->id,
        ]);

        $projects->each(function (Project $project) use ($user) {
            Sketch::factory(3)->withNodes()->create([
                'project_id' => $project->id,
                'created_by' => $user->id,
            ]);
        });
    }
}
