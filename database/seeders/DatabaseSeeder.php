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
        // Hoofd testaccount — bewust bewaard voor development
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Extra realistische gebruikers
        $users = collect([
            User::factory()->create([
                'name' => 'Lars van den Berg',
                'email' => 'lars.vandenberg@bouwbedrijf.nl',
                'password' => bcrypt('password'),
            ]),
            User::factory()->create([
                'name' => 'Noor Janssen',
                'email' => 'n.janssen@architectenbureau.nl',
                'password' => bcrypt('password'),
            ]),
            User::factory()->create([
                'name' => 'Daan de Vries',
                'email' => 'daan.devries@interieurburo.nl',
                'password' => bcrypt('password'),
            ]),
        ]);

        // Projecten voor het testaccount
        $testProjects = Project::factory(15)->create([
            'created_by' => $testUser->id,
        ]);

        $testProjects->each(function (Project $project) use ($testUser) {
            Sketch::factory(3)->withNodes()->create([
                'project_id' => $project->id,
                'created_by' => $testUser->id,
            ]);
        });

        // Projecten voor de extra gebruikers
        $users->each(function (User $user) {
            $projects = Project::factory(fake()->numberBetween(3, 8))->create([
                'created_by' => $user->id,
            ]);

            $projects->each(function (Project $project) use ($user) {
                Sketch::factory(fake()->numberBetween(1, 4))->withNodes()->create([
                    'project_id' => $project->id,
                    'created_by' => $user->id,
                ]);
            });
        });
    }
}
