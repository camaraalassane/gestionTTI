<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiels', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            // Unique est déjà un index, parfait pour la recherche de S/N
            $table->string('numero_serie')->unique();

            // On ajoute un index sur l'état car vous filtrerez souvent par 'neuf' ou 'utilisé'
            $table->string('etat')->default('Disponible')->index();
            $table->string('statut')->default('Neuf')->index();
            $table->text('description')->nullable();

            // Clés étrangères existantes
            $table->foreignId('reception_id')->constrained()->onDelete('cascade');
            $table->foreignId('categorie_id')->constrained();

            // LIAISONS SERVICES ET DEMANDES (Optimisées 2026)
            // L'index sur service_id est automatique avec constrained()
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');

            // CRUCIAL : On ajoute l'index sur demande_id pour que le dashboard calcule instantanément
$table->unsignedBigInteger('demande_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiels');
    }
};
