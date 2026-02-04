<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Materiel extends Model
{
    use HasFactory;

    protected $table = 'materiels';

    protected $fillable = [
        'nom',
        'numero_serie',
        'categorie_id',
        'reception_id',
        'etat',
        'statut',        // neuf, utilisé, en_panne
        'demande_id',
        'service_id',
        'scan_contrat',
        'description'
    ];

    /**
     * Charger systématiquement la catégorie et les pièces pour le frontend
     */
    protected $with = ['categorie', 'pieces'];

    /**
     * Rend les attributs calculés disponibles pour Vue.js
     */
    protected $appends = ['est_disponible', 'est_complet'];

    protected $casts = [
        'created_at' => 'datetime:d/m/Y H:i',
        'updated_at' => 'datetime:d/m/Y H:i',
        'demande_id' => 'integer',
        'service_id' => 'integer',
        'categorie_id' => 'integer',
    ];

    // --- RELATIONS ---

    public function pieces(): HasMany
    {
        return $this->hasMany(PieceMateriel::class, 'materiel_id');
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class, 'demande_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class, 'reception_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

// --- ACCESSEURS & SCOPES ---

/**
 * Le matériel est disponible tant qu'il est logistiquement en stock.
 * Même "En panne", il peut être livré (ex: pour réparation ou rebut).
 */
public function getEstDisponibleAttribute(): bool
{
    return $this->etat === 'Disponible';
}

/**
 * Vérifie si toutes les pièces sont encore présentes
 */
public function getEstCompletAttribute(): bool
{
    if ($this->pieces->isEmpty()) return true;

    return $this->pieces->where('statut', 'En Stock')->count() === $this->pieces->count();
}

/**
 * Filtre pour le stock (utilisé dans le create du controller)
 */
public function scopeEnStock(Builder $query): void
{
    $query->where('etat', 'Disponible');
}

/**
 * Filtre par état logistique (Disponible, En attente, Livré)
 */
public function scopeParLogistique(Builder $query, string $etat): void
{
    $query->where('etat', $etat);
}

/**
 * Filtre par statut physique (Neuf, En panne, Utilisé)
 */
public function scopeParStatutPhysique(Builder $query, string $statut): void
{
    $query->where('statut', $statut);
}

}
