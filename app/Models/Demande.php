<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numcomande',
        'date_demande',
        'materiel_id',
        'modele_materiel_id', // AJOUTÉ : Pour la liaison normalisée
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
     * Relation vers le matériel spécifique (exemplaire).
     */
    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class, 'materiel_id');
    }

    /**
     * Relation vers le modèle (catalogue).
     */
    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleMateriel::class, 'modele_materiel_id');
    }

    /**
     * Relation avec les pièces détachées.
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(PieceMateriel::class, 'demande_id');
    }
/**
 * Accesseur pour récupérer le nom du modèle de manière sécurisée.
 */
public function getNomMaterielAttribute()
{
    // Si tu veux toujours avoir accès au nom, même si la relation est nulle
    return $this->modele ? $this->modele->nom : $this->attributes['nom_materiel'];
}
    /**
     * Booted : Logique automatique à la création.
     */
    protected static function booted()
    {
        static::creating(function ($demande) {
            $demande->statut = $demande->statut ?? 'En attente';

            if (empty($demande->numcomande)) {
                $prefix = 'CMD-' . date('Y') . '-';
                $last = static::where('numcomande', 'like', $prefix . '%')
                              ->orderBy('id', 'desc')
                              ->first();

                $nextNumber = $last ? ((int) substr($last->numcomande, -4)) + 1 : 1;
                $demande->numcomande = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $casts = [
        'date_demande' => 'date:d/m/Y',
        'created_at'   => 'datetime:d/m/Y H:i',
    ];
}