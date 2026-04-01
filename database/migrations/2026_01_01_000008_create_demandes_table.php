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
            
            // Liaisons normalisées
            $table->foreignId('materiel_id')->constrained('materiels')->onDelete('cascade');
            // Ajout de la liaison vers le catalogue pour supprimer la dépendance aux chaînes de caractères
            $table->foreignId('modele_materiel_id')->constrained('modele_materiels')->onDelete('cascade');

            // Données redondantes pour l'affichage rapide (cache)
            $table->string('nom_materiel'); 
            $table->string('categorie')->nullable();
            $table->string('numero_serie')->nullable()->index();

            // Gestion de la demande
            $table->string('numcomande')->index();
            $table->string('service_beneficiaire')->index();
            $table->string('demandeur_nom')->index();

            $table->integer('nbredemande')->default(1);
            $table->date('date_demande')->index();
            $table->string('statut')->default('En attente')->index();
            $table->string('scan_contrat')->nullable();

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