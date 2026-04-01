<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\PieceMateriel;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Statistiques rapides (via Query Builder pour la performance)
        $statsMateriels = DB::table('materiels')
            ->select('etat', DB::raw('count(*) as total'))
            ->groupBy('etat')
            ->pluck('total', 'etat');

        $statutsMateriels = DB::table('materiels')
            ->select('statut', DB::raw('count(*) as total'))
            ->where('statut', 'En panne')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $piecesSorties = DB::table('pieces_materiels')
            ->whereNotNull('demande_id')
            ->count();

        // 2. Récupération des 5 derniers matériels avec leur modèle lié
        $recents = Materiel::with([
            'categorie:id,nom', 
            'pieces:id,materiel_id,nom_piece,demande_id', 
            'modele:id,nom' // Chargement de la relation pour récupérer le nom
        ])
        ->select('id', 'modele_materiel_id', 'numero_serie', 'etat', 'statut', 'categorie_id', 'created_at')
        ->latest()
        ->take(5)
        ->get()
        ->map(function ($item) {
            return [
                'id'           => $item->id,
                // On récupère le nom depuis le modèle lié
                'nom'          => $item->modele ? $item->modele->nom : 'N/A', 
                'numero_serie' => $item->numero_serie,
                'etat'         => $item->etat,
                'statut'       => $item->statut,
                'categorie'    => $item->categorie,
                'pieces'       => $item->pieces,
                'created_at'   => $item->created_at
            ];
        });

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => $statsMateriels->sum(),
                'en_stock'       => $statsMateriels['Disponible'] ?? 0,
                'octroyes'       => $statsMateriels['Livré'] ?? 0,
                'en_attente'     => $statsMateriels['En attente'] ?? 0,
                'en_panne'       => $statutsMateriels['En panne'] ?? 0,
                'pieces_sorties' => $piecesSorties,
                'recents'        => $recents
            ]
        ]);
    }
}