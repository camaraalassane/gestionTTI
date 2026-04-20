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
        // Index pour la table receptions
        Schema::table('receptions', function (Blueprint $table) {
            $table->index('created_at', 'idx_receptions_created_at');
            $table->index('numero_contrat', 'idx_receptions_numero_contrat');
            $table->index('date_livraison', 'idx_receptions_date_livraison');
            $table->index(['numero_contrat', 'date_livraison'], 'idx_receptions_contrat_date');
        });

        // Index pour la table materiels
        Schema::table('materiels', function (Blueprint $table) {
            $table->index('modele_materiel_id', 'idx_materiels_modele_id');
            $table->index('demande_id', 'idx_materiels_demande_id');
            $table->index('reception_id', 'idx_materiels_reception_id');
            $table->index(['reception_id', 'modele_materiel_id'], 'idx_materiels_reception_modele');
            $table->index(['modele_materiel_id', 'demande_id'], 'idx_materiels_modele_demande');
        });

        // Index pour la table modele_materiels
        Schema::table('modele_materiels', function (Blueprint $table) {
            $table->index('nom', 'idx_modele_nom');
            $table->index('categorie_id', 'idx_modele_categorie_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les indexes de la table receptions
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropIndex('idx_receptions_created_at');
            $table->dropIndex('idx_receptions_numero_contrat');
            $table->dropIndex('idx_receptions_date_livraison');
            $table->dropIndex('idx_receptions_contrat_date');
        });

        // Supprimer les indexes de la table materiels
        Schema::table('materiels', function (Blueprint $table) {
            $table->dropIndex('idx_materiels_modele_id');
            $table->dropIndex('idx_materiels_demande_id');
            $table->dropIndex('idx_materiels_reception_id');
            $table->dropIndex('idx_materiels_reception_modele');
            $table->dropIndex('idx_materiels_modele_demande');
        });

        // Supprimer les indexes de la table modele_materiels
        Schema::table('modele_materiels', function (Blueprint $table) {
            $table->dropIndex('idx_modele_nom');
            $table->dropIndex('idx_modele_categorie_id');
        });
    }
};