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
       Schema::create('inventaires', function (Blueprint $table) {
    $table->id();
    $table->string('annee')->unique(); // ex: "2025"
    $table->date('date_cloture');
    $table->integer('total_items');
    $table->foreignId('user_id')->constrained(); // Responsable de la clôture
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaires');
    }
};
