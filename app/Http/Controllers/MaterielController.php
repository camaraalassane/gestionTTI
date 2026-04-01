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
     * 2. Liste détaillée (OPTIMISÉE)
     */
    public function list(Request $request)
    {
        $query = \App\Models\ModeleMateriel::query()
            ->select('modele_materiels.*')
            ->join('categories', 'modele_materiels.categorie_id', '=', 'categories.id')
            ->with('categorie:id,nom');

        // Comptage des unités en stock
        $query->withCount([
            'exemplaires as qte_materiel' => fn($q) => $q->whereIn('materiels.etat', ['Disponible', 'En stock'])
        ]);

        // Comptage des unités livrées
        $query->withCount([
            'exemplaires as qte_livree' => fn($q) => $q->where('materiels.etat', 'Livré')
        ]);

        // Comptage des pièces en stock
        $query->withCount([
            'pieces as qte_pieces' => fn($q) => $q->whereNull('pieces_materiels.demande_id')
        ]);

        // Filtre recherche texte uniquement
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function($q) use ($term) {
                $q->where('modele_materiels.nom', 'ilike', "%{$term}%")
                  ->orWhere('categories.nom', 'ilike', "%{$term}%")
                  ->orWhereHas('exemplaires', function($sq) use ($term) {
                      $sq->where('numero_serie', 'ilike', "%{$term}%");
                  });
            });
        }

        // Tri
        $results = $query->orderBy('modele_materiels.categorie_id')
                         ->orderBy('modele_materiels.nom')
                         ->paginate(30)
                         ->withQueryString();

        return Inertia::render('materiel/listemateriel', [
            'materiels'  => $results,
            'categories' => \App\Models\Categorie::all(['id', 'nom']),
            'stats'      => $this->getGlobalStats(),
            'filters'    => $request->only(['search']),
        ]);
    }

    /**
     * Optimisation : Toutes les stats en 1 seule requête
     */
    private function getGlobalStats()
    {
        $mStats = Materiel::selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN etat IN ('Disponible', 'En stock') THEN 1 END) as disponible,
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
     */
    public function checkSn($sn)
    {
        $exists = Materiel::where('numero_serie', $sn)->exists();

        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * Stockage groupé
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

                // 1. Gestion du Contrat
                $contrat = \App\Models\Contrat::firstOrCreate(
                    ['numero_contrat' => $request->numero_contrat],
                    [
                        'fournisseur' => $request->fournisseur,
                        'quantite_totale_prevue' => $request->quantite_totale_prevue ?? 0
                    ]
                );

                // 2. Gestion du Scan
                $scanPath = $request->hasFile('scan_contrat')
                    ? $request->file('scan_contrat')->store('contrats/' . date('Y'), 'public')
                    : ($request->ancien_scan_path ?? $contrat->scan_contrat);

                if ($request->hasFile('scan_contrat')) {
                    $contrat->update(['scan_contrat' => $scanPath]);
                }

                $totalCree = 0;
                $allPiecesPayload = [];

                // 3. Boucle sur les items
                foreach ($request->items as $item) {

                    $modele = \App\Models\ModeleMateriel::firstOrCreate(
                        [
                            'nom'          => $item['designation'],
                            'categorie_id' => $item['categorie_id']
                        ]
                    );

                    // Création de la réception
                    $reception = \App\Models\Reception::create([
                        'contrat_id'     => $contrat->id,
                        'numero_contrat' => $request->numero_contrat,
                        'fournisseur'    => $request->fournisseur,
                        'date_livraison' => $request->date_livraison ?? now(),
                        'categorie_id'   => $item['categorie_id'],
                        'unite'          => (int)$item['unite'],
                        'somme'          => (int)$item['unite'],
                        'scan_contrat'   => $scanPath
                    ]);

                    // 4. Création des exemplaires physiques
                    for ($i = 0; $i < (int)$item['unite']; $i++) {
                        $materiel = \App\Models\Materiel::create([
                            'modele_materiel_id' => $modele->id,
                            'numero_serie'       => "SN-" . strtoupper(Str::random(5)),
                            'categorie_id'       => $item['categorie_id'],
                            'reception_id'       => $reception->id,
                            'etat'               => 'Disponible',
                            'statut'             => 'Neuf',
                        ]);

                        $totalCree++;

                        // Préparation des pièces liées
                        if (!empty($item['pieces_modeles'])) {
                            foreach ($item['pieces_modeles'] as $mod) {
                                $allPiecesPayload[] = [
                                    'materiel_id'        => $materiel->id,
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

                // 5. Insertion massive des pièces
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
     */
    public function edit($id)
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
     */
    public function updateModele(Request $request, $modeleId)
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
                // 1. Mettre à jour le modèle
                $modele->update([
                    'nom' => $validated['nom'],
                    'categorie_id' => $validated['categorie_id'],
                ]);

                // 2. Mettre à jour TOUS les exemplaires
                \App\Models\Materiel::where('modele_materiel_id', $modele->id)
                    ->update([
                        'categorie_id' => $validated['categorie_id'],
                        'description' => $validated['description'] ?? null,
                    ]);

                Log::info('Modèle et exemplaires mis à jour avec succès');
            });

            return back()->with('success', 'Modèle et stock mis à jour avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur update modèle: ' . $e->getMessage());
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Suppression et archivage
     */
    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $materielSource = Materiel::with('modele')->findOrFail($id);
                $modeleId = $materielSource->modele_materiel_id;

                // Comptage du nombre d'exemplaires du même modèle
                $count = Materiel::where('modele_materiel_id', $modeleId)
                    ->whereIn('etat', ['Disponible', 'En stock'])
                    ->whereNull('demande_id')
                    ->count();

                // Suppression de tous les exemplaires disponibles
                Materiel::where('modele_materiel_id', $modeleId)
                    ->whereIn('etat', ['Disponible', 'En stock'])
                    ->whereNull('demande_id')
                    ->delete();

                // Archivage
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
     */
    public function historique($id)
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

        // Traiter les matériels
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

        // Traiter les pièces seules
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
     */
    public function exportHistorique($id)
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
     * Export PDF du stock magasin
     */
    public function export(Request $request)
    {
        $filters = $request->only(['search']);

        $query = Materiel::with([
            'categorie',
            'modele',
            'pieces' => function($q) {
                $q->whereNull('demande_id');
            }
        ])
        ->whereIn('etat', ['Disponible', 'En stock'])
        ->whereNull('demande_id');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('modele', function($modeleQuery) use ($search) {
                    $modeleQuery->where('nom', 'ilike', "%{$search}%");
                })
                ->orWhere('numero_serie', 'ilike', "%{$search}%")
                ->orWhereHas('categorie', function($cat) use ($search) {
                    $cat->where('nom', 'ilike', "%{$search}%");
                });
            });
        }

        $materiels = $query->get();

        if ($materiels->isEmpty()) {
            return back()->with('error', 'Aucun matériel trouvé en stock.');
        }

        $materielsGroupes = $materiels->groupBy(function($item) {
            return $item->categorie->nom ?? 'SANS CATÉGORIE';
        });

        $materielsGroupes = $materielsGroupes->map(function($group) {
            return $group->groupBy(function($item) {
                return $item->modele->nom ?? 'MODÈLE INCONNU';
            });
        });

        $data = [
            'materielsGroupes' => $materielsGroupes,
            'periode' => "STOCK RÉEL MAGASIN",
            'date' => now()->format('d/m/Y'),
            'total' => $materiels->count()
        ];

        $pdf = Pdf::loadView('pdf.inventaire_global', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("Inventaire_Magasin_" . now()->format('Ymd') . ".pdf");
    }

    /**
     * Helper pour le nom des mois
     */
    private function getNomMois($num) {
        $mois = [
            1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin',
            7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'
        ];
        return $mois[(int)$num] ?? '';
    }
}
