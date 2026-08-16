<?php

namespace App\Http\Controllers;

use App\Models\Reception;
use App\Models\Materiel;
use App\Models\Contrat;
use App\Models\Categorie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    /**
     * Affiche la liste des CONTRATS groupés
     */
    public function index(Request $request)
    {
        // Récupérer tous les contrats avec leurs réceptions et catégories
        $contrats = Contrat::with(['receptions.categorie'])
            ->orderBy('created_at', 'desc')
            ->get();

        $contratsData = [];

        foreach ($contrats as $contrat) {
            $categories = [];
            $dateLivraison = null;

            foreach ($contrat->receptions as $reception) {
                // Récupérer la date de la première réception
                if (!$dateLivraison && $reception->date_livraison) {
                    $dateLivraison = $reception->date_livraison;
                }

                // Utilisation de la catégorie directe de la réception
                if ($reception->categorie && !in_array($reception->categorie->nom, $categories)) {
                    $categories[] = $reception->categorie->nom;
                }
            }

            $contratsData[] = [
                'id' => $contrat->id,
                'numero_contrat' => $contrat->numero_contrat,
                'fournisseur' => $contrat->fournisseur,
                'date_livraison' => $dateLivraison,
                'scan_contrat' => $contrat->scan_contrat,
                'created_at' => $contrat->created_at,
                'all_categories' => array_unique($categories),
            ];
        }

        // Pagination : 10 éléments par page
        $perPage = 10;
        $currentPage = $request->get('page', 1);

        // Appliquer les filtres si présents
        $query = collect($contratsData);

        // Filtre par recherche
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query = $query->filter(function($item) use ($search) {
                return str_contains(strtolower($item['numero_contrat']), $search) ||
                       str_contains(strtolower($item['fournisseur']), $search);
            });
        }

        // Filtre par date
        if ($request->filled('date_debut')) {
            $query = $query->filter(function($item) use ($request) {
                return $item['date_livraison'] >= $request->date_debut;
            });
        }

        if ($request->filled('date_fin')) {
            $query = $query->filter(function($item) use ($request) {
                return $item['date_livraison'] <= $request->date_fin;
            });
        }

        $totalItems = $query->count();
        $paginatedData = $query->values()->forPage($currentPage, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            $totalItems,
            $perPage,
            $currentPage,
            ['path' => route('reception.index'), 'query' => $request->query()]
        );

        return Inertia::render('materiel/ReceptionContracts', [
            'receptions' => $paginated,
            'filters' => $request->only(['search', 'date_debut', 'date_fin'])
        ]);
    }

    /**
     * Récupère la liste des différents lots pour la traçabilité
     * Utilisé par la Modale 2 - GROUPÉ PAR DATE DE LIVRAISON
     */
    public function getLotsJson(int $id)
    {
        $results = DB::select("
            SELECT
                MIN(r.id) as id,
                r.date_livraison,
                SUM(r.unite) as quantite_recue,
                COUNT(DISTINCT r.id) as nb_receptions
            FROM receptions r
            WHERE r.contrat_id = ?
            GROUP BY r.date_livraison
            ORDER BY r.date_livraison ASC
        ", [$id]);

        return response()->json($results);
    }

    /**
     * Détails d'un contrat - Groupé par modèle
     */
    public function getMaterielsJson(int $id)
    {
        $results = DB::select("
            SELECT
                mm.nom as designation,
                COUNT(*) as total,
                SUM(CASE WHEN m.demande_id IS NULL THEN 1 ELSE 0 END) as qte_stock,
                SUM(CASE WHEN m.demande_id IS NOT NULL THEN 1 ELSE 0 END) as qte_sorti
            FROM materiels m
            INNER JOIN modele_materiels mm ON mm.id = m.modele_materiel_id
            INNER JOIN receptions r ON r.id = m.reception_id
            WHERE r.contrat_id = ?
            GROUP BY mm.id, mm.nom
            ORDER BY mm.nom
        ", [$id]);

        $modeles = [];
        $totalStock = 0;
        $totalSorti = 0;

        foreach ($results as $row) {
            $modeles[] = [
                'designation' => $row->designation,
                'qte_stock' => (int)$row->qte_stock,
                'qte_sorti' => (int)$row->qte_sorti,
                'total' => (int)$row->total
            ];
            $totalStock += (int)$row->qte_stock;
            $totalSorti += (int)$row->qte_sorti;
        }

        return response()->json([
            'modeles' => $modeles,
            'total_materiels' => $totalStock + $totalSorti,
            'total_modeles' => count($modeles),
            'total_stock' => $totalStock,
            'total_sorti' => $totalSorti
        ]);
    }

    /**
     * API de vérification pour le formulaire
     */
    public function checkContrat(string $numero)
    {
        $contrat = Contrat::where('numero_contrat', $numero)->first();

        if ($contrat) {
            $totalRecu = Reception::where('contrat_id', $contrat->id)->sum('unite');

            return response()->json([
                'exists'      => true,
                'fournisseur' => $contrat->fournisseur,
                'total_prevu' => (int) $contrat->quantite_totale_prevue,
                'deja_recu'   => (int) $totalRecu,
                'scan_contrat'=> $contrat->scan_contrat,
            ]);
        }
        return response()->json(['exists' => false]);
    }

    /**
     * Téléchargement du scan physique
     */
    public function downloadContrat(int $id)
    {
        $contrat = Contrat::findOrFail($id);

        if (!$contrat->scan_contrat) {
            return back()->with('error', 'Aucun fichier associé.');
        }

        $path = storage_path('app/public/' . $contrat->scan_contrat);

        if (!file_exists($path)) {
            return back()->with('error', 'Fichier introuvable sur le serveur.');
        }

        $safeName = str_replace(['/', '\\'], '-', $contrat->numero_contrat);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $fileName = "Contrat_" . $safeName . "." . $extension;

        if (ob_get_length()) ob_end_clean();

        return response()->download($path, $fileName);
    }

    /**
     * Export PDF Global (contrat complet avec TOUTES les réceptions)
     */
    public function exportPdf(int $id)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $contrat = Contrat::with('receptions')->findOrFail($id);

        $results = DB::select("
            SELECT 
                r.date_livraison,
                mm.nom as designation,
                SUM(CASE WHEN m.demande_id IS NULL THEN 1 ELSE 0 END) as qte_stock,
                SUM(CASE WHEN m.demande_id IS NOT NULL THEN 1 ELSE 0 END) as qte_sorti,
                COUNT(*) as total
            FROM receptions r
            JOIN materiels m ON m.reception_id = r.id
            LEFT JOIN modele_materiels mm ON m.modele_materiel_id = mm.id
            WHERE r.contrat_id = ?
            GROUP BY r.date_livraison, mm.nom
            ORDER BY r.date_livraison ASC, mm.nom ASC
        ", [$id]);

        $receptionsGroupedArray = [];
        $globalGrouped = [];
        $globalStock = 0;
        $globalSorti = 0;
        $globalTotal = 0;

        foreach ($results as $row) {
            $dateKey = $row->date_livraison ?? 'date_inconnue';
            
            if (!isset($receptionsGroupedArray[$dateKey])) {
                $receptionsGroupedArray[$dateKey] = [
                    'date_livraison' => $row->date_livraison ? \Carbon\Carbon::parse($row->date_livraison) : null,
                    'groupes' => [],
                    'total_materiels' => 0,
                    'total_modeles' => 0,
                    'total_stock' => 0,
                    'total_sorti' => 0
                ];
            }
            
            $designation = $row->designation ?? 'Modèle inconnu';
            $qte_stock = (int)$row->qte_stock;
            $qte_sorti = (int)$row->qte_sorti;
            $total = (int)$row->total;

            $receptionsGroupedArray[$dateKey]['groupes'][] = [
                'designation' => $designation,
                'qte_stock' => $qte_stock,
                'qte_sorti' => $qte_sorti,
                'total' => $total
            ];
            
            $receptionsGroupedArray[$dateKey]['total_materiels'] += $total;
            $receptionsGroupedArray[$dateKey]['total_stock'] += $qte_stock;
            $receptionsGroupedArray[$dateKey]['total_sorti'] += $qte_sorti;
            $receptionsGroupedArray[$dateKey]['total_modeles']++;

            if (!isset($globalGrouped[$designation])) {
                $globalGrouped[$designation] = [
                    'designation' => $designation,
                    'qte_stock' => 0,
                    'qte_sorti' => 0,
                    'total' => 0
                ];
            }
            $globalGrouped[$designation]['qte_stock'] += $qte_stock;
            $globalGrouped[$designation]['qte_sorti'] += $qte_sorti;
            $globalGrouped[$designation]['total'] += $total;

            $globalStock += $qte_stock;
            $globalSorti += $qte_sorti;
            $globalTotal += $total;
        }

        $firstReception = $contrat->receptions->first();
        $dateLivraison = $firstReception && $firstReception->date_livraison
            ? $firstReception->date_livraison->format('d/m/Y')
            : now()->format('d/m/Y');

        $pdf = Pdf::loadView('pdf.inventaire_contrat', [
            'reception' => (object)[
                'numero_contrat' => $contrat->numero_contrat,
                'fournisseur' => $contrat->fournisseur,
                'date_livraison' => $dateLivraison,
            ],
            'receptions_grouped' => array_values($receptionsGroupedArray),
            'groupes' => array_values($globalGrouped),
            'total_materiels' => $globalTotal,
            'total_modeles' => count($globalGrouped),
            'total_stock' => $globalStock,
            'total_sorti' => $globalSorti,
            'date' => now()->format('d/m/Y H:i'),
            'is_global' => true,
            'nb_receptions' => $contrat->receptions->count()
        ])->setPaper('a4', 'portrait');

        $safeNumeroContrat = str_replace(['/', '\\', ' ', '.'], '-', $contrat->numero_contrat);
        $fileName = "Inventaire_Global_" . $safeNumeroContrat . ".pdf";

        return $pdf->stream($fileName);
    }

    /**
     * Export PDF spécifique à un lot (TOUTES les réceptions de la même date)
     */
    public function exportPdfLot(Request $request, int $lotId)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $firstReception = Reception::with(['contrat'])->findOrFail($lotId);
        $dateLivraison = $firstReception->date_livraison;
        $contratId = $firstReception->contrat_id;

        $receptions = Reception::where('contrat_id', $contratId)
            ->whereDate('date_livraison', $dateLivraison)
            ->get();

        $results = DB::select("
            SELECT 
                mm.nom as designation,
                SUM(CASE WHEN m.demande_id IS NULL THEN 1 ELSE 0 END) as qte_stock,
                SUM(CASE WHEN m.demande_id IS NOT NULL THEN 1 ELSE 0 END) as qte_sorti,
                COUNT(*) as total
            FROM receptions r
            JOIN materiels m ON m.reception_id = r.id
            LEFT JOIN modele_materiels mm ON m.modele_materiel_id = mm.id
            WHERE r.contrat_id = ? AND DATE(r.date_livraison) = DATE(?)
            GROUP BY mm.nom
        ", [$contratId, $dateLivraison]);

        $groupedModeles = [];
        $totalStock = 0;
        $totalSorti = 0;
        $totalMateriels = 0;

        foreach ($results as $row) {
            $designation = $row->designation ?? 'Modèle inconnu';
            $qte_stock = (int)$row->qte_stock;
            $qte_sorti = (int)$row->qte_sorti;
            $total = (int)$row->total;

            $groupedModeles[] = [
                'designation' => $designation,
                'qte_stock' => $qte_stock,
                'qte_sorti' => $qte_sorti,
                'total' => $total
            ];

            $totalStock += $qte_stock;
            $totalSorti += $qte_sorti;
            $totalMateriels += $total;
        }

        $pdf = Pdf::loadView('pdf.inventaire_contrat', [
            'reception' => (object)[
                'numero_contrat' => $firstReception->contrat->numero_contrat,
                'fournisseur' => $firstReception->contrat->fournisseur,
                'date_livraison' => $dateLivraison,
            ],
            'groupes' => array_values($groupedModeles),
            'total_materiels' => $totalMateriels,
            'total_modeles' => count($groupedModeles),
            'total_stock' => $totalStock,
            'total_sorti' => $totalSorti,
            'date' => now()->format('d/m/Y H:i'),
            'is_lot' => true,
            'nb_receptions' => $receptions->count()
        ])->setPaper('a4', 'portrait');

        $safeNum = str_replace(['/', '\\', ' ', '.'], '-', $firstReception->contrat->numero_contrat);
        $fileName = "Lot_" . $safeNum . "_" . date('Ymd', strtotime($dateLivraison)) . ".pdf";

        return $pdf->stream($fileName);
    }
}
