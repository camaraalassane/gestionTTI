<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            // On utilise des noms de services réalistes pour ton projet TTI
            'nom' => fake()->unique()->randomElement([
                'Direction Générale',
                'Ressources Humaines',
                'Comptabilité & Finances',
                'Logistique',
                'Informatique (IT)',
                'Service Technique',
                'Communication',
                'Audit Interne',
                'Sécurité',
                'Achats'
            ]),
        ];
    }
}
