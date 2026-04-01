<?php

namespace Database\Factories;

use App\Models\Demande;
use App\Models\Materiel;
use Illuminate\Database\Eloquent\Factories\Factory;

class DemandeFactory extends Factory
{
    protected $model = Demande::class;

    public function definition(): array
    {
        return [
            // 'numcomande' est géré par le booted(), on le laisse vide ici
            'date_demande'        => fake()->dateTimeBetween('-1 year', 'now'),
            'nbredemande'         => fake()->numberBetween(1, 5),
            'numero_serie'        => fake()->bothify('SN-####-????'),
            'categorie'           => fake()->randomElement(['Informatique', 'Mobilier', 'Réseau']),
            'service_beneficiaire'=> fake()->company(),
            'statut'              => fake()->randomElement(['En attente', 'Validée', 'Rejetée', 'Livrée']),
            'demandeur_nom'       => fake()->name(),
            'nom_materiel'        => fake()->word(),
            'description'         => fake()->sentence(),
            'scan_contrat'        => null, // ou 'contrats/test.pdf'

            // On lie la demande à un matériel existant
            'materiel_id'         => Materiel::inRandomOrder()->first()?->id ?? Materiel::factory(),
        ];
    }
}
