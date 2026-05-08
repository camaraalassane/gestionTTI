<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ModeleMateriel extends Model
{
    protected $fillable = ['nom', 'categorie_id'];

    /**
     * Bootstrap du modèle
     */
    protected static function booted()
    {
        // Synchroniser la catégorie des exemplaires quand celle du modèle change
        static::updated(function ($modele) {
            if ($modele->wasChanged('categorie_id')) {
                $modele->exemplaires()->update(['categorie_id' => $modele->categorie_id]);
            }
        });

        // Optionnel : synchroniser aussi à la création
        static::created(function ($modele) {
            $modele->exemplaires()->update(['categorie_id' => $modele->categorie_id]);
        });
    }

    // Relation avec la table Categories
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    // Relation avec les exemplaires physiques (la table materiels)
    public function exemplaires(): HasMany
    {
        return $this->hasMany(Materiel::class, 'modele_materiel_id');
    }

    // Relation pour compter les pièces à travers les matériels
    public function pieces(): HasManyThrough
    {
        return $this->hasManyThrough(
            PieceMateriel::class, // Modèle cible
            Materiel::class,      // Modèle intermédiaire
            'modele_materiel_id', // Clé étrangère sur la table materiels
            'materiel_id',        // Clé étrangère sur la table pieces_materiels
            'id',                 // Clé locale sur modele_materiels
            'id'                  // Clé locale sur materiels
        );
    }
}
