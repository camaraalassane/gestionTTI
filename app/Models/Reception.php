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
        'contrat_id',       // <--- TRÈS IMPORTANT : Ajoute ceci pour permettre le lien
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
        'date_livraison' => 'date:Y-m-d',
        'created_at'     => 'datetime:d/m/Y H:i',
    ];

    /**
     * Relation avec le Contrat global (NOUVEAU)
     * Permet de remonter au contrat parent pour voir l'avancement global
     */
    public function contrat(): BelongsTo
    {
        // On utilise Contrat avec Majuscule si c'est ce que tu as choisi finalement
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }

    /**
     * Relation avec la Catégorie
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    /**
     * Relation avec les matériels
     */
    public function materiels(): HasMany
    {
        return $this->hasMany(Materiel::class, 'reception_id');
    }
}
