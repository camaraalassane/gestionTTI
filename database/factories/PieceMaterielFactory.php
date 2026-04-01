<?php

namespace Database\Factories;

use App\Models\PieceMateriel;
use App\Models\Materiel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PieceMaterielFactory extends Factory
{
    protected $model = PieceMateriel::class;

    public function definition(): array
    {
        return [
            'nom_piece'    => fake()->randomElement(['Disque Dur SSD 500Go', 'RAM 8Go', 'Batterie', 'Clavier USB']),
            'numero_serie' => fake()->unique()->bothify('PART-####-????'),

            // On utilise EXACTEMENT les valeurs de ton enum
            'statut'       => fake()->randomElement(['En Stock', 'Livré', 'En Panne']),

            'materiel_id'  => Materiel::inRandomOrder()->first()?->id ?? Materiel::factory(),
            'demande_id'   => null,
        ];
    }
}
