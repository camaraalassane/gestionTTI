<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrat extends Model
{
    use HasFactory;

    protected $table = 'contrats';

    protected $fillable = [
        'numero_contrat',
        'fournisseur',
        'quantite_totale_prevue',
        'description',
        'scan_contrat'
    ];

    /**
     * Un contrat est composé de plusieurs réceptions (lots)
     */
    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class, 'contrat_id');
    }
}
