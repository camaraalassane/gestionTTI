<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pieces_materiels', function (Blueprint $table) {
            $table->id();

            // 1. Liaison au matériel
            // L'index est automatique ici, crucial pour charger les pièces d'un matériel sans ramer.
            $table->foreignId('materiel_id')->constrained('materiels')->onDelete('cascade');

            // 2. Liaison à la demande (Livraison)
            // On s'assure que cet index existe pour filtrer instantanément les pièces livrées ou non.
            $table->foreignId('demande_id')->nullable()->constrained('demandes')->onDelete('set null')->index();

            // 3. Identification
            // Index sur nom_piece car tu vas souvent chercher ou trier par nom de composant (ex: RAM, Disque).
            $table->string('nom_piece')->index();
            $table->string('numero_serie')->unique()->nullable();

            // 4. Statut
            // Crucial : Indexer le statut permet de compter les pièces "En Stock" instantanément sur ton dashboard.
            $table->enum('statut', ['En Stock', 'Livré', 'En attente'])->default('En Stock')->index();

            $table->timestamps();

            // OPTIMISATION : Index sur la date de création pour les suivis chronologiques
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_materiels');
    }
};
