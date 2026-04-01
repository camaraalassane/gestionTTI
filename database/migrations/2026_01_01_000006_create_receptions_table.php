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
    // Correction : Pas de ->after() ici, on place juste la colonne
    $table->foreignId('contrat_id')->nullable()->constrained('contrats')->onDelete('set null');
    $table->foreignId('categorie_id')->constrained(); // Relation directe

    $table->string('fournisseur')->index(); // Indexé pour filtrer par fournisseur
    $table->string('numero_contrat')->index();
    $table->string('scan_contrat')->nullable();
    $table->date('date_livraison')->index(); // Indexé pour les stats par date

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
