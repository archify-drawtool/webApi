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
            'Klantportaal backend',
            'Interne HR-tool API',
            'Voorraadbeheersysteem',
            'Facturatiemodule integratie',
            'Projectplanning API',
            'Digitale handtekening service',
            'Exportmodule rapportages',
            'Aanmeldsysteem evenementen',
            'Reserveringssysteem API',
            'Contractbeheer backend',
            'Koppeling met boekhoudpakket',
            'Productcatalogus service',
            'Klachtenregistratie systeem',
            'Onderhoudstaken scheduler',
            'Communicatieplatform backend',
            'Certificaatbeheer service',
            'Auditing en logging module',
            'Tweefactorauthenticatie integratie',
            'Bulk e-mail verzendservice',
            'Geografische data API',
            'Sensor data ingest pipeline',
            'Machine learning inferentie API',
            'A/B test framework backend',
            'Webhooks distributieservice',
            'Toegangspassen integratie',
            'Digitaal archief backend',
            'Ticketbeheer API',
            'Enquêteplatform service',
            'Leveranciersbeheer API',
            'Tijdregistratie backend',
        ];

        return [
            'title' => fake()->unique()->randomElement($titles),
            'created_by' => User::factory(),
        ];
    }
}
