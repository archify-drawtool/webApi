<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $titles = [
            'E-commerce platform redesign',
            'Microservices migratie monoliet',
            'Real-time notificatiesysteem',
            'Multi-tenant SaaS backend',
            'API gateway implementatie',
            'Event-driven orderverwerking',
            'SSO integratie met OAuth2',
            'Data pipeline voor analytics',
            'Mobile backend (BFF patroon)',
            'Betalingssysteem integratie',
            'Zoekinfrastructuur met Elasticsearch',
            'CI/CD pipeline herstructurering',
            'Logging en monitoring stack',
            'Content delivery netwerk opzet',
            'Chatfunctionaliteit met WebSockets',
            'Document scanverwerking pipeline',
            'Rolgebaseerde toegangscontrole',
            'Async taakverwerking met queues',
            'Caching strategie Redis',
            'GraphQL API voor webapp',
        ];

        return [
            'title' => fake()->randomElement($titles),
            'created_by' => User::factory(),
        ];
    }
}
