<?php

namespace Database\Factories;

use App\Models\Materiel;
use App\Models\Categorie;
use App\Models\Reception;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterielFactory extends Factory
{
    protected $model = Materiel::class;

    public function definition(): array
    {
        return [
            'nom'          => fake()->randomElement(['Ordinateur Portable', 'Imprimante HP', 'Ecran Dell', 'Onduleur', 'Scanner']),
            'numero_serie' => fake()->unique()->bothify('SN-#####-????'),
            'etat'         => fake()->randomElement(['Disponible', 'Affecté', 'En Maintenance']),
            'statut'       => fake()->randomElement(['neuf', 'utilisé', 'en_panne']),
            'description'  => fake()->sentence(),

            // On lie aléatoirement aux parents déjà créés par le Seeder
            'categorie_id' => Categorie::inRandomOrder()->first()?->id ?? Categorie::factory(),
            'service_id'   => Service::inRandomOrder()->first()?->id ?? Service::factory(),
            'reception_id' => Reception::inRandomOrder()->first()?->id ?? Reception::factory(),


        ];
    }
}
