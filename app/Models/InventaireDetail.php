<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireDetail extends Model
{
    // Ajoutez cette ligne pour autoriser l'insertion groupée
    protected $fillable = [
        'inventaire_id',
        'designation',
        'numero_serie',
        'etat_materiel',
        'localisation'
    ];

    /**
     * Relation vers l'inventaire parent
     */
    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }
}
