<?php

namespace Database\Factories;

use App\Models\contrat;
use Illuminate\Database\Eloquent\Factories\Factory;

class contratFactory extends Factory
{
    // On lie explicitement au modèle avec son nom actuel
    protected $model = contrat::class;

    public function definition(): array
    {
        return [
            'numero_contrat' => 'CONTRAT-' . fake()->unique()->bothify('####-2026'),
            'fournisseur' => fake()->company(),
            'quantite_totale_prevue' => fake()->numberBetween(50, 500),

        ];
    }
}
