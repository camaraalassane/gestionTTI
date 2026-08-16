<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\Categorie;
use App\Models\Reception;
use App\Models\MaterielSupprime;
use App\Models\Contrat;
use App\Models\PieceMateriel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class MaterielController extends Controller
{
    /**
     * 0. Vérification de la clé d'accès
     */
    public function verifyAccess(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && $request->code === $user->code_materiel) {
            $request->session()->put('material_access_granted', true);
            return redirect()->intended(route('materiel.indexmat'));
        }
        return back()->withErrors(['code' => 'Code de sécurité incorrect.']);
    }

    /**
     * 1. Vue d'ensemble (Dashboard Inventaire)
     */
    public function index()
    {
        return Inertia::render('materiel/indexmat', [
            'categories' => Categorie::query()
                ->select('id', 'nom')
                ->withCount(['materiels as stock_reel' => function($query) {
                    $query->where('etat', 'Disponible');
                }])->get()
        ]);
    }

    /**
     * 2. Liste détaillée (OPTIMISÉE) - Avec recherche sur catégories ET modèles
     */
    public function list(Request $request)
    {
        $search = $request->input('search');

        // Requête SQL directe avec recherche élargie
        $sql = "
            SELECT
                c.id as categorie_id,
                c.nom as categorie_nom,
                m.id as modele_id,
                m.nom as modele_nom,
                COUNT(CASE WHEN mat.etat IN ('Disponible', 'En stock') THEN 1 END) as qte_materiel,
                COUNT(CASE WHEN mat.etat = 'Livré' THEN 1 END) as qte_livree,
                COUNT(p.id) as qte_pieces
            FROM categories c
            LEFT JOIN modele_materiels m ON m.categorie_id = c.id
            LEFT JOIN materiels mat ON mat.modele_materiel_id = m.id
            LEFT JOIN pieces_materiels p ON p.materiel_id = mat.id AND p.demande_id IS NULL
        ";

        // Ajouter la clause WHERE si recherche
        if ($search) {
            $sql .= " WHERE c.nom LIKE ? OR m.nom LIKE ?";
        }

        $sql .= " GROUP BY c.id, c.nom, m.id, m.nom
                  HAVING COUNT(m.id) > 0
                  ORDER BY c.nom, m.nom";

        // Exécuter avec ou sans paramètres
        $results = $search
            ? DB::select($sql, ["%{$search}%", "%{$search}%"])
            : DB::select($sql);

        // Regrouper par catégorie
        $categoriesData = [];
        foreach ($results as $row) {
            $categorieKey = $row->categorie_id;
            if (!isset($categoriesData[$categorieKey])) {
                $categoriesData[$categorieKey] = [
                    'id' => $row->categorie_id,
                    'nom' => $row->categorie_nom,
                    'modeleMateriels' => []
                ];
            }
            if ($row->modele_id) {
                $categoriesData[$categorieKey]['modeleMateriels'][] = [
                    'id' => $row->modele_id,
                    'nom' => $row->modele_nom,
                    'qte_materiel' => (int)$row->qte_materiel,
                    'qte_livree' => (int)$row->qte_livree,
                    'qte_pieces' => (int)$row->qte_pieces
                ];
            }
        }

        // Filtrer les catégories sans modèles
        $categoriesList = array_values(array_filter($categoriesData, function($cat) {
            return !empty($cat['modeleMateriels']);
        }));

        // Pagination (5 catégories par page)
        $perPage = 5;
        $currentPage = request()->get('page', 1);
        $paginatedCategories = collect($categoriesList)->forPage($currentPage, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedCategories,
            count($categoriesList),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $formattedData = [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'per_page' => $paginated->perPage(),
        ];

        return Inertia::render('materiel/listemateriel', [
            'categories' => $formattedData,
            'stats' => $this->getGlobalStats(),
            'filters' => $request->only(['search']),
            'categoriesList' => Categorie::all(['id', 'nom']),
        ]);
    }

    /**
     * Optimisation : Toutes les stats en 1 seule requête
     */
    private function getGlobalStats()
    {
        $mStats = Materiel::selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN etat IN ('Disponible', 'En stock') AND service_id IS NULL THEN 1 END) as disponible,
            COUNT(CASE WHEN etat = 'En attente' THEN 1 END) as en_attente,
            COUNT(CASE WHEN etat = 'Livré' THEN 1 END) as livres
        ")->first();

        return [
            'total'          => $mStats->total ?? 0,
            'disponible'     => $mStats->disponible ?? 0,
            'en_attente'     => $mStats->en_attente ?? 0,
            'livres'         => $mStats->livres ?? 0,
            'pieces_sorties' => DB::table('pieces_materiels')->whereNotNull('demande_id')->count(),
        ];
    }

    /**
     * Vérifier si un numéro de série existe
     * @param string $sn
     */
    public function checkSn(string $sn)
    {
        $exists = Materiel::where('numero_serie', $sn)->exists();

        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * Recherche des modèles existants pour la réception de stock
     */
    public function searchModelesReception(Request $request)
    {
        $search = trim($request->get('q', ''));
        $categorieId = $request->get('categorie_id');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $query = \App\Models\ModeleMateriel::query();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(nom) LIKE ?', ["%" . strtolower($search) . "%"]);
            });
        }

        if ($categorieId) {
            $query->where('categorie_id', $categorieId);
        }

        $modeles = $query->orderBy('nom', 'asc')
                         ->limit(10)
                         ->withCount(['pieces as pieces_count'])
                         ->get(['id', 'nom', 'categorie_id']);

        return response()->json($modeles);
    }

    /**
     * Stockage groupé - CHAQUE SAISIE CRÉE UNE NOUVELLE RÉCEPTION
     */
    public function store_group(Request $request)
    {
        $request->validate([
            'fournisseur'      => 'required|string|max:255',
            'numero_contrat'   => 'required|string|max:255',
            'items'            => 'required|array',
            'ancien_scan_path' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {

                $contrat = \App\Models\Contrat::firstOrCreate(
                    ['numero_contrat' => $request->numero_contrat],
                    [
                        'fournisseur' => $request->fournisseur,
                        'quantite_totale_prevue' => $request->quantite_totale_prevue ?? 0
                    ]
                );

                $scanPath = $request->hasFile('scan_contrat')
                    ? $request->file('scan_contrat')->store('contrats/' . date('Y'), 'public')
                    : ($request->ancien_scan_path ?? $contrat->scan_contrat);

                if ($request->hasFile('scan_contrat')) {
                    $contrat->update(['scan_contrat' => $scanPath]);
                }

                $totalCree = 0;
                $allPiecesPayload = [];
                $dateLivraison = $request->date_livraison ?? now();

                foreach ($request->items as $item) {

                    if (isset($item['modele_id']) && !empty($item['modele_id'])) {
                        $modele = \App\Models\ModeleMateriel::find($item['modele_id']);
                        if (!$modele) {
                            $modele = \App\Models\ModeleMateriel::firstOrCreate([
                                'nom' => $item['designation'],
                                'categorie_id' => $item['categorie_id']
                            ]);
                        }
                    } else {
                        $modele = \App\Models\ModeleMateriel::firstOrCreate(
                            [
                                'nom' => $item['designation'],
                                'categorie_id' => $item['categorie_id']
                            ]
                        );
                    }

                    $reception = \App\Models\Reception::create([
                        'contrat_id'     => $contrat->id,
                        'numero_contrat' => $request->numero_contrat,
                        'fournisseur'    => $request->fournisseur,
                        'date_livraison' => $dateLivraison,
                        'categorie_id'   => $item['categorie_id'],
                        'unite'          => (int)$item['unite'],
                        'somme'          => (int)$item['unite'],
                        'scan_contrat'   => $scanPath
                    ]);

                    $materielsPayload = [];
                    $snsForItem = [];
                    for ($i = 0; $i < (int)$item['unite']; $i++) {
                        $sn = "SN-" . strtoupper(Str::random(5));
                        $snsForItem[] = $sn;
                        $materielsPayload[] = [
                            'modele_materiel_id' => $modele->id,
                            'numero_serie'       => $sn,
                            'categorie_id'       => $item['categorie_id'],
                            'reception_id'       => $reception->id,
                            'etat'               => 'Disponible',
                            'statut'             => 'Neuf',
                            'created_at'         => now(),
                            'updated_at'         => now()
                        ];
                        $totalCree++;
                    }

                    // Insertion de masse des matériels (par lots de 500 max)
                    foreach (array_chunk($materielsPayload, 500) as $chunk) {
                        DB::table('materiels')->insert($chunk);
                    }

                    // Si des pièces sont définies, on récupère les IDs des matériels insérés via leur numéro de série
                    if (!empty($item['pieces_modeles'])) {
                        $insertedMateriels = DB::table('materiels')
                            ->whereIn('numero_serie', $snsForItem)
                            ->get(['id', 'numero_serie']);

                        foreach ($insertedMateriels as $mat) {
                            foreach ($item['pieces_modeles'] as $mod) {
                                $allPiecesPayload[] = [
                                    'materiel_id'        => $mat->id,
                                    'modele_materiel_id' => $modele->id,
                                    'nom_piece'          => $mod['nom'] ?? "Composant",
                                    'numero_serie'       => "P-SN-" . strtoupper(Str::random(4)),
                                    'statut'             => 'En Stock',
                                    'created_at'         => now(),
                                    'updated_at'         => now()
                                ];
                            }
                        }
                    }
                }

                if (!empty($allPiecesPayload)) {
                    foreach (array_chunk($allPiecesPayload, 500) as $chunk) {
                        DB::table('pieces_materiels')->insert($chunk);
                    }
                }

                return redirect()->route('materiel.indexmat')
                                 ->with('success', "Succès : $totalCree matériels ajoutés.");
            });
        } catch (\Exception $e) {
            Log::error('Erreur store_group: ' . $e->getMessage());
            return back()->with('error', "Erreur : " . $e->getMessage());
        }
    }

    /**
     * Formulaire d'édition
     * @param int $id
     */
    public function edit(int $id)
    {
        $materiel = Materiel::with(['reception', 'categorie', 'demande', 'pieces', 'modele'])
            ->findOrFail($id);

        return Inertia::render('materiel/edit', [
            'materiel' => $materiel,
            'categories' => Categorie::all(['id', 'nom'])
        ]);
    }

    /**
     * Mise à jour d'un modèle et de ses exemplaires
     * @param Request $request
     * @param int $modeleId
     */
    public function updateModele(Request $request, int $modeleId)
    {
        Log::info('=== UPDATE MODELE ===');
        Log::info('Modele ID: ' . $modeleId);

        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'categorie_id' => 'required|exists:categories,id',
                'description' => 'nullable|string',
            ]);

            $modele = \App\Models\ModeleMateriel::findOrFail($modeleId);

            DB::transaction(function () use ($modele, $validated) {
                $modele->update([
                    'nom' => $validated['nom'],
                    'categorie_id' => $validated['categorie_id'],
                ]);

                \App\Models\Materiel::where('modele_materiel_id', $modele->id)
                    ->update([
                        'categorie_id' => $validated['categorie_id'],
                        'description' => $validated['description'] ?? null,
                    ]);

                $receptionIds = \App\Models\Reception::whereHas('materiels', function($q) use ($modele) {
                    $q->where('modele_materiel_id', $modele->id);
                })->pluck('id');

                if ($receptionIds->isNotEmpty()) {
                    \App\Models\Reception::whereIn('id', $receptionIds)->update(['categorie_id' => $validated['categorie_id']]);
                }

                Log::info('Modèle, exemplaires et réceptions mis à jour');
            });

            return back()->with('success', 'Modèle, stock et réceptions mis à jour');

        } catch (\Exception $e) {
            Log::error('Erreur update modèle: ' . $e->getMessage());
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Suppression et archivage
     * @param int $id
     */
    public function destroy(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $materielSource = Materiel::with('modele')->findOrFail($id);
                $modeleId = $materielSource->modele_materiel_id;

                $count = Materiel::where('modele_materiel_id', $modeleId)
                    ->whereIn('etat', ['Disponible', 'En stock'])
                    ->whereNull('demande_id')
                    ->count();

                Materiel::where('modele_materiel_id', $modeleId)
                    ->whereIn('etat', ['Disponible', 'En stock'])
                    ->whereNull('demande_id')
                    ->delete();

                \App\Models\MaterielSupprime::create([
                    'nom' => $materielSource->modele->nom ?? "Modèle ID: " . $modeleId,
                    'numero_serie' => "GROUPE-STK-" . $count,
                    'categorie' => $materielSource->categorie->nom ?? 'Inconnue',
                    'par_utilisateur' => Auth::user()->name ?? 'Système',
                    'supprime_le' => now(),
                ]);

                return back()->with('success', "Suppression effectuée avec succès");
            });
        } catch (\Exception $e) {
            Log::error('Erreur destroy: ' . $e->getMessage());
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Historique des sorties
     * @param int $id
     */
    public function historique(int $id)
    {
        $materiels = Materiel::with([
            'modele',
            'demande',
            'pieces' => function($q) {
                $q->with('demande');
            }
        ])
        ->where('modele_materiel_id', $id)
        ->where('etat', 'Livré')
        ->get();

        $piecesSeules = PieceMateriel::where('modele_materiel_id', $id)
            ->whereNotNull('demande_id')
            ->whereDoesntHave('materiel', function($q) {
                $q->where('etat', 'Livré');
            })
            ->with(['demande', 'materiel.modele'])
            ->get();

        $groupedByCommande = [];

        foreach ($materiels as $materiel) {
            if (!$materiel->demande) continue;

            $numCom = $materiel->demande->numcomande;

            if (!isset($groupedByCommande[$numCom])) {
                $groupedByCommande[$numCom] = [
                    'numcomande' => $numCom,
                    'date' => $materiel->demande->date_demande,
                    'service' => $materiel->demande->service_beneficiaire,
                    'demandeur' => $materiel->demande->demandeur_nom,
                    'materiels' => [],
                    'pieces_seules' => []
                ];
            }

            $groupedByCommande[$numCom]['materiels'][] = [
                'id' => $materiel->id,
                'numero_serie' => $materiel->numero_serie,
                'nom_modele' => $materiel->modele->nom ?? 'Modèle inconnu',
                'pieces' => $materiel->pieces->map(function($p) {
                    return [
                        'id' => $p->id,
                        'nom' => $p->nom_piece,
                        'sn' => $p->numero_serie,
                    ];
                })
            ];
        }

        foreach ($piecesSeules as $piece) {
            if (!$piece->demande) continue;

            $numCom = $piece->demande->numcomande;

            if (!isset($groupedByCommande[$numCom])) {
                $groupedByCommande[$numCom] = [
                    'numcomande' => $numCom,
                    'date' => $piece->demande->date_demande,
                    'service' => $piece->demande->service_beneficiaire,
                    'demandeur' => $piece->demande->demandeur_nom,
                    'materiels' => [],
                    'pieces_seules' => []
                ];
            }

            $groupedByCommande[$numCom]['pieces_seules'][] = [
                'id' => $piece->id,
                'nom' => $piece->nom_piece,
                'sn' => $piece->numero_serie,
            ];
        }

        $groupedByCommande = collect($groupedByCommande)->sortByDesc('date')->values();

        return response()->json($groupedByCommande);
    }

    /**
     * Export PDF historique
     * @param int $id
     */
    public function exportHistorique(int $id)
    {
        $modele = \App\Models\ModeleMateriel::find($id);
        $nom = $modele ? $modele->nom : 'Historique';

        $historique = Materiel::with([
            'modele',
            'demande',
            'pieces'
        ])
        ->where('modele_materiel_id', $id)
        ->where('etat', 'Livré')
        ->orderBy('updated_at', 'desc')
        ->get();

        $pdf = Pdf::loadView('pdf.historique_modele', [
            'nom' => $nom,
            'historique' => $historique,
            'date' => now()->format('d/m/Y')
        ]);

        return $pdf->download("Historique_Sorties_{$nom}.pdf");
    }

    /**
     * Export PDF du stock magasin - Version optimisée
     * @param Request $request
     */
    public function export(Request $request)
    {
        $filters = $request->only(['search']);

        $results = DB::select("
            SELECT
                c.id as categorie_id,
                c.nom as categorie_nom,
                m.id as modele_id,
                m.nom as modele_nom,
                COUNT(CASE WHEN mat.etat IN ('Disponible', 'En stock') AND mat.service_id IS NULL THEN 1 END) as qte_materiel,
                COUNT(CASE WHEN mat.etat = 'Livré' THEN 1 END) as qte_livree,
                COUNT(p.id) as qte_pieces
            FROM categories c
            LEFT JOIN modele_materiels m ON m.categorie_id = c.id
            LEFT JOIN materiels mat ON mat.modele_materiel_id = m.id
            LEFT JOIN pieces_materiels p ON p.materiel_id = mat.id AND p.demande_id IS NULL
            GROUP BY c.id, c.nom, m.id, m.nom
            HAVING COUNT(m.id) > 0
            ORDER BY c.nom, m.nom
        ");

        $categoriesData = [];
        foreach ($results as $row) {
            $categorieKey = $row->categorie_id;
            if (!isset($categoriesData[$categorieKey])) {
                $categoriesData[$categorieKey] = [
                    'id' => $row->categorie_id,
                    'nom' => $row->categorie_nom,
                    'modeleMateriels' => []
                ];
            }
            if ($row->modele_id) {
                $categoriesData[$categorieKey]['modeleMateriels'][] = [
                    'id' => $row->modele_id,
                    'nom' => $row->modele_nom,
                    'qte_materiel' => (int)$row->qte_materiel,
                    'qte_livree' => (int)$row->qte_livree,
                    'qte_pieces' => (int)$row->qte_pieces
                ];
            }
        }

        $categoriesList = array_values($categoriesData);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            foreach ($categoriesList as &$categorie) {
                $categorie['modeleMateriels'] = array_filter($categorie['modeleMateriels'], function($modele) use ($search) {
                    return str_contains(strtolower($modele['nom']), $search);
                });
            }
            $categoriesList = array_filter($categoriesList, function($categorie) {
                return !empty($categorie['modeleMateriels']);
            });
            $categoriesList = array_values($categoriesList);
        }

        $totalMateriels = 0;
        $totalDisponible = 0;
        $totalLivres = 0;
        $totalPieces = 0;

        foreach ($categoriesList as $categorie) {
            foreach ($categorie['modeleMateriels'] as $modele) {
                $totalMateriels += $modele['qte_materiel'] + $modele['qte_livree'];
                $totalDisponible += $modele['qte_materiel'];
                $totalLivres += $modele['qte_livree'];
                $totalPieces += $modele['qte_pieces'];
            }
        }

        $data = [
            'categories' => $categoriesList,
            'stats' => [
                'total' => $totalMateriels,
                'disponible' => $totalDisponible,
                'livres' => $totalLivres,
                'pieces_sorties' => $totalPieces,
                'en_attente' => 0
            ],
            'date' => now()->format('d/m/Y H:i'),
            'filters' => $filters
        ];

        $pdf = Pdf::loadView('pdf.inventaire_global', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("Inventaire_Magasin_" . now()->format('Ymd') . ".pdf");
    }

    /**
     * Helper pour le nom des mois
     * @param int|string $num
     */
    private function getNomMois($num): string
    {
        $mois = [
            1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin',
            7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'
        ];
        return $mois[(int)$num] ?? '';
    }
}
