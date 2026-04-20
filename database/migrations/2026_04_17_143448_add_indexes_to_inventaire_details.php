<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaire_details', function (Blueprint $table) {
            // Index composite pour accélérer les requêtes de groupement
            $table->index(['inventaire_id', 'designation'], 'idx_inventaire_details_group');
            
            // Index pour la localisation
            $table->index(['inventaire_id', 'localisation'], 'idx_inventaire_details_localisation');
        });
    }

    public function down(): void
    {
        Schema::table('inventaire_details', function (Blueprint $table) {
            $table->dropIndex('idx_inventaire_details_group');
            $table->dropIndex('idx_inventaire_details_localisation');
        });
    }
};