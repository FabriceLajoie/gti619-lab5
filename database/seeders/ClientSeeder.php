<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clients résidentiels
        $residentialClients = [
            ['first_name' => 'Jean', 'last_name' => 'Dupont', 'type' => 'residential', 'email' => 'jean.dupont@email.com', 'phone' => '514-123-4567', 'address' => '123 Rue Principal, Montréal, QC'],
            ['first_name' => 'Marie', 'last_name' => 'Martin', 'type' => 'residential', 'email' => 'marie.martin@email.com', 'phone' => '514-234-5678', 'address' => '456 Avenue Secondaire, Montréal, QC'],
            ['first_name' => 'Pierre', 'last_name' => 'Bernard', 'type' => 'residential', 'email' => 'pierre.bernard@email.com', 'phone' => '514-345-6789', 'address' => '789 Boulevard Tertaire, Montréal, QC'],
            ['first_name' => 'Sophie', 'last_name' => 'Leclerc', 'type' => 'residential', 'email' => 'sophie.leclerc@email.com', 'phone' => '514-456-7890', 'address' => '321 Rue Quatrième, Montréal, QC'],
            ['first_name' => 'Michel', 'last_name' => 'Gauthier', 'type' => 'residential', 'email' => 'michel.gauthier@email.com', 'phone' => '514-567-8901', 'address' => '654 Avenue Cinquième, Montréal, QC'],
            ['first_name' => 'Nathalie', 'last_name' => 'Thibault', 'type' => 'residential', 'email' => 'nathalie.thibault@email.com', 'phone' => '514-678-9012', 'address' => '987 Boulevard Sixième, Montréal, QC'],
            ['first_name' => 'Jacques', 'last_name' => 'Roy', 'type' => 'residential', 'email' => 'jacques.roy@email.com', 'phone' => '514-789-0123', 'address' => '111 Rue Septième, Montréal, QC'],
            ['first_name' => 'Isabelle', 'last_name' => 'Côté', 'type' => 'residential', 'email' => 'isabelle.cote@email.com', 'phone' => '514-890-1234', 'address' => '222 Avenue Huitième, Montréal, QC'],
            ['first_name' => 'Philippe', 'last_name' => 'Gagnon', 'type' => 'residential', 'email' => 'philippe.gagnon@email.com', 'phone' => '514-901-2345', 'address' => '333 Boulevard Neuvième, Montréal, QC'],
            ['first_name' => 'Chantal', 'last_name' => 'Dion', 'type' => 'residential', 'email' => 'chantal.dion@email.com', 'phone' => '514-012-3456', 'address' => '444 Rue Dixième, Montréal, QC'],
        ];

        // Clients d'affaires
        $businessClients = [
            ['first_name' => 'Acme', 'last_name' => 'Corporation', 'type' => 'business', 'email' => 'info@acmecorp.com', 'phone' => '514-111-1111', 'address' => '1000 Rue Affaires, Montréal, QC'],
            ['first_name' => 'TechSoft', 'last_name' => 'Solutions', 'type' => 'business', 'email' => 'contact@techsoft.com', 'phone' => '514-222-2222', 'address' => '2000 Boulevard Technologie, Montréal, QC'],
            ['first_name' => 'Global', 'last_name' => 'Consulting', 'type' => 'business', 'email' => 'hello@globalconsulting.com', 'phone' => '514-333-3333', 'address' => '3000 Avenue Conseil, Montréal, QC'],
            ['first_name' => 'Digital', 'last_name' => 'Services Inc', 'type' => 'business', 'email' => 'support@digitalservices.com', 'phone' => '514-444-4444', 'address' => '4000 Rue Numérique, Montréal, QC'],
            ['first_name' => 'Innovate', 'last_name' => 'Ltd', 'type' => 'business', 'email' => 'sales@innovateltd.com', 'phone' => '514-555-5555', 'address' => '5000 Boulevard Innovation, Montréal, QC'],
            ['first_name' => 'ProBuild', 'last_name' => 'Construction', 'type' => 'business', 'email' => 'office@probuild.com', 'phone' => '514-666-6666', 'address' => '6000 Avenue Construction, Montréal, QC'],
            ['first_name' => 'EuroTrade', 'last_name' => 'Import Export', 'type' => 'business', 'email' => 'shipping@eurotrade.com', 'phone' => '514-777-7777', 'address' => '7000 Rue Commerce, Montréal, QC'],
            ['first_name' => 'HealthCare', 'last_name' => 'Plus', 'type' => 'business', 'email' => 'appointments@healthcareplus.com', 'phone' => '514-888-8888', 'address' => '8000 Boulevard Santé, Montréal, QC'],
            ['first_name' => 'Green', 'last_name' => 'Energy Corp', 'type' => 'business', 'email' => 'info@greenenergy.com', 'phone' => '514-999-9999', 'address' => '9000 Avenue Durable, Montréal, QC'],
            ['first_name' => 'Future', 'last_name' => 'Ventures', 'type' => 'business', 'email' => 'invest@futureventures.com', 'phone' => '514-000-0000', 'address' => '10000 Rue Avenir, Montréal, QC'],
        ];

        foreach (array_merge($residentialClients, $businessClients) as $client) {
            Client::create($client);
        }
    }
}
