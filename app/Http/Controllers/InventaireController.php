<?php

namespace App\Http\Controllers;

use App\Models\{Materiel, Inventaire, InventaireDetail, Demande, Service};
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

    /**
     * Affiche la liste des inventaires
     */
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
 * Création de l'inventaire annuel
 * - Stock : TOUS les matériels en stock (quel que soit leur année)
 * - Sortis : UNIQUEMENT ceux clôturés dans l'année
 */
public function store(Request $request)
{
    $request->validate([
        'annee' => 'required|numeric|digits:4',
    ]);

    try {
        return DB::transaction(function () use ($request) {

            $annee = (string) $request->annee;

            // Vérifier si l'année est déjà clôturée
            $existingInventaire = Inventaire::where('annee', $annee)->first();
            if ($existingInventaire) {
                return back()->withErrors(['annee' => "L'année {$annee} a déjà été clôturée."]);
            }

            // ✅ 1. Compter TOUS les matériels en stock (MAGASIN)
            $materielsEnStock = Materiel::whereNull('service_id')
                ->whereNull('demande_id')
                ->count();

            // ✅ 2. Compter UNIQUEMENT les sorties de l'année
            $materielsSortisAnnee = Materiel::whereNotNull('service_id')
                ->whereHas('demande', function($q) use ($annee) {
                    $q->whereYear('date_demande', $annee)
                      ->where('statut', 'Clôturé');
                })
                ->count();

            $totalMateriels = $materielsEnStock + $materielsSortisAnnee;

            if ($totalMateriels === 0) {
                return back()->withErrors(['annee' => 'Aucun matériel à archiver pour cette année.']);
            }

            // Créer l'inventaire
            $inventaire = Inventaire::create([
                'annee' => $annee,
                'date_cloture' => now(),
                'total_items' => $totalMateriels,
                'user_id' => Auth::id(),
            ]);

            // ✅ 3. Insérer les matériels en STOCK (tous, sans condition d'année)
            $queryStock = DB::table('materiels')
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

            DB::table('inventaire_details')->insertUsing(
                ['inventaire_id', 'designation', 'numero_serie', 'etat_materiel', 'localisation', 'created_at', 'updated_at'],
                $queryStock
            );

            // ✅ 4. Insérer les SORTIES de l'année uniquement - VERSION CORRIGÉE
            // On utilise Eloquent pour la requête puis on la convertit en Query Builder
            $querySorties = Materiel::whereNotNull('service_id')
    ->join('modele_materiels', 'materiels.modele_materiel_id', '=', 'modele_materiels.id')
    ->join('services', 'materiels.service_id', '=', 'services.id')
    ->join('demandes', 'materiels.demande_id', '=', 'demandes.id')
    ->whereYear('demandes.date_demande', $annee)
    ->where('demandes.statut', 'Clôturé')
    ->select([
                    DB::raw("{$inventaire->id} as inventaire_id"),
                    DB::raw("COALESCE(modele_materiels.nom, 'N/A') as designation"),
                    'materiels.numero_serie',
                    DB::raw("materiels.etat as etat_materiel"),
                    DB::raw("'SORTI' as localisation"),
                    DB::raw("NOW() as created_at"),
                    DB::raw("NOW() as updated_at")
                ]);

            // ✅ Convertir Eloquent Builder en Query Builder pour insertUsing
            DB::table('inventaire_details')->insertUsing(
                ['inventaire_id', 'designation', 'numero_serie', 'etat_materiel', 'localisation', 'created_at', 'updated_at'],
                $querySorties->toBase()
            );

            // Nettoyer le cache
            Cache::forget("inventaire_{$inventaire->id}_groupes");
            Cache::forget("inventaire_{$inventaire->id}_pdf_data");

            // Calculer les statistiques
            $stats = $this->calculateInventoryStats($inventaire->id);

            return redirect()->back()->with('success',
                "Inventaire {$annee} archivé avec succès.\n" .
                "Total: {$totalMateriels} matériels | " .
                "Stock: {$stats['stock']} | Sortis: {$stats['sortis']} (uniquement {$annee})"
            );

        });
    } catch (\Exception $e) {
        Log::error('Erreur inventaire store: ' . $e->getMessage());
        return back()->withErrors(['annee' => "Erreur lors de l'archivage : " . $e->getMessage()]);
    }
}

    /**
     * Calculer les statistiques de l'inventaire
     */
    private function calculateInventoryStats(int $inventaireId): array
    {
        $stats = DB::table('inventaire_details')
            ->where('inventaire_id', $inventaireId)
            ->selectRaw("
                COUNT(CASE WHEN localisation = 'MAGASIN' THEN 1 END) as stock,
                COUNT(CASE WHEN localisation = 'SORTI' THEN 1 END) as sortis,
                COUNT(*) as total
            ")
            ->first();

        return [
            'stock' => $stats->stock ?? 0,
            'sortis' => $stats->sortis ?? 0,
            'total' => $stats->total ?? 0,
        ];
    }

    /**
     * Affichage des détails (OPTIMISÉ AVEC CACHE)
     */
    public function show(int $id)
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

        // Statistiques
        $stats = $this->calculateInventoryStats($id);

        return Inertia::render('materiel/InventaireShow', [
            'inventaire' => [
                'id' => $inventaire->id,
                'annee' => $inventaire->annee,
                'date_cloture' => $inventaire->date_cloture,
                'total_items' => $inventaire->total_items,
                'responsable' => $inventaire->user?->name ?? 'Système'
            ],
            'groupes' => $paginated,
            'stats' => $stats,
        ]);
    }

    /**
     * Récupérer les données groupées de l'inventaire
     */
    private function getGroupedInventaireData(int $id): array
    {
        $results = DB::select("
            SELECT
                COALESCE(r.fournisseur, 'X1') as fournisseur,
                COALESCE(r.numero_contrat, 'Marche N°022') as numero_contrat,
                id.designation,
                COUNT(CASE WHEN id.localisation = 'MAGASIN' THEN 1 END) as qte_stock,
                COUNT(CASE WHEN id.localisation = 'SORTI' THEN 1 END) as qte_sorti,
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
     * Téléchargement PDF
     */
    public function downloadPdf(int $id)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $inventaire = Inventaire::with('user')->findOrFail($id);

        $cacheKey = "inventaire_{$id}_pdf_data";

        $data = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($inventaire, $id) {

            $results = DB::select("
                SELECT
                    COALESCE(r.fournisseur, 'X1') as fournisseur,
                    COALESCE(r.numero_contrat, 'Marche N°022') as numero_contrat,
                    id.designation,
                    COUNT(CASE WHEN id.localisation = 'MAGASIN' THEN 1 END) as qte_stock,
                    COUNT(CASE WHEN id.localisation = 'SORTI' THEN 1 END) as qte_sorti,
                    COUNT(*) as total
                FROM inventaire_details id
                LEFT JOIN materiels m ON m.numero_serie = id.numero_serie
                LEFT JOIN receptions r ON r.id = m.reception_id
                WHERE id.inventaire_id = ?
                GROUP BY r.fournisseur, r.numero_contrat, id.designation
                ORDER BY r.fournisseur, id.designation
            ", [$id]);

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

                $qteStock = (int)$row->qte_stock;
                $qteSorti = (int)$row->qte_sorti;

                $groupesArray[$key]['modeles'][] = [
                    'designation' => $row->designation,
                    'qte_stock' => $qteStock,
                    'qte_sorti' => $qteSorti,
                    'total' => (int)$row->total
                ];

                $totalStock += $qteStock;
                $totalSorti += $qteSorti;
            }

            // Statistiques détaillées
            $stats = DB::table('inventaire_details')
                ->where('inventaire_id', $id)
                ->selectRaw("
                    COUNT(CASE WHEN localisation = 'MAGASIN' THEN 1 END) as stock,
                    COUNT(CASE WHEN localisation = 'SORTI' THEN 1 END) as sortis
                ")
                ->first();

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
                'annee' => $inventaire->annee,
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

    /**
     * ✅ Reconstruire un inventaire avec la nouvelle logique
     */
    public function rebuildInventory(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $inventaire = Inventaire::findOrFail($id);
                $annee = $inventaire->annee;

                // Supprimer les anciens détails
                DB::table('inventaire_details')->where('inventaire_id', $id)->delete();

                // ✅ 1. Insérer les matériels en STOCK
                DB::statement("
                    INSERT INTO inventaire_details (inventaire_id, designation, numero_serie, etat_materiel, localisation, created_at, updated_at)
                    SELECT
                        ? as inventaire_id,
                        COALESCE(mm.nom, 'N/A') as designation,
                        m.numero_serie,
                        m.etat as etat_materiel,
                        'MAGASIN' as localisation,
                        NOW() as created_at,
                        NOW() as updated_at
                    FROM materiels m
                    LEFT JOIN modele_materiels mm ON m.modele_materiel_id = mm.id
                    WHERE m.service_id IS NULL AND m.demande_id IS NULL
                ", [$id]);

                // ✅ 2. Insérer les SORTIES de l'année uniquement
                DB::statement("
                    INSERT INTO inventaire_details (inventaire_id, designation, numero_serie, etat_materiel, localisation, created_at, updated_at)
                    SELECT
                        ? as inventaire_id,
                        COALESCE(mm.nom, 'N/A') as designation,
                        m.numero_serie,
                        m.etat as etat_materiel,
                        'SORTI' as localisation,
                        NOW() as created_at,
                        NOW() as updated_at
                    FROM materiels m
                    LEFT JOIN modele_materiels mm ON m.modele_materiel_id = mm.id
                    INNER JOIN demandes d ON m.demande_id = d.id
                    WHERE m.service_id IS NOT NULL
                    AND d.statut = 'Clôturé'
                    AND EXTRACT(YEAR FROM d.date_demande) = ?
                ", [$id, $annee]);

                // Mettre à jour le total
                $total = DB::table('inventaire_details')->where('inventaire_id', $id)->count();
                $inventaire->update(['total_items' => $total]);

                // Vider le cache
                Cache::forget("inventaire_{$id}_groupes");
                Cache::forget("inventaire_{$id}_pdf_data");

                $stats = $this->calculateInventoryStats($id);

                return back()->with('success',
                    "Inventaire {$inventaire->annee} reconstruit avec succès.\n" .
                    "Total: {$total} | Stock: {$stats['stock']} | Sortis: {$stats['sortis']} (uniquement {$annee})"
                );
            });
        } catch (\Exception $e) {
            Log::error('Erreur rebuildInventory: ' . $e->getMessage());
            return back()->with('error', "Erreur : " . $e->getMessage());
        }
    }

    /**
     * ✅ Vérifier ce qui sera archivé avant la clôture
     */
    public function previewCloture(Request $request)
    {
        $annee = $request->input('annee', date('Y'));

        // Stock
        $stock = Materiel::whereNull('service_id')
            ->whereNull('demande_id')
            ->count();

        // Sorties de l'année
        $sorties = Materiel::whereNotNull('service_id')
            ->whereHas('demande', function($q) use ($annee) {
                $q->whereYear('date_demande', $annee)
                  ->where('statut', 'Clôturé');
            })
            ->count();

        // Détail des sorties par modèle
        $detailsSorties = Materiel::whereNotNull('service_id')
            ->whereHas('demande', function($q) use ($annee) {
                $q->whereYear('date_demande', $annee)
                  ->where('statut', 'Clôturé');
            })
            ->select(
                'modele_materiel_id',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('modele_materiel_id')
            ->with('modele')
            ->get();

        return response()->json([
            'annee' => $annee,
            'stock' => $stock,
            'sorties_annee' => $sorties,
            'total' => $stock + $sorties,
            'details_sorties' => $detailsSorties->map(function($item) {
                return [
                    'modele' => $item->modele?->nom ?? 'N/A',
                    'quantite' => $item->total
                ];
            })
        ]);
    }
}
