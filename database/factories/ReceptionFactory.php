<?php

namespace Database\Factories;

use App\Models\Reception;
use App\Models\contrat;
use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceptionFactory extends Factory
{
    protected $model = Reception::class;

    public function definition(): array
{
    $montantTotal = fake()->numberBetween(500000, 10000000); // Somme globale du lot

    return [
        'fournisseur'    => fake()->company(),
        'numero_contrat' => 'CONTRAT-' . fake()->bothify('####-2026'),
        'date_livraison' => fake()->date(),
        'nbrcarton'      => fake()->numberBetween(1, 100),

        // Selon ta logique :
        'unite'          => $montantTotal, // Ici la somme globale (Integer)
        'somme'          => $montantTotal, // Initialement égal à unite, décrémenté plus tard

        'contrat_id'     => \App\Models\contrat::inRandomOrder()->first()?->id ?? \App\Models\contrat::factory(),
        'categorie_id'   => \App\Models\Categorie::inRandomOrder()->first()?->id ?? \App\Models\Categorie::factory(),
    ];
}
}
