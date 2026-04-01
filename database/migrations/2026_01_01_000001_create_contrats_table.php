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
        Schema::create('contrats', function (Blueprint $table) {
    $table->id();
    $table->string('numero_contrat')->unique(); // HGJ7889
    $table->string('fournisseur'); // DRAMANE
    $table->integer('quantite_totale_prevue'); // 500
    $table->string('scan_contrat')->nullable(); // Chemin vers le scan du contrat
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
