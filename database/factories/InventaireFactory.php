<?php

namespace Database\Factories;

use App\Models\Inventaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventaireFactory extends Factory
{
    protected $model = Inventaire::class;

  public function definition(): array
{
    $annee = fake()->year();

    return [
        'annee'        => $annee,
        'total_items'  => fake()->numberBetween(500, 5000),
        'user_id'      => \App\Models\User::inRandomOrder()->first()?->id ?? \App\Models\User::factory(),

        // CORRECTION ICI : On génère une date de clôture obligatoire
        // On s'assure que la date de clôture est bien dans l'année de l'inventaire
        'date_cloture' => fake()->dateTimeBetween("$annee-12-01", "$annee-12-31")->format('Y-m-d'),

        'created_at'   => now(),
        'updated_at'   => now(),
    ];
}
}
