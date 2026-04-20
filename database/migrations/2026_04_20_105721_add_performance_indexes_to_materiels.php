<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Index sur la table materiels
        Schema::table('materiels', function (Blueprint $table) {
            // Vérifier si l'index n'existe pas déjà avant de l'ajouter
            if (!Schema::hasIndex('materiels', ['modele_materiel_id', 'etat'])) {
                $table->index(['modele_materiel_id', 'etat']);
            }
            if (!Schema::hasIndex('materiels', ['modele_materiel_id', 'demande_id'])) {
                $table->index(['modele_materiel_id', 'demande_id']);
            }
            if (!Schema::hasIndex('materiels', ['reception_id', 'etat'])) {
                $table->index(['reception_id', 'etat']);
            }
            if (!Schema::hasIndex('materiels', ['categorie_id', 'etat'])) {
                $table->index(['categorie_id', 'etat']);
            }
            if (!Schema::hasIndex('materiels', ['service_id', 'etat'])) {
                $table->index(['service_id', 'etat']);
            }
            if (!Schema::hasIndex('materiels', ['etat'])) {
                $table->index('etat');
            }
            if (!Schema::hasIndex('materiels', ['statut'])) {
                $table->index('statut');
            }
            if (!Schema::hasIndex('materiels', ['demande_id'])) {
                $table->index('demande_id');
            }
        });

        // Index sur la table receptions
        Schema::table('receptions', function (Blueprint $table) {
            if (!Schema::hasIndex('receptions', ['contrat_id'])) {
                $table->index('contrat_id');
            }
            if (!Schema::hasIndex('receptions', ['date_livraison'])) {
                $table->index('date_livraison');
            }
            if (!Schema::hasIndex('receptions', ['contrat_id', 'date_livraison'])) {
                $table->index(['contrat_id', 'date_livraison']);
            }
        });

        // Index sur la table modele_materiels
        Schema::table('modele_materiels', function (Blueprint $table) {
            if (!Schema::hasIndex('modele_materiels', ['categorie_id'])) {
                $table->index('categorie_id');
            }
            if (!Schema::hasIndex('modele_materiels', ['nom'])) {
                $table->index('nom');
            }
        });

        // Index sur la table contrats
        Schema::table('contrats', function (Blueprint $table) {
            if (!Schema::hasIndex('contrats', ['numero_contrat'])) {
                $table->index('numero_contrat');
            }
        });
    }

    public function down()
    {
        // Supprimer les index (optionnel)
        Schema::table('materiels', function (Blueprint $table) {
            $table->dropIndex(['modele_materiel_id', 'etat']);
            $table->dropIndex(['modele_materiel_id', 'demande_id']);
            $table->dropIndex(['reception_id', 'etat']);
            $table->dropIndex(['categorie_id', 'etat']);
            $table->dropIndex(['service_id', 'etat']);
            $table->dropIndex(['etat']);
            $table->dropIndex(['statut']);
            $table->dropIndex(['demande_id']);
        });

        Schema::table('receptions', function (Blueprint $table) {
            $table->dropIndex(['contrat_id']);
            $table->dropIndex(['date_livraison']);
            $table->dropIndex(['contrat_id', 'date_livraison']);
        });

        Schema::table('modele_materiels', function (Blueprint $table) {
            $table->dropIndex(['categorie_id']);
            $table->dropIndex(['nom']);
        });

        Schema::table('contrats', function (Blueprint $table) {
            $table->dropIndex(['numero_contrat']);
        });
    }
};
