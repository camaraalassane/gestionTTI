<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\Materiel;
use App\Models\Service;
use App\Models\PieceMateriel;
use App\Models\ModeleMateriel;
use App\Models\Reception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class DemandeController extends Controller
{
    /**
     * Méthode privée partagée : charge toutes les demandes d'une liste de commandes EN UNE SEULE requête
     * Élimine le problème N+1 présent dans index(), historique() et exportPDF()
     */
    private function chargerDemandesParCommandes(\Illuminate\Support\Collection $commandes): \Illuminate\Support\Collection
    {
        $numCommandes = $commandes->pluck('numcomande');

        return Demande::whereIn('numcomande', $numCommandes)
            ->with(['pieces', 'materiel.pieces'])
            ->get()
            ->groupBy('numcomande');
    }

    /**
     * 1. Liste des demandes "En attente" - GROUPÉE PAR COMMANDE
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $commandes = Demande::query()
            ->select(
                'numcomande',
                DB::raw('MIN(date_demande) as date_demande'),
                DB::raw('MIN(demandeur_nom) as demandeur_nom'),
                DB::raw('MIN(service_beneficiaire) as service_beneficiaire'),
                DB::raw('MIN(statut) as statut')
            )
            ->where('statut', 'En attente')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numcomande', 'like', "%{$search}%")
                      ->orWhere('service_beneficiaire', 'like', "%{$search}%")
                      ->orWhere('demandeur_nom', 'like', "%{$search}%");
                });
            })
            ->groupBy('numcomande')
            ->orderBy('date_demande', 'desc')
            ->paginate(10)
            ->withQueryString();

        $toutesLesDemandes = $this->chargerDemandesParCommandes($commandes->getCollection());

        $commandes->getCollection()->transform(function ($commande) use ($toutesLesDemandes) {
            $demandes = ($toutesLesDemandes->get($commande->numcomande) ?? collect())
                ->map(function ($demande) {
                    return [
                        ...$demande->toArray(),
                        'est_sortie_materiel'    => (int)$demande->nbredemande > 0,
                        'a_des_pieces_au_total'  => $demande->materiel && $demande->materiel->pieces->isNotEmpty(),
                    ];
                });

            return [
                'numcomande'           => $commande->numcomande,
                'date_demande'         => $commande->date_demande,
                'date_affichee'        => $commande->date_demande
                    ? \Carbon\Carbon::parse($commande->date_demande)->format('d/m/Y')
                    : 'Date inconnue',
                'demandeur_nom'        => $commande->demandeur_nom,
                'service_beneficiaire' => $commande->service_beneficiaire,
                'statut'               => $commande->statut,
                'demandes'             => $demandes,
                'total_items'          => $demandes->sum(function ($d) {
                    return $d['est_sortie_materiel'] ? $d['nbredemande'] : count($d['pieces']);
                }),
            ];
        });

        return Inertia::render('demandes/index', [
            'commandes' => $commandes,
            'filters'   => $request->only(['search']),
        ]);
    }

    /**
     * 2. Formulaire de création
     */
    public function create(Request $request)
    {
        return Inertia::render('demandes/create', [
            'services' => Service::select('id', 'nom')->orderBy('nom')->get(),
        ]);
    }

    /**
     * API: Recherche de modèles pour chargement dynamique
     */
    public function searchModeles(Request $request)
    {
        $search = $request->get('search', '');

        $query = ModeleMateriel::query()
            ->select('id', 'nom')
            ->withCount([
                'exemplaires as total_materiels' => function ($q) {
                    $q->whereNull('demande_id')->whereIn('etat', ['Disponible', 'En stock']);
                },
            ]);

        if ($search) {
            $query->where('nom', 'ilike', "%{$search}%");
        }

        return response()->json($query->get());
    }

    /**
     * API: Récupérer TOUS les matériels disponibles d'un modèle
     */
    public function getMaterielsByModele(int $modele_id)
    {
        $materiels = Materiel::where('modele_materiel_id', $modele_id)
            ->whereNull('demande_id')
            ->whereIn('etat', ['Disponible', 'En stock'])
            ->with(['modele', 'pieces' => function ($q) {
                $q->whereNull('demande_id');
            }])
            ->get();

        return response()->json($materiels);
    }

    /**
     * 3. Enregistrement du Panier - ÉVITE LES DOUBLONS DE COMMANDE
     */
    public function store_group(Request $request)
    {
        $validated = $request->validate([
            'demandeur_nom'            => 'required',
            'service_beneficiaire'     => 'required',
            'date_demande'             => 'required|date',
            'items'                    => 'required|array',
            'items.*.materiel_id'      => 'required',
            'items.*.numero_serie'     => 'nullable|string',
            'items.*.mode_sortie'      => 'required|in:unite,pieces,complet',
            'items.*.pieces_ids'       => 'sometimes|array',
            'items.*.pieces_details'   => 'sometimes|array',
            'items.*.description'      => 'nullable|string',
            'items.*.quantite'         => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $annee  = date('Y');
                $prefix = "CMD-{$annee}-";

                $derniere = Demande::where('numcomande', 'like', $prefix . '%')
                    ->orderBy('numcomande', 'desc')
                    ->lockForUpdate()
                    ->value('numcomande');

                $dernierNum = $derniere
                    ? (int) str_replace($prefix, '', $derniere)
                    : 0;

                $numCmd = $prefix . str_pad($dernierNum + 1, 4, '0', STR_PAD_LEFT);

                Log::info("Création commande : {$numCmd}");

                // Pré-charger tous les matériels pour éviter les requêtes N+1
                $materielIds = collect($validated['items'])->pluck('materiel_id')->unique()->toArray();
                $materiels = Materiel::with(['modele', 'categorie'])->whereIn('id', $materielIds)->get()->keyBy('id');

                foreach ($validated['items'] as $item) {
                    $mat = $materiels->get($item['materiel_id']);
                    
                    if (!$mat) {
                        throw new \Exception("Matériel introuvable ID: {$item['materiel_id']}");
                    }

                    if (!empty($item['numero_serie']) && $item['mode_sortie'] !== 'pieces') {
                        $mat->update(['numero_serie' => $item['numero_serie']]);
                    }

                    $quantiteMateriel = ($item['mode_sortie'] === 'pieces') ? 0 : $item['quantite'];
                    $nomMateriel      = $mat->modele ? $mat->modele->nom : $mat->nom;
                    $modeleMaterielId = $mat->modele_materiel_id ?? $mat->modele->id ?? null;

                    if (!$modeleMaterielId) {
                        throw new \Exception("Impossible de déterminer le modèle du matériel ID: {$mat->id}");
                    }

                    $demande = Demande::create([
                        'numcomande'           => $numCmd,
                        'materiel_id'          => $mat->id,
                        'modele_materiel_id'   => $modeleMaterielId,
                        'nom_materiel'         => $nomMateriel,
                        'nbredemande'          => $quantiteMateriel,
                        'numero_serie'         => $item['numero_serie'] ?? $mat->numero_serie,
                        'categorie'            => $mat->categorie->nom ?? 'N/A',
                        'demandeur_nom'        => $validated['demandeur_nom'],
                        'service_beneficiaire' => $validated['service_beneficiaire'],
                        'date_demande'         => $validated['date_demande'],
                        'statut'               => 'En attente',
                        'description'          => $item['description'] ?? '',
                    ]);

                    if (!empty($item['pieces_details'])) {
                        foreach ($item['pieces_details'] as $pDetail) {
                            if (isset($pDetail['id'])) {
                                DB::table('pieces_materiels')->where('id', $pDetail['id'])->update([
                                    'numero_serie' => $pDetail['numero_serie'] ?? null,
                                    'demande_id'   => $demande->id,
                                    'statut'       => 'En attente',
                                ]);
                            }
                        }
                    }

                    if ($item['mode_sortie'] === 'unite' || $item['mode_sortie'] === 'complet') {
                        $mat->update([
                            'demande_id' => $demande->id,
                            'etat'       => 'En attente',
                        ]);
                    }
                }

                return redirect()->route('demandes.index');
            });
        } catch (\Exception $e) {
            Log::error('Erreur store_group: ' . $e->getMessage());
            return back()->with('error', "Erreur lors de l'enregistrement : " . $e->getMessage());
        }
    }

    /**
     * 4. Validation (Mise à jour avec Verrouillage)
     */
    public function validerGroupe(Request $request)
    {
        $ids = $request->input('ids');
        if (empty($ids)) return back()->with('error', "Aucune sélection.");

        try {
            return DB::transaction(function () use ($ids) {
                $demandes = Demande::with('materiel')
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get();

                // Pré-charger les services
                $services = Service::whereIn('nom', $demandes->pluck('service_beneficiaire')->unique()->filter())->get()->keyBy('nom');
                
                // Mettre à jour toutes les pièces associées en une seule requête
                PieceMateriel::whereIn('demande_id', $demandes->pluck('id'))->update(['statut' => 'Livré']);

                foreach ($demandes as $demande) {
                    if ($demande->statut !== 'En attente') {
                        continue;
                    }

                    $service = $services->get($demande->service_beneficiaire);

                    if ((int)$demande->nbredemande > 0 && $demande->materiel && $demande->materiel->demande_id == $demande->id) {
                        $demande->materiel->update([
                            'etat'       => 'Livré',
                            'service_id' => $service ? $service->id : $demande->materiel->service_id,
                        ]);
                    } elseif ((int)$demande->nbredemande == 0 && $demande->materiel) {
                        $demande->materiel->update([
                            'demande_id' => null,
                            'etat'       => 'Disponible',
                        ]);
                    }

                    $demande->update(['statut' => 'Validé']);
                }

                return back()->with('success', "Validation terminée.");
            });
        } catch (\Exception $e) {
            return back()->with('error', "Erreur : " . $e->getMessage());
        }
    }

   /**
 * 5. Gestion par Service - TOUTES LES DEMANDES SANS PAGINATION
 */
public function gestionService(Request $request)
{
    $query = Demande::where('statut', '!=', 'Clôturé')
        ->with(['pieces:id,demande_id,nom_piece,numero_serie', 'materiel.pieces'])
        ->select(
            'id', 'materiel_id', 'nom_materiel', 'numero_serie',
            'service_beneficiaire', 'statut', 'nbredemande',
            'demandeur_nom', 'description', 'date_demande'
        )
        // ✅ Trier par service d'abord pour avoir tous les services
        ->orderBy('service_beneficiaire', 'asc')
        ->orderBy('date_demande', 'desc');

    if ($request->filled('service')) {
        $query->where('service_beneficiaire', $request->service);
    }

    if ($request->filled('statut')) {
        $query->where('statut', $request->statut);
    }

    // ✅ Récupérer TOUTES les demandes (pas de pagination)
    $demandes = $query->get();

    // Transformer les données
    $demandes->transform(function ($demande) {
        $demande->est_uniquement_piece    = (int)$demande->nbredemande == 0;
        $demande->a_des_pieces_au_total   = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
        $demande->date_affichee           = $demande->date_demande
            ? \Carbon\Carbon::parse($demande->date_demande)->format('d/m/Y')
            : 'N/A';
        return $demande;
    });

    // ✅ Récupérer TOUS les services avec demandes (indépendamment de la pagination)
    $servicesAvecDemandes = Demande::where('statut', '!=', 'Clôturé')
        ->whereNotNull('service_beneficiaire')
        ->distinct()
        ->pluck('service_beneficiaire')
        ->filter()
        ->values()
        ->toArray();

    // ✅ Récupérer les totaux par service
    $servicesTotaux = Demande::where('statut', '!=', 'Clôturé')
        ->whereNotNull('service_beneficiaire')
        ->select('service_beneficiaire', DB::raw('COUNT(*) as total'))
        ->groupBy('service_beneficiaire')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->service_beneficiaire => $item->total];
        })
        ->toArray();

    // ✅ Récupérer TOUS les services
    $tousLesServices = Service::select('id', 'nom')->orderBy('nom')->get();

    return Inertia::render('demandes/GestionService', [
        'demandes' => $demandes,
        'services' => $tousLesServices,
        'services_actifs' => $servicesAvecDemandes,
        'services_totaux' => $servicesTotaux,
        'filters'  => $request->only(['service', 'statut']),
    ]);
}

    /**
     * 6. Clôturer / Archiver - AVEC DÉDUCTION STOCK SUR LES RÉCEPTIONS
     */
    public function cloturer_groupe(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids || !is_array($ids)) return back()->with('error', 'Sélection invalide.');

        try {
            DB::transaction(function () use ($ids) {
                $demandes = Demande::with('materiel')->whereIn('id', $ids)->get();

                $totauxParModele = [];
                foreach ($demandes as $demande) {
                    $quantite = (int)$demande->nbredemande;
                    if ($quantite > 0 && $demande->materiel) {
                        $modeleId = $demande->materiel->modele_materiel_id;
                        $totauxParModele[$modeleId] = ($totauxParModele[$modeleId] ?? 0) + $quantite;
                    }
                }

                foreach ($totauxParModele as $modeleId => $quantiteTotaleRequise) {
                    $stockDisponible = Materiel::where('modele_materiel_id', $modeleId)
                        ->whereNull('service_id')
                        ->whereIn('etat', ['Disponible', 'En stock'])
                        ->count();

                    if ($stockDisponible < $quantiteTotaleRequise) {
                        $nomModele = ModeleMateriel::find($modeleId)?->nom ?? "ID: $modeleId";
                        throw new \Exception("Stock insuffisant pour le modèle: $nomModele. Disponible: $stockDisponible, Demandé: $quantiteTotaleRequise");
                    }
                }

                foreach ($totauxParModele as $modeleId => $quantiteTotaleRequise) {
                    $receptions = Reception::whereHas('materiels', function ($q) use ($modeleId) {
                            $q->where('modele_materiel_id', $modeleId);
                        })
                        ->where('somme', '>', 0)
                        ->orderBy('date_livraison', 'asc')
                        ->lockForUpdate()
                        ->get();

                    $resteADeduire = $quantiteTotaleRequise;

                    foreach ($receptions as $reception) {
                        if ($resteADeduire <= 0) break;
                        $aPrendre = min($resteADeduire, $reception->somme);
                        $reception->decrement('somme', $aPrendre);
                        $resteADeduire -= $aPrendre;
                    }

                    if ($resteADeduire > 0) {
                        $nomMat = $demandes->firstWhere('materiel.modele_materiel_id', $modeleId)->nom_materiel ?? 'Inconnu';
                        throw new \Exception("Stock insuffisant pour le modèle: $nomMat (Manquant: $resteADeduire)");
                    }
                }

                // Pré-charger les services pour éviter les requêtes N+1
                $services = Service::whereIn('nom', $demandes->pluck('service_beneficiaire')->unique()->filter())->get()->keyBy('nom');

                // Mise à jour de masse des pièces
                PieceMateriel::whereIn('demande_id', $demandes->pluck('id'))->update([
                    'statut' => 'Livré'
                ]);

                foreach ($demandes as $demande) {
                    $service = $services->get($demande->service_beneficiaire);

                    if ((int)$demande->nbredemande > 0 && $demande->materiel) {
                        $demande->materiel->update([
                            'etat'       => 'Livré',
                            'service_id' => $service ? $service->id : null,
                        ]);
                    }

                    $demande->update(['statut' => 'Clôturé']);
                }
            });

            return back()->with('success', count($ids) . ' demandes clôturées avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la clôture : ' . $e->getMessage());
        }
    }

    /**
     * 7. Mise à jour manuelle du S/N
     */
    public function updateSerialNumber(Request $request, int $id)
    {
        $request->validate(['numero_serie' => 'required|string']);
        $demande = Demande::findOrFail($id);
        $demande->update(['numero_serie' => $request->numero_serie]);

        return back()->with('success', 'Numéro de série mis à jour.');
    }

    /**
     * 8. Imprimer le bon de commande
     */
    public function imprimer_bon(Request $request, string $service)
    {
        $serviceNom = trim($service);
        $demandeur  = $request->query('demandeur');
        $numcomande = $request->query('numcomande');

        $query = Demande::with(['pieces', 'materiel.modele', 'materiel.pieces', 'modele'])
            ->where('service_beneficiaire', $serviceNom)
            ->whereIn('statut', ['Validé', 'En attente', 'Clôturé']);

        if ($numcomande) {
            $query->where('numcomande', $numcomande);
        } else {
            if ($demandeur) {
                $query->where('demandeur_nom', $demandeur);
            }
            $query->latest()->limit(200);
        }

        $demandes = $query->get();

        if ($demandes->isEmpty()) {
            return back()->with('error', "Aucune demande trouvée.");
        }

        $demandesPretes = $demandes->map(function ($demande) {
            $quantite    = $demande->nombre_article ?? $demande->nbredemande ?? 0;
            $nomMateriel = $demande->nom_materiel;

            if (empty($nomMateriel) && $demande->modele) {
                $nomMateriel = $demande->modele->nom;
            }
            if (empty($nomMateriel) && $demande->materiel && $demande->materiel->modele) {
                $nomMateriel = $demande->materiel->modele->nom;
            }

            return [
                'id'                    => $demande->id,
                'numcomande'            => $demande->numcomande,
                'nom_materiel'          => $nomMateriel ?: 'MATÉRIEL',
                'numero_serie'          => $demande->numero_serie ?? ($demande->materiel->numero_serie ?? '—'),
                'nbredemande'           => $quantite,
                'demandeur_nom'         => $demande->demandeur_nom,
                'description'           => $demande->description,
                'pieces'                => $demande->pieces->map(fn ($p) => [
                    'id'           => $p->id,
                    'nom_piece'    => $p->nom_piece,
                    'numero_serie' => $p->numero_serie ?? '—',
                ]),
                'est_uniquement_piece'  => (int)$quantite === 0,
                'a_des_pieces_au_total' => $demande->materiel && $demande->materiel->pieces
                    ? $demande->materiel->pieces->isNotEmpty()
                    : false,
            ];
        });

        return Inertia::render('demandes/BonCommande', [
            'service'    => $serviceNom,
            'demandes'   => $demandesPretes,
            'numcomande' => $numcomande,
            'demandeur'  => $demandeur ?? ($demandes->first()->demandeur_nom ?? ''),
            'date'       => $request->query('date') ?? now()->format('d/m/Y'),
        ]);
    }

    /**
     * 9. Historique - Pagination uniforme (10 par page TOUJOURS)
     */
    public function historique(Request $request)
    {
        $commandesQuery = Demande::query()
            ->select(
                'numcomande',
                DB::raw('MIN(date_demande) as date_demande'),
                DB::raw('MIN(service_beneficiaire) as service_beneficiaire'),
                DB::raw('MIN(demandeur_nom) as demandeur_nom'),
                DB::raw('MIN(statut) as statut')
            )
            ->where('statut', 'Clôturé');

        if ($request->filled('service')) {
            $commandesQuery->where('service_beneficiaire', $request->service);
        }
        if ($request->filled('year')) {
            $commandesQuery->whereYear('date_demande', $request->year);
        }
        if ($request->filled('month')) {
            $commandesQuery->whereMonth('date_demande', $request->month);
        }
        if ($request->filled('search')) {
            $search = $request->search;

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)) {
                [$d, $m, $y] = explode('/', $search);
                $search = "$y-$m-$d";
            }

            $commandesQuery->where(function ($q) use ($search) {
                $q->where('numcomande', 'ilike', "%{$search}%")
                    ->orWhere('service_beneficiaire', 'ilike', "%{$search}%")
                    ->orWhere('demandeur_nom', 'ilike', "%{$search}%")
                    ->orWhere('nom_materiel', 'ilike', "%{$search}%")
                    ->orWhere('date_demande', 'ilike', "%{$search}%");
            });
        }

        $commandes = $commandesQuery
            ->groupBy('numcomande')
            ->orderBy('date_demande', 'desc')
            ->paginate(10)
            ->withQueryString();

        $toutesLesDemandes = $this->chargerDemandesParCommandes($commandes->getCollection());

        $commandes->getCollection()->transform(function ($commande) use ($toutesLesDemandes) {
            $demandes = ($toutesLesDemandes->get($commande->numcomande) ?? collect())
                ->map(function ($demande) {
                    $piecesMappees = ($demande->pieces ?? collect())->map(fn ($p) => [
                        'id'           => $p->id,
                        'nom_piece'    => $p->nom_piece,
                        'numero_serie' => $p->numero_serie ?? null,
                    ])->values()->all();

                    $nbPieces         = count($piecesMappees);
                    $nbredemande      = (int) $demande->nbredemande;
                    $estSortieUniquementPiece = $nbredemande === 0 && $nbPieces > 0;
                    $aDesPiecesAuTotal = $demande->materiel
                        && $demande->materiel->pieces
                        && $demande->materiel->pieces->count() > 0;

                    return [
                        'id'                          => $demande->id,
                        'nom_materiel'                => $demande->nom_materiel,
                        'numero_serie'                => $demande->numero_serie,
                        'nbredemande'                 => $nbredemande,
                        'date_demande'                => $demande->date_demande,
                        'service_beneficiaire'        => $demande->service_beneficiaire,
                        'demandeur_nom'               => $demande->demandeur_nom,
                        'description'                 => $demande->description,
                        'pieces'                      => $piecesMappees,
                        'a_des_pieces_au_total'       => $aDesPiecesAuTotal,
                        'est_sortie_uniquement_piece' => $estSortieUniquementPiece,
                    ];
                });

            return [
                'numcomande'           => $commande->numcomande,
                'date_demande'         => $commande->date_demande,
                'service_beneficiaire' => $commande->service_beneficiaire,
                'demandeur_nom'        => $commande->demandeur_nom,
                'statut'               => $commande->statut,
                'demandes'             => $demandes,
                'total_items'          => $demandes->count(),
            ];
        });

        return Inertia::render('demandes/Historique', [
            'historique' => $commandes,
            'services'   => Service::select('id', 'nom')->orderBy('nom')->get(),
            'filters'    => [
                'search'  => $request->input('search', ''),
                'year'    => $request->input('year', ''),
                'month'   => $request->input('month', ''),
                'service' => $request->input('service', ''),
            ],
        ]);
    }

    /**
     * 10. Export PDF
     */
    public function exportPDF(Request $request)
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(600);

        $commandesQuery = Demande::query()
            ->select(
                'numcomande',
                DB::raw('MIN(date_demande) as date_demande'),
                DB::raw('MIN(service_beneficiaire) as service_beneficiaire'),
                DB::raw('MIN(demandeur_nom) as demandeur_nom'),
                DB::raw('MIN(statut) as statut')
            )
            ->where('statut', 'Clôturé');

        if ($request->filled('service')) {
            $commandesQuery->where('service_beneficiaire', $request->service);
        }
        if ($request->filled('year')) {
            $commandesQuery->whereYear('date_demande', $request->year);
        }
        if ($request->filled('month')) {
            $commandesQuery->whereMonth('date_demande', $request->month);
            if (!$request->filled('year')) {
                $commandesQuery->whereYear('date_demande', date('Y'));
            }
        }
        if ($request->filled('search')) {
            $search = $request->search;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)) {
                [$d, $m, $y] = explode('/', $search);
                $search = "$y-$m-$d";
            }
            $commandesQuery->where(function ($q) use ($search) {
                $q->where('numcomande', 'ilike', "%{$search}%")
                  ->orWhere('service_beneficiaire', 'ilike', "%{$search}%")
                  ->orWhere('demandeur_nom', 'ilike', "%{$search}%")
                  ->orWhere('date_demande', 'ilike', "%{$search}%");
            });
        }

        $commandes = $commandesQuery
            ->groupBy('numcomande')
            ->orderBy('date_demande', 'desc')
            ->get();

        if ($commandes->isEmpty()) {
            return back()->with('error', "Aucune commande trouvée pour ces critères.");
        }

        $numCommandes = $commandes->pluck('numcomande');
        $toutesLesDemandes = Demande::whereIn('numcomande', $numCommandes)
            ->with(['pieces', 'materiel.pieces'])
            ->get()
            ->groupBy('numcomande');

        $donnees = [];

        foreach ($commandes as $commande) {
            $dateKey    = \Carbon\Carbon::parse($commande->date_demande)->format('Y-m-d');
            $service    = $commande->service_beneficiaire;
            $demandeur  = $commande->demandeur_nom;
            $numcomande = $commande->numcomande;
            $dateCommande = $commande->date_demande;

            $demandes = $toutesLesDemandes->get($numcomande, collect());

            if (!isset($donnees[$dateKey])) {
                $donnees[$dateKey] = [];
            }
            if (!isset($donnees[$dateKey][$service])) {
                $donnees[$dateKey][$service] = [];
            }
            if (!isset($donnees[$dateKey][$service][$demandeur])) {
                $donnees[$dateKey][$service][$demandeur] = [];
            }

            $donnees[$dateKey][$service][$demandeur][$numcomande] = [
                'demandes' => $demandes,
                'date_commande' => $dateCommande
            ];
        }

        krsort($donnees);

        $serviceLabel = $request->filled('service') ? $request->service : 'Tous les services';
        $sousTitre    = "UNITÉ : " . strtoupper($serviceLabel);

        if ($request->filled('month')) {
            $moisInt  = (int) $request->month;
            $moisFm   = \Carbon\Carbon::now()->month($moisInt)->translatedFormat('F');
            $annee    = $request->filled('year') ? $request->year : date('Y');
            $sousTitre .= " — PÉRIODE : " . strtoupper($moisFm) . " $annee";
        } elseif ($request->filled('year')) {
            $sousTitre .= " — ANNÉE : " . $request->year;
        } else {
            $sousTitre .= " — HISTORIQUE COMPLET";
        }

        if ($request->filled('search')) {
            $sousTitre .= " — RECHERCHE : " . $request->search;
        }

        try {
            $pdf = Pdf::loadView('pdf.historique', [
                'donnees'   => $donnees,
                'titre'     => "HISTORIQUE DES SORTIES MATÉRIELS",
                'sousTitre' => $sousTitre,
            ])->setPaper('a4', 'portrait');

            $nomFichier = 'historique_sorties_'
                . ($request->filled('service') ? Str::slug($request->service) . '_' : '')
                . date('dmY_His')
                . '.pdf';

            return $pdf->download($nomFichier);

        } catch (\Exception $e) {
            Log::error('Erreur export PDF: ' . $e->getMessage());
            return back()->with('error', "Erreur lors de la génération : " . $e->getMessage());
        }
    }

    /**
     * 11. Suppression / Annulation
     */
    public function destroy_by_commande(string $numcomande)
    {
        return DB::transaction(function () use ($numcomande) {
            $demandeIds = Demande::where('numcomande', $numcomande)->pluck('id');

            if ($demandeIds->isEmpty()) {
                return back()->with('error', 'Commande introuvable.');
            }

            Materiel::whereIn('demande_id', $demandeIds)->update([
                'demande_id' => null,
                'etat'       => 'Disponible',
            ]);

            PieceMateriel::whereIn('demande_id', $demandeIds)->update([
                'demande_id' => null,
                'statut'     => 'En Stock',
            ]);

            Demande::whereIn('id', $demandeIds)->delete();

            return back()->with('success', "La commande #$numcomande a été annulée et les stocks ont été libérés.");
        });
    }
}
