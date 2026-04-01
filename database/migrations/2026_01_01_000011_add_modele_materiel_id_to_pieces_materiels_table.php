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
    Schema::table('pieces_materiels', function (Blueprint $table) {
        $table->foreignId('modele_materiel_id')->nullable()->constrained('modele_materiels')->onDelete('cascade');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pieces_materiels', function (Blueprint $table) {
            //
        });
    }
};
