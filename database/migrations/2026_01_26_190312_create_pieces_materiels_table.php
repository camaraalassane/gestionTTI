<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('pieces_materiels', function (Blueprint $table) {
        $table->id();
        
        // 1. Liaison au matériel (ex: l'ordinateur qui contient la pièce)
        $table->foreignId('materiel_id')->constrained('materiels')->onDelete('cascade');

        // 2. Liaison à la livraison (C'est cette colonne qui va BLOQUER la modification)
        // Elle est "nullable" car au début, la pièce est en stock (pas de livraison).
        $table->foreignId('demande_id')->nullable()->constrained('demandes')->onDelete('set null');

        $table->string('nom_piece'); 
        $table->string('numero_serie')->unique()->nullable();

        // 3. L'état de la pièce
        // 'En Stock' : La pièce est disponible.
        // 'Livré' : La pièce est sortie (demande_id ne sera plus nul).
        // 'En Panne' : La pièce est défectueuse.
        $table->enum('statut', ['En Stock', 'Livré', 'En Panne'])->default('En Stock');

        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pieces_materiels');
    }
};
