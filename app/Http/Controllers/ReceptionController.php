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
    public function index()
    {
        $contrats = Contrat::with(['receptions.categorie'])
            ->orderBy('created_at', 'desc')
            ->get();

        $contratsData = [];
        foreach ($contrats as $contrat) {
            $categories = [];
            foreach ($contrat->receptions as $reception) {
                if ($reception->categorie && !in_array($reception->categorie->nom, $categories)) {
                    $categories[] = $reception->categorie->nom;
                }
            }

            $firstReception = $contrat->receptions->first();

            $contratsData[] = [
                'id' => $contrat->id,
                'numero_contrat' => $contrat->numero_contrat,
                'fournisseur' => $contrat->fournisseur,
                'date_livraison' => $firstReception ? $firstReception->date_livraison : null,
                'scan_contrat' => $contrat->scan_contrat,
                'created_at' => $contrat->created_at,
                'all_categories' => $categories,
            ];
        }

        $perPage = 5;
        $currentPage = request()->get('page', 1);
        $paginatedData = collect($contratsData)->forPage($currentPage, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            count($contratsData),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return Inertia::render('materiel/ReceptionContracts', [
            'receptions' => $paginated,
        ]);
    }

    /**
     * Récupère la liste des différents lots pour la traçabilité
     * Utilisé par la Modale 2 - GROUPÉ PAR DATE DE LIVRAISON
     */
    public function getLotsJson($id)
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
    public function getMaterielsJson($id)
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
    public function checkContrat($numero)
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
    public function downloadContrat($id)
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
    public function exportPdf($id)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $contrat = Contrat::with(['receptions.materiels.modele'])->findOrFail($id);

        $receptionsByDate = [];
        $allMateriels = collect();

        foreach ($contrat->receptions as $reception) {
            $dateKey = $reception->date_livraison ? $reception->date_livraison->format('Y-m-d') : 'date_inconnue';

            if (!isset($receptionsByDate[$dateKey])) {
                $receptionsByDate[$dateKey] = [
                    'date_livraison' => $reception->date_livraison,
                    'materiels' => collect(),
                    'reception_ids' => []
                ];
            }

            $receptionsByDate[$dateKey]['materiels'] = $receptionsByDate[$dateKey]['materiels']->concat($reception->materiels);
            $receptionsByDate[$dateKey]['reception_ids'][] = $reception->id;
            $allMateriels = $allMateriels->concat($reception->materiels);
        }

        $receptionsGrouped = [];
        foreach ($receptionsByDate as $dateKey => $data) {
            $groupedModeles = [];
            $totalStock = 0;
            $totalSorti = 0;

            foreach ($data['materiels'] as $materiel) {
                $nomModele = $materiel->modele->nom ?? 'Modèle inconnu';

                if (!isset($groupedModeles[$nomModele])) {
                    $groupedModeles[$nomModele] = [
                        'designation' => $nomModele,
                        'qte_stock' => 0,
                        'qte_sorti' => 0,
                        'total' => 0
                    ];
                }

                if ($materiel->demande_id !== null) {
                    $groupedModeles[$nomModele]['qte_sorti']++;
                    $totalSorti++;
                } else {
                    $groupedModeles[$nomModele]['qte_stock']++;
                    $totalStock++;
                }
                $groupedModeles[$nomModele]['total']++;
            }

            $receptionsGrouped[] = [
                'date_livraison' => $data['date_livraison'],
                'groupes' => array_values($groupedModeles),
                'total_materiels' => $data['materiels']->count(),
                'total_modeles' => count($groupedModeles),
                'total_stock' => $totalStock,
                'total_sorti' => $totalSorti
            ];
        }

        $globalGrouped = [];
        $globalStock = 0;
        $globalSorti = 0;

        foreach ($allMateriels as $materiel) {
            $nomModele = $materiel->modele->nom ?? 'Modèle inconnu';

            if (!isset($globalGrouped[$nomModele])) {
                $globalGrouped[$nomModele] = [
                    'designation' => $nomModele,
                    'qte_stock' => 0,
                    'qte_sorti' => 0,
                    'total' => 0
                ];
            }

            if ($materiel->demande_id !== null) {
                $globalGrouped[$nomModele]['qte_sorti']++;
                $globalSorti++;
            } else {
                $globalGrouped[$nomModele]['qte_stock']++;
                $globalStock++;
            }
            $globalGrouped[$nomModele]['total']++;
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
            'receptions_grouped' => $receptionsGrouped,
            'groupes' => array_values($globalGrouped),
            'total_materiels' => $allMateriels->count(),
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
    public function exportPdfLot(Request $request, $lotId)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $firstReception = Reception::with(['contrat'])->findOrFail($lotId);
        $dateLivraison = $firstReception->date_livraison;
        $contratId = $firstReception->contrat_id;

        $receptions = Reception::with(['materiels.modele'])
            ->where('contrat_id', $contratId)
            ->whereDate('date_livraison', $dateLivraison)
            ->get();

        $materiels = collect();
        foreach ($receptions as $reception) {
            $materiels = $materiels->concat($reception->materiels);
        }

        $groupedModeles = [];
        $totalStock = 0;
        $totalSorti = 0;

        foreach ($materiels as $materiel) {
            $nomModele = $materiel->modele->nom ?? 'Modèle inconnu';

            if (!isset($groupedModeles[$nomModele])) {
                $groupedModeles[$nomModele] = [
                    'designation' => $nomModele,
                    'qte_stock' => 0,
                    'qte_sorti' => 0,
                    'total' => 0
                ];
            }

            if ($materiel->demande_id !== null) {
                $groupedModeles[$nomModele]['qte_sorti']++;
                $totalSorti++;
            } else {
                $groupedModeles[$nomModele]['qte_stock']++;
                $totalStock++;
            }
            $groupedModeles[$nomModele]['total']++;
        }

        $pdf = Pdf::loadView('pdf.inventaire_contrat', [
            'reception' => (object)[
                'numero_contrat' => $firstReception->contrat->numero_contrat,
                'fournisseur' => $firstReception->contrat->fournisseur,
                'date_livraison' => $dateLivraison,
            ],
            'groupes' => array_values($groupedModeles),
            'total_materiels' => $materiels->count(),
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
