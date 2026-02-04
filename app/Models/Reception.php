<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reception extends Model
{
    use HasFactory;

    protected $table = 'receptions';

    protected $fillable = [
        'fournisseur',
        'numero_contrat',
        'date_livraison',
        'categorie_id',
        'nbrcarton',
        'unite',
        'somme',
        'scan_contrat'
    ];

    /**
     * Casts pour assurer le bon formatage vers Inertia/Vuetify
     */
    protected $casts = [
        'date_livraison' => 'date:Y-m-d', // Format standard pour les inputs date de Vuetify
        'created_at'     => 'datetime:d/m/Y H:i',
    ];

    /**
     * Relation avec la Catégorie (Indispensable pour savoir ce qu'on a reçu)
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    /**
     * Relation avec les matériels (Les unités précises créées lors de cette réception)
     */
    public function materiels(): HasMany
    {
        return $this->hasMany(Materiel::class, 'reception_id');
    }
}
