<?php

namespace Database\Factories;

use App\Models\InventaireDetail;
use App\Models\Inventaire;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventaireDetailFactory extends Factory
{
    protected $model = InventaireDetail::class;

    public function definition(): array
    {
        return [
            'designation'   => fake()->word() . ' ' . fake()->randomElement(['Dell', 'HP', 'Cisco']),
            'numero_serie'  => fake()->unique()->bothify('INV-###-????'),
            'etat_materiel' => fake()->randomElement(['Bon état', 'À réparer', 'Déclassé']),
            'localisation'  => fake()->randomElement(['Bureau A1', 'Salle Serveur', 'Magasin Central', 'Agence Nord']),

            // On lie au parent
            'inventaire_id' => Inventaire::inRandomOrder()->first()?->id ?? Inventaire::factory(),
        ];
    }
}
