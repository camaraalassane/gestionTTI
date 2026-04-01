<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PieceMateriel extends Model
{
    use HasFactory;
    protected $table = 'pieces_materiels';

    protected $fillable = [
        'modele_materiel_id',
        'materiel_id',
        'demande_id', // <--- TRÈS IMPORTANT : Ajoutez ceci pour permettre l'affectation
        'nom_piece',
        'numero_serie',
        'statut'
    ];

    /**
     * Le matériel parent auquel appartient la pièce
     */
    public function materiel()
    {
        return $this->belongsTo(Materiel::class);
    }

    /**
     * La demande (livraison) à laquelle cette pièce est potentiellement liée
     */
    public function demande()
    {
        return $this->belongsTo(Demande::class, 'demande_id');
    }
}
