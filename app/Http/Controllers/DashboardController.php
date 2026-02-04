<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
  public function index()
    {
        // 1. On récupère les chiffres selon les états
        // On utilise les noms que ton Dashboard attend : 'octroyes' et 'en_stock'
        $octroyes = Materiel::where('etat', 'Livré')->count(); // C'est ton 14
        $en_stock = Materiel::where('etat', 'Disponible')->count();
        $total = Materiel::count();

        // On récupère aussi le chiffre 3 (même si ton dashboard actuel n'a pas encore de case pour lui)
        $en_attente = Materiel::where('etat', 'En attente')->count();

        $piecesSorties = DB::table('pieces_materiels')
            ->whereNotNull('demande_id')
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => $total,
                'en_stock'       => $en_stock,
                'octroyes'       => $octroyes, // Dashboard affichera 14 ici
                'en_panne'       => Materiel::where('statut', 'En panne')->count(),
                'pieces_sorties' => $piecesSorties,
                'en_attente'     => $en_attente, // Pour usage futur

                'recents' => Materiel::with(['categorie:id,nom', 'pieces'])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'id'           => $item->id,
                            'nom'          => $item->nom,
                            'numero_serie' => $item->numero_serie,
                            'etat'         => $item->etat,
                            'statut'       => $item->statut,
                            'categorie'    => $item->categorie ? ['nom' => $item->categorie->nom] : null,
                            'pieces'       => $item->pieces->map(fn($p) => [
                                'id'         => $p->id,
                                'nom_piece'  => $p->nom_piece,
                                'demande_id' => $p->demande_id
                            ]),
                        ];
                    })
            ]
        ]);
    }
}
