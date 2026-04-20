<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\PieceMateriel;
use App\Models\Service;
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
            'modele:id,nom'
        ])
        ->select('id', 'modele_materiel_id', 'numero_serie', 'etat', 'statut', 'categorie_id', 'created_at')
        ->latest()
        ->take(5)
        ->get()
        ->map(function ($item) {
            return [
                'id'           => $item->id,
                'nom'          => $item->modele ? $item->modele->nom : 'N/A',
                'numero_serie' => $item->numero_serie,
                'etat'         => $item->etat,
                'statut'       => $item->statut,
                'categorie'    => $item->categorie,
                'pieces'       => $item->pieces,
                'created_at'   => $item->created_at
            ];
        });

        // 3. Récupération de tous les services pour le select
        $services = Service::select('id', 'nom')->orderBy('nom')->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => $statsMateriels->sum(),
                'en_stock'       => $statsMateriels['Disponible'] ?? 0,
                'octroyes'       => $statsMateriels['Livré'] ?? 0,
                'en_attente'     => $statsMateriels['En attente'] ?? 0,
                'en_panne'       => $statutsMateriels['En panne'] ?? 0,
                'pieces_sorties' => $piecesSorties,
                'recents'        => $recents
            ],
            'services' => $services
        ]);
    }

    /**
     * Récupérer les statistiques d'un service spécifique
     */
    public function getServiceStats($serviceId)
    {
        $service = Service::findOrFail($serviceId);

        // Récupérer tous les matériels affectés à ce service
        $materiels = Materiel::where('service_id', $serviceId)
            ->where('etat', 'Livré')
            ->with(['modele', 'modele.categorie'])
            ->get();

        // Grouper par catégorie et par modèle
        $categories = [];
        foreach ($materiels as $materiel) {
            if (!$materiel->modele) continue;

            $categorieId = $materiel->modele->categorie_id;
            $categorieNom = $materiel->modele->categorie->nom ?? 'Sans catégorie';
            $modeleId = $materiel->modele->id;
            $modeleNom = $materiel->modele->nom;

            if (!isset($categories[$categorieId])) {
                $categories[$categorieId] = [
                    'id' => $categorieId,
                    'nom' => $categorieNom,
                    'modeles' => [],
                    'total_quantite' => 0
                ];
            }

            if (!isset($categories[$categorieId]['modeles'][$modeleId])) {
                $categories[$categorieId]['modeles'][$modeleId] = [
                    'id' => $modeleId,
                    'nom' => $modeleNom,
                    'quantite' => 0
                ];
            }

            $categories[$categorieId]['modeles'][$modeleId]['quantite']++;
            $categories[$categorieId]['total_quantite']++;
        }

        // Convertir les tableaux internes en listes
        foreach ($categories as &$categorie) {
            $categorie['modeles'] = array_values($categorie['modeles']);
        }

        return response()->json([
            'service_nom' => $service->nom,
            'total_materiels' => $materiels->count(),
            'categories' => array_values($categories),
            'last_update' => now()->format('d/m/Y H:i')
        ]);
    }

    /**
     * Récupérer les statistiques globales du service (optionnel)
     */
    public function getAllServicesStats()
    {
        $services = Service::withCount(['materiels as total_materiels' => function($q) {
            $q->where('etat', 'Livré');
        }])->get();

        return response()->json($services);
    }
}
