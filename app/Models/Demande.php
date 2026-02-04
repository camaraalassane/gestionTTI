<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- AJOUTE CETTE LIGNE
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numcomande',
        'date_demande',
        'materiel_id',
        'nbredemande',
        'numero_serie',
        'categorie',
        'service_beneficiaire',
        'statut',
        'demandeur_nom',
        'nom_materiel',
        'description',
        'scan_contrat', 
    ];

    /**
     * Relation vers le matériel associé.
     */
    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    /**
     * Relation avec les pièces détachées
     */
    public function pieces(): HasMany
    {
        // 'demande_id' doit exister dans ta table pieces_materiels
        return $this->hasMany(PieceMateriel::class, 'demande_id');
    }

    /**
     * Booted : Logique automatique à la création.
     */
    protected static function booted()
    {
        static::creating(function ($demande) {
            if (empty($demande->statut)) {
                $demande->statut = 'En attente';
            }

            if (empty($demande->numcomande)) {
                $prefix = 'CMD-' . date('Y') . '-';
                $last = static::where('numcomande', 'like', $prefix . '%')
                             ->orderBy('id', 'desc')
                             ->first();

                if ($last) {
                    $lastNum = (int) substr($last->numcomande, -4);
                    $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $newNum = '0001';
                }
                $demande->numcomande = $prefix . $newNum;
            }
        });
    }

    protected $casts = [
        'date_demande' => 'date:d/m/Y',
        'created_at'   => 'datetime:d/m/Y H:i',
    ];
}