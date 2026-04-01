<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategorieFactory extends Factory
{
    protected $model = Categorie::class;

    public function definition(): array
    {
        return [
            // Utilise unique() pour éviter les doublons si tu as une contrainte UNIQUE en base
            'nom' => fake()->unique()->randomElement([
                'Informatique', 'Mobilier de bureau', 'Réseau & Télécom',
                'Consommables', 'Matériel de sécurité', 'Électroménager',
                'Véhicules', 'Outillage', 'Papeterie', 'Logiciels'
            ]),
        ];
    }
}
