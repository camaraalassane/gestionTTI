<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\PieceMateriel;
use App\Models\Service;
use App\Models\ModeleMateriel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function getServiceStats(int $serviceId)
    {
        $service = Service::findOrFail($serviceId);

        $materiels = Materiel::where('service_id', $serviceId)
            ->where('etat', 'Livré')
            ->with(['modele', 'modele.categorie'])
            ->get();

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

    /**
     * Récupérer les données pour le graphique : modèles affectés par service
     */
    public function getModelesParService()
    {
        try {
            $services = Service::whereHas('materiels', function($q) {
                $q->where('etat', 'Livré');
            })->orderBy('nom')->get();

            $modeles = ModeleMateriel::whereHas('exemplaires', function($q) {
                $q->where('etat', 'Livré');
            })->orderBy('nom')->pluck('nom');

            $data = DB::table('materiels')
                ->join('modele_materiels', 'materiels.modele_materiel_id', '=', 'modele_materiels.id')
                ->join('services', 'materiels.service_id', '=', 'services.id')
                ->where('materiels.etat', 'Livré')
                ->select('services.nom as service', 'modele_materiels.nom as modele', DB::raw('count(*) as total'))
                ->groupBy('services.nom', 'modele_materiels.nom')
                ->orderBy('services.nom')
                ->orderBy('modele_materiels.nom')
                ->get();

            $servicesList = $services->pluck('nom')->toArray();
            $modelesList = $modeles->toArray();

            $result = [];
            foreach ($data as $row) {
                $result[$row->service][$row->modele] = $row->total;
            }

            $chartData = [];
            foreach ($servicesList as $service) {
                $row = [];
                foreach ($modelesList as $modele) {
                    $row[] = $result[$service][$modele] ?? 0;
                }
                $chartData[] = $row;
            }

            return response()->json([
                'services' => $servicesList,
                'modeles' => $modelesList,
                'data' => $chartData
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getModelesParService: ' . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
