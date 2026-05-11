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
    private static array $sketchTitles = [
        'Systeem overzicht (high-level)',
        'Database schema v1',
        'API endpoints structuur',
        'Authenticatie flow',
        'Deployment architectuur',
        'Microservices communicatie',
        'Data flow diagram',
        'Queue en worker opzet',
        'Caching laag overzicht',
        'Third-party integraties',
        'Frontend component structuur',
        'Event flow overzicht',
        'Netwerk en beveiligingslagen',
        'Schaalbaarheidsstrategie',
        'Error handling en retry logica',
    ];

    private static array $canvasLayouts = [
        // Webapplicatie architectuur
        [
            'nodes' => [
                ['id' => '1', 'type' => 'user',        'position' => ['x' => 100, 'y' => 200], 'data' => ['label' => 'Gebruiker']],
                ['id' => '2', 'type' => 'application', 'position' => ['x' => 350, 'y' => 200], 'data' => ['label' => 'Webapp (Vue.js)']],
                ['id' => '3', 'type' => 'server',      'position' => ['x' => 650, 'y' => 200], 'data' => ['label' => 'API Server (Laravel)']],
                ['id' => '4', 'type' => 'database',    'position' => ['x' => 950, 'y' => 200], 'data' => ['label' => 'MySQL Database']],
            ],
            'edges' => [
                ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
                ['id' => 'e2-3', 'source' => '2', 'target' => '3'],
                ['id' => 'e3-4', 'source' => '3', 'target' => '4'],
            ],
        ],
        // Microservices setup
        [
            'nodes' => [
                ['id' => '1', 'type' => 'application', 'position' => ['x' => 100, 'y' => 200], 'data' => ['label' => 'Mobiele App']],
                ['id' => '2', 'type' => 'server',      'position' => ['x' => 400, 'y' => 200], 'data' => ['label' => 'API Gateway']],
                ['id' => '3', 'type' => 'server',      'position' => ['x' => 700, 'y' => 50],  'data' => ['label' => 'Auth Service']],
                ['id' => '4', 'type' => 'server',      'position' => ['x' => 700, 'y' => 200], 'data' => ['label' => 'Project Service']],
                ['id' => '5', 'type' => 'server',      'position' => ['x' => 700, 'y' => 350], 'data' => ['label' => 'Scan Service']],
                ['id' => '6', 'type' => 'database',    'position' => ['x' => 1000, 'y' => 200], 'data' => ['label' => 'PostgreSQL']],
            ],
            'edges' => [
                ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
                ['id' => 'e2-3', 'source' => '2', 'target' => '3'],
                ['id' => 'e2-4', 'source' => '2', 'target' => '4'],
                ['id' => 'e2-5', 'source' => '2', 'target' => '5'],
                ['id' => 'e4-6', 'source' => '4', 'target' => '6'],
                ['id' => 'e5-6', 'source' => '5', 'target' => '6'],
            ],
        ],
        // Simpele client-server
        [
            'nodes' => [
                ['id' => '1', 'type' => 'user',     'position' => ['x' => 100, 'y' => 150], 'data' => ['label' => 'Beheerder']],
                ['id' => '2', 'type' => 'server',   'position' => ['x' => 400, 'y' => 150], 'data' => ['label' => 'Webserver (Nginx)']],
                ['id' => '3', 'type' => 'database', 'position' => ['x' => 700, 'y' => 150], 'data' => ['label' => 'SQLite']],
            ],
            'edges' => [
                ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
                ['id' => 'e2-3', 'source' => '2', 'target' => '3'],
            ],
        ],
        // Kantoornetwerk
        [
            'nodes' => [
                ['id' => '1', 'type' => 'user',        'position' => ['x' => 100, 'y' => 50],  'data' => ['label' => 'Werkplek 1']],
                ['id' => '2', 'type' => 'user',        'position' => ['x' => 100, 'y' => 200], 'data' => ['label' => 'Werkplek 2']],
                ['id' => '3', 'type' => 'user',        'position' => ['x' => 100, 'y' => 350], 'data' => ['label' => 'Werkplek 3']],
                ['id' => '4', 'type' => 'server',      'position' => ['x' => 400, 'y' => 200], 'data' => ['label' => 'Switch']],
                ['id' => '5', 'type' => 'server',      'position' => ['x' => 700, 'y' => 100], 'data' => ['label' => 'Fileserver']],
                ['id' => '6', 'type' => 'application', 'position' => ['x' => 700, 'y' => 300], 'data' => ['label' => 'Printer']],
            ],
            'edges' => [
                ['id' => 'e1-4', 'source' => '1', 'target' => '4'],
                ['id' => 'e2-4', 'source' => '2', 'target' => '4'],
                ['id' => 'e3-4', 'source' => '3', 'target' => '4'],
                ['id' => 'e4-5', 'source' => '4', 'target' => '5'],
                ['id' => 'e4-6', 'source' => '4', 'target' => '6'],
            ],
        ],
        // CI/CD pipeline
        [
            'nodes' => [
                ['id' => '1', 'type' => 'application', 'position' => ['x' => 100, 'y' => 150], 'data' => ['label' => 'Git Repository']],
                ['id' => '2', 'type' => 'server',      'position' => ['x' => 400, 'y' => 150], 'data' => ['label' => 'CI Pipeline (GitHub Actions)']],
                ['id' => '3', 'type' => 'server',      'position' => ['x' => 700, 'y' => 50],  'data' => ['label' => 'Staging Server']],
                ['id' => '4', 'type' => 'server',      'position' => ['x' => 700, 'y' => 250], 'data' => ['label' => 'Productie Server']],
                ['id' => '5', 'type' => 'database',    'position' => ['x' => 1000, 'y' => 150], 'data' => ['label' => 'Redis Cache']],
            ],
            'edges' => [
                ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
                ['id' => 'e2-3', 'source' => '2', 'target' => '3'],
                ['id' => 'e2-4', 'source' => '2', 'target' => '4'],
                ['id' => 'e4-5', 'source' => '4', 'target' => '5'],
            ],
        ],
    ];

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(self::$sketchTitles),
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'canvas_state' => null,
        ];
    }

    public function withNodes(): static
    {
        return $this->state(fn () => [
            'canvas_state' => fake()->randomElement(self::$canvasLayouts),
        ]);
    }
}
