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
    Schema::create('inventaire_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventaire_id')->constrained()->onDelete('cascade');
    $table->string('designation');
    $table->string('numero_serie')->nullable();
    $table->string('etat_materiel');
    $table->string('localisation');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaire_details');
    }
};
