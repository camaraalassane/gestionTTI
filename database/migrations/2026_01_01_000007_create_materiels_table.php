<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('materiels', function (Blueprint $table) {
        $table->id();

        // C'est ici que le lien est fait. 
        // Maintenant, pour avoir le nom, on fera $materiel->modele->nom
        $table->foreignId('modele_materiel_id')->constrained('modele_materiels')->onDelete('cascade');

        $table->string('numero_serie')->unique();
        $table->string('etat')->default('Disponible')->index();
        $table->string('statut')->default('Neuf')->index();
        $table->text('description')->nullable();

        $table->foreignId('reception_id')->constrained()->onDelete('cascade');
        $table->foreignId('categorie_id')->constrained();
        $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
        $table->unsignedBigInteger('demande_id')->nullable()->index();
        
        $table->timestamps();
        $table->index('created_at');
    });
}

    public function down(): void
    {
        Schema::dropIfExists('materiels');
    }
};
