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
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->string('fournisseur');
            $table->string('numero_contrat');
            $table->string('scan_contrat')->nullable();
            $table->date('date_livraison');

            // Relation avec la catégorie
            $table->foreignId('categorie_id')->constrained();

            $table->integer('nbrcarton')->default(0);
            $table->integer('unite')->default(0);
            $table->integer('somme')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // CORRIGÉ : On enlève l'accent sur "receptions"
        Schema::dropIfExists('receptions');
    }
};
