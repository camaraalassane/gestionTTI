<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    // Définition de la table (optionnel mais recommandé)
    protected $table = 'services';

    protected $fillable = ['nom'];

    /**
     * Relation avec les matériels.
     * Permet de lister tous les équipements actuellement affectés à ce service.
     */
    public function materiels(): HasMany
    {
        return $this->hasMany(Materiel::class, 'service_id');
    }

    /**
     * Relation avec les demandes.
     * Permet de retrouver l'historique des commandes passées par ce service.
     */
    public function demandes(): HasMany
    {
        // Note : On utilise ici le nom du service comme lien si votre table
        // demandes n'utilise pas de service_id numérique.
        return $this->hasMany(Demande::class, 'service_beneficiaire', 'nom');
    }

    /**
     * Casts pour le formatage vers Inertia/Vuetify
     */
    protected $casts = [
        'created_at' => 'datetime:d/m/Y',
    ];
}
