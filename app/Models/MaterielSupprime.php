<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterielSupprime extends Model
{
    use HasFactory;

    // Nom de la table conforme à votre migration
    protected $table = 'materiel_supprimes';

    // Désactiver les timestamps standards (created_at/updated_at)
    // si vous utilisez uniquement 'supprime_le'
    public $timestamps = true;

    protected $fillable = [
        'nom',
        'numero_serie',
        'categorie',
        'fournisseur',
        'supprime_le',
        'par_utilisateur'
    ];

    /**
     * Casts pour un affichage propre dans l'historique de suppression
     */
    protected $casts = [
        'supprime_le' => 'datetime:d/m/Y H:i',
        'created_at'  => 'datetime:d/m/Y H:i',
    ];
}
