<?php

namespace App\Http\Controllers;

use App\Models\{Materiel, Inventaire, InventaireDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InventaireController extends Controller
{
    // Cache duration (minutes)
    const CACHE_DURATION = 60;

    public function index()
    {
        $historique = Inventaire::with('user:id,name')
            ->orderBy('annee', 'desc')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'annee' => $inv->annee,
                    'date_cloture' => $inv->date_cloture,
                    'created_at' => $inv->created_at,
                    'total_items' => $inv->total_items,
                    'user' => $inv->user?->name ?? 'Système',
                ];
            });

        return Inertia::render('materiel/InventaireIndex', [
            'historique' => $historique,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Création de l'inventaire (avec insertUsing)
     */
    public function store(Request $request)
    {
        $request->validate([
            'annee' => 'required|numeric|digits:4',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                
                $annee = (string) $request->annee;
                
                $existingInventaire = Inventaire::where('annee', $annee)->first();
                if ($existingInventaire) {
                    return back()->withErrors(['annee' => "L'année {$annee} a déjà été clôturée."]);
                }

                $totalMateriels = Materiel::whereNull('service_id')
                    ->whereNull('demande_id')
                    ->count();

                if ($totalMateriels === 0) {
                    return back()->withErrors(['annee' => 'Aucun matériel en stock à archiver.']);
                }

                $inventaire = Inventaire::create([
                    'annee' => $annee,
                    'date_cloture' => now(),
                    'total_items' => $totalMateriels,
                    'user_id' => Auth::id(),
                ]);

                // Utiliser insertUsing pour une insertion en masse optimisée
                $query = DB::table('materiels')
                    ->leftJoin('modele_materiels', 'materiels.modele_materiel_id', '=', 'modele_materiels.id')
                    ->whereNull('materiels.service_id')
                    ->whereNull('materiels.demande_id')
                    ->select([
                        DB::raw("{$inventaire->id} as inventaire_id"),
                        DB::raw("COALESCE(modele_materiels.nom, 'N/A') as designation"),
                        'materiels.numero_serie',
                        DB::raw("materiels.etat as etat_materiel"),
                        DB::raw("'MAGASIN' as localisation"),
                        DB::raw("NOW() as created_at"),
                        DB::raw("NOW() as updated_at")
                    ]);

                DB::table('inventaire_details')->insertUsing(['inventaire_id', 'designation', 'numero_serie', 'etat_materiel', 'localisation', 'created_at', 'updated_at'], $query);

                // Nettoyer le cache
                Cache::forget("inventaire_{$inventaire->id}_groupes");

                return redirect()->back()->with('success', "Inventaire {$annee} archivé avec succès ({$totalMateriels} matériels).");
                
            });
        } catch (\Exception $e) {
            Log::error('Erreur inventaire store: ' . $e->getMessage());
            return back()->withErrors(['annee' => "Erreur lors de l'archivage : " . $e->getMessage()]);
        }
    }

    /**
     * Affichage des détails (OPTIMISÉ AVEC CACHE)
     */
    public function show($id)
    {
        $inventaire = Inventaire::with('user:id,name')->findOrFail($id);
        
        $cacheKey = "inventaire_{$id}_groupes_page_" . (request()->get('page', 1));
        
        $groupesData = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($id) {
            return $this->getGroupedInventaireData($id);
        });
        
        // Pagination
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $offset = ($currentPage - 1) * $perPage;
        $paginatedGroupes = array_slice($groupesData['groupes'], $offset, $perPage);
        
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedGroupes,
            $groupesData['total_groupes'],
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        
        return Inertia::render('materiel/InventaireShow', [
            'inventaire' => [
                'id' => $inventaire->id,
                'annee' => $inventaire->annee,
                'date_cloture' => $inventaire->date_cloture,
                'total_items' => $inventaire->total_items,
                'responsable' => $inventaire->user?->name ?? 'Système'
            ],
            'groupes' => $paginated,
        ]);
    }

    /**
     * Récupérer les données groupées de l'inventaire
     */
    private function getGroupedInventaireData($id)
    {
        // Requête SQL optimisée avec GROUP BY
        $results = DB::select("
            SELECT 
                COALESCE(r.fournisseur, 'X1') as fournisseur,
                COALESCE(r.numero_contrat, 'Marche N°022') as numero_contrat,
                id.designation,
                COUNT(CASE WHEN id.localisation = 'MAGASIN' THEN 1 END) as qte_stock,
                COUNT(CASE WHEN id.localisation != 'MAGASIN' THEN 1 END) as qte_sorti,
                COUNT(*) as total
            FROM inventaire_details id
            LEFT JOIN materiels m ON m.numero_serie = id.numero_serie
            LEFT JOIN receptions r ON r.id = m.reception_id
            WHERE id.inventaire_id = ?
            GROUP BY r.fournisseur, r.numero_contrat, id.designation
            ORDER BY r.fournisseur, id.designation
        ", [$id]);
        
        // Regrouper par fournisseur/contrat
        $groupesArray = [];
        foreach ($results as $row) {
            $key = $row->fournisseur . '|' . $row->numero_contrat;
            if (!isset($groupesArray[$key])) {
                $groupesArray[$key] = [
                    'fournisseur' => $row->fournisseur,
                    'numero_contrat' => $row->numero_contrat,
                    'modeles' => []
                ];
            }
            $groupesArray[$key]['modeles'][] = [
                'designation' => $row->designation,
                'qte_stock' => (int)$row->qte_stock,
                'qte_sorti' => (int)$row->qte_sorti,
                'total' => (int)$row->total
            ];
        }
        
        return [
            'groupes' => array_values($groupesArray),
            'total_groupes' => count($groupesArray)
        ];
    }

    /**
     * Téléchargement PDF (OPTIMISÉ AVEC CACHE)
     */
    public function downloadPdf($id)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        
        $inventaire = Inventaire::with('user')->findOrFail($id);
        
        $cacheKey = "inventaire_{$id}_pdf_data";
        
        $data = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($inventaire, $id) {
            // Requête SQL optimisée pour le PDF
            $results = DB::select("
                SELECT 
                    COALESCE(r.fournisseur, 'X1') as fournisseur,
                    COALESCE(r.numero_contrat, 'Marche N°022') as numero_contrat,
                    id.designation,
                    COUNT(CASE WHEN id.localisation = 'MAGASIN' THEN 1 END) as qte_stock,
                    COUNT(CASE WHEN id.localisation != 'MAGASIN' THEN 1 END) as qte_sorti,
                    COUNT(*) as total
                FROM inventaire_details id
                LEFT JOIN materiels m ON m.numero_serie = id.numero_serie
                LEFT JOIN receptions r ON r.id = m.reception_id
                WHERE id.inventaire_id = ?
                GROUP BY r.fournisseur, r.numero_contrat, id.designation
                ORDER BY r.fournisseur, id.designation
            ", [$id]);
            
            // Regrouper par fournisseur/contrat
            $groupesArray = [];
            $totalStock = 0;
            $totalSorti = 0;
            
            foreach ($results as $row) {
                $key = $row->fournisseur . '|' . $row->numero_contrat;
                if (!isset($groupesArray[$key])) {
                    $groupesArray[$key] = [
                        'fournisseur' => $row->fournisseur,
                        'numero_contrat' => $row->numero_contrat,
                        'modeles' => []
                    ];
                }
                $groupesArray[$key]['modeles'][] = [
                    'designation' => $row->designation,
                    'qte_stock' => (int)$row->qte_stock,
                    'qte_sorti' => (int)$row->qte_sorti,
                    'total' => (int)$row->total
                ];
                
                $totalStock += (int)$row->qte_stock;
                $totalSorti += (int)$row->qte_sorti;
            }
            
            return [
                'title' => "INVENTAIRE - " . $inventaire->annee,
                'inventaire' => $inventaire,
                'groupes' => array_values($groupesArray),
                'responsable' => $inventaire->user?->name ?? 'Système',
                'date' => now(),
                'total_modeles' => count($results),
                'total_stock' => $totalStock,
                'total_sorti' => $totalSorti,
                'total_materiels' => $inventaire->total_items,
            ];
        });

        $pdf = Pdf::loadView('pdf.inventaire', $data)
            ->setPaper('a4', 'portrait');
        
        return $pdf->download("Inventaire_{$inventaire->annee}.pdf");
    }

    /**
     * Vider le cache de l'inventaire
     */
 public function clearCache()
{
    try {
        Cache::flush();
        
        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Cache vidé avec succès']);
        }
        
        return redirect()->back()->with('success', 'Cache vidé avec succès');
    } catch (\Exception $e) {
        Log::error('Erreur clearCache: ' . $e->getMessage());
        
        if (request()->wantsJson()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
        
        return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
    }
}
}