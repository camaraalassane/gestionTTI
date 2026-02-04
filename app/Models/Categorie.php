<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Categorie extends Model
{
    use HasFactory;

    // Force le nom de la table au cas où (recommandé pour la clarté)
    protected $table = 'categories';

    protected $fillable = ['nom'];

    /**
     * Relation avec les matériels.
     * Permet de compter le stock réel par catégorie dans vos Dashboard et Index.
     */
    public function materiels(): HasMany
    {
        // On précise bien la clé étrangère 'categorie_id'
        return $this->hasMany(Materiel::class, 'categorie_id');
    }

    /**
     * Casts pour le formatage des dates vers Inertia/Vuetify
     */
    protected $casts = [
        'created_at' => 'datetime:d/m/Y',
    ];
}
