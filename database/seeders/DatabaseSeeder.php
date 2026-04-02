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

        // Eerste project krijgt een schets met nodes voor canvas-testing
        Sketch::factory()->withNodes()->create([
            'title' => 'IT Landschap v1',
            'project_id' => $projects->first()->id,
            'created_by' => $user->id,
        ]);

        // Paar lege schetsen op willekeurige projecten
        Sketch::factory(3)->create([
            'project_id' => $projects->random()->id,
            'created_by' => $user->id,
        ]);
    }
}
