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
        'modele_materiel_id',
        'numero_serie',
        'categorie_id',
        'reception_id',
        'etat',
        'statut',
        'demande_id',
        'service_id',
        'scan_contrat',
        'description'
        // 'nom' a été supprimé ici car il est maintenant dans 'modele_materiels'
    ];

    protected $with = ['categorie:id,nom', 'pieces', 'demande:id,service_beneficiaire,demandeur_nom', 'modele'];
    protected $appends = ['est_disponible'];

    protected $casts = [
        'created_at' => 'datetime:d/m/Y H:i',
        'updated_at' => 'datetime:d/m/Y H:i',
        'demande_id' => 'integer',
        'service_id' => 'integer',
        'categorie_id' => 'integer',
        'modele_materiel_id' => 'integer',
    ];

    protected static function booted()
    {
        static::deleting(function ($materiel) {
            $materiel->pieces()->delete();
        });
    }

    // --- RELATIONS ---

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleMateriel::class, 'modele_materiel_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PieceMateriel::class, 'materiel_id')->orderBy('nom_piece');
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class, 'demande_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class, 'reception_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    // --- ACCESSEURS OPTIMISÉS ---

    public function getEstDisponibleAttribute(): bool
    {
        return $this->etat === 'Disponible' || $this->etat === 'En stock';
    }

    public function getEstCompletAttribute(): bool
    {
        return $this->pieces->every('statut', 'En Stock');
    }
    // --- SCOPES DE RECHERCHE ---

    public function scopeRechercher(Builder $query, $term): void
    {
        if ($term) {
            $query->where(function ($q) use ($term) {
                // CORRECTION : On ne cherche plus dans 'nom' (inexistant dans materiels)
                // mais on cherche dans le modèle associé
                $q->where('numero_serie', 'ilike', "%{$term}%")
                  ->orWhereHas('modele', function ($q2) use ($term) {
                      $q2->where('nom', 'ilike', "%{$term}%"); // Utilise la nouvelle colonne 'nom'
                  });
            });
        }
    }
}