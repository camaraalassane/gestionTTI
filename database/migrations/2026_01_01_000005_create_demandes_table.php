<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();

            // Relation avec le matériel
            $table->foreignId('materiel_id')->constrained('materiels')->onDelete('cascade');

            // Informations matériel au moment de la demande (Dénormalisation pour l'historique)
            $table->string('nom_materiel');
            $table->string('categorie')->nullable();
            $table->string('numero_serie')->nullable()->index(); // Indexé pour recherche rapide

            // Détails de la commande
            // Note : numcomande est en string pour accepter les formats "CMD-2026-0001"
            $table->string('numcomande')->index();

            // Bénéficiaire
            $table->string('service_beneficiaire')->index();
            $table->string('demandeur_nom');
            $table->integer('nbredemande')->default(1);
            // Suivi et Statut
            $table->date('date_demande')->index();
            $table->string('statut')->default('En attente')->index(); // Index crucial pour séparer Flux/Historique
$table->string('scan_contrat')->nullable();
            // Notes
            $table->text('description')->nullable();
            $table->text('observation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
