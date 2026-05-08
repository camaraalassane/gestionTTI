<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\Materiel;
use App\Models\Service;
use App\Models\PieceMateriel;
use App\Models\ModeleMateriel;
use App\Models\Reception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class DemandeController extends Controller
{
    /**
     * 1. Liste des demandes "En attente"
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $demandes = Demande::query()
            ->select([
                'id',
                'numcomande',
                'date_demande',
                'demandeur_nom',
                'service_beneficiaire',
                'statut',
                'nom_materiel',
                'numero_serie',
                'nbredemande',
                'materiel_id'
            ])
            ->with([
                'pieces:id,demande_id,nom_piece,numero_serie',
                'materiel.pieces:id,materiel_id'
            ])
            ->where('statut', 'En attente')
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('numcomande', 'like', "%{$search}%")
                      ->orWhere('service_beneficiaire', 'like', "%{$search}%")
                      ->orWhere('numero_serie', 'like', "%{$search}%")
                      ->orWhere('nom_materiel', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $demandes->getCollection()->transform(function ($demande) {
            $demande->date_affichee = $demande->date_demande
                ? \Carbon\Carbon::parse($demande->date_demande)->format('d/m/Y')
                : 'Date inconnue';

            $demande->est_sortie_materiel = (int)$demande->nbredemande > 0;
            $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();

            return $demande;
        });

        return Inertia::render('demandes/index', [
            'demandes' => $demandes,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * 2. Formulaire de création - SANS LIMITE
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
                'exemplaires as total_materiels' => function($q) {
                    $q->whereNull('demande_id')->whereIn('etat', ['Disponible', 'En stock']);
                }
            ]);

        if ($search) {
            $query->where('nom', 'ilike', "%{$search}%");
        }

        $modeles = $query->get();

        return response()->json($modeles);
    }

    /**
     * API: Récupérer TOUS les matériels disponibles d'un modèle (sans limite)
     */
    public function getMaterielsByModele(int $modele_id)
    {
        $materiels = Materiel::where('modele_materiel_id', $modele_id)
            ->whereNull('demande_id')
            ->whereIn('etat', ['Disponible', 'En stock'])
            ->with(['modele', 'pieces' => function($q) {
                $q->whereNull('demande_id');
            }])
            ->get();

        return response()->json($materiels);
    }

    /**
     * 3. Enregistrement du Panier
     */
    public function store_group(Request $request)
    {
        $validated = $request->validate([
            'demandeur_nom' => 'required',
            'service_beneficiaire' => 'required',
            'date_demande' => 'required|date',
            'items' => 'required|array',
            'items.*.materiel_id' => 'required',
            'items.*.numero_serie' => 'nullable|string',
            'items.*.mode_sortie' => 'required|in:unite,pieces,complet',
            'items.*.pieces_ids' => 'sometimes|array',
            'items.*.pieces_details' => 'sometimes|array',
            'items.*.description' => 'nullable|string',
            'items.*.quantite' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $prefix = 'CMD-' . date('Y') . '-';
                $lastDemande = Demande::where('numcomande', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
                $lastNum = $lastDemande ? intval(substr($lastDemande->numcomande, -4)) : 0;
                $numCmd = $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

                foreach ($validated['items'] as $item) {
                    $mat = Materiel::with(['modele', 'categorie'])->findOrFail($item['materiel_id']);

                    if (!empty($item['numero_serie']) && $item['mode_sortie'] !== 'pieces') {
                        $mat->update(['numero_serie' => $item['numero_serie']]);
                    }

                    $quantiteMateriel = ($item['mode_sortie'] === 'pieces') ? 0 : $item['quantite'];
                    $nomMateriel = $mat->modele ? $mat->modele->nom : $mat->nom;
                    $modeleMaterielId = $mat->modele_materiel_id ?? $mat->modele->id ?? null;

                    if (!$modeleMaterielId) {
                        throw new \Exception("Impossible de déterminer le modèle du matériel ID: " . $mat->id);
                    }

                    $demande = Demande::create([
                        'numcomande' => $numCmd,
                        'materiel_id' => $mat->id,
                        'modele_materiel_id' => $modeleMaterielId,
                        'nom_materiel' => $nomMateriel,
                        'nbredemande' => $quantiteMateriel,
                        'numero_serie' => $item['numero_serie'] ?? $mat->numero_serie,
                        'categorie' => $mat->categorie->nom ?? 'N/A',
                        'demandeur_nom' => $validated['demandeur_nom'],
                        'service_beneficiaire' => $validated['service_beneficiaire'],
                        'date_demande' => $validated['date_demande'],
                        'statut' => 'En attente',
                        'description' => $item['description'] ?? '',
                    ]);

                    if (!empty($item['pieces_details'])) {
                        foreach ($item['pieces_details'] as $pDetail) {
                            if (isset($pDetail['id'])) {
                                DB::table('pieces_materiels')->where('id', $pDetail['id'])->update([
                                    'numero_serie' => $pDetail['numero_serie'] ?? null,
                                    'demande_id'   => $demande->id,
                                    'statut'       => 'En attente'
                                ]);
                            }
                        }
                    }

                    if ($item['mode_sortie'] === 'unite' || $item['mode_sortie'] === 'complet') {
                        $mat->update([
                            'demande_id' => $demande->id,
                            'etat' => 'En attente'
                        ]);
                    }
                }

                return redirect()->route('demandes.index');
            });
        } catch (\Exception $e) {
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
                $demandes = Demande::with('materiel')->whereIn('id', $ids)->get();

                foreach ($demandes as $demande) {
                    $service = Service::where('nom', $demande->service_beneficiaire)->first();

                    PieceMateriel::where('demande_id', $demande->id)->update(['statut' => 'Livré']);

                    if ((int)$demande->nbredemande > 0 && $demande->materiel && $demande->materiel->demande_id == $demande->id) {
                        $demande->materiel->update([
                            'etat' => 'Livré',
                            'service_id' => $service ? $service->id : $demande->materiel->service_id
                        ]);
                    } else if ((int)$demande->nbredemande == 0 && $demande->materiel) {
                        $demande->materiel->update([
                            'demande_id' => null,
                            'etat' => 'Disponible'
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
     * 5. Gestion par Service
     */
    public function gestionService()
    {
        $demandes = Demande::where('statut', '!=', 'Clôturé')
            ->with(['pieces:id,demande_id,nom_piece,numero_serie', 'materiel.pieces'])
            ->select('id', 'materiel_id', 'nom_materiel', 'numero_serie', 'service_beneficiaire', 'statut', 'nbredemande', 'demandeur_nom', 'description', 'date_demande')
            ->latest()
            ->get();

        $demandes->transform(function ($demande) {
            $demande->est_uniquement_piece = (int)$demande->nbredemande == 0;
            $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
            $demande->date_affichee = $demande->date_demande
                ? \Carbon\Carbon::parse($demande->date_demande)->format('d/m/Y')
                : 'N/A';
            return $demande;
        });

        return Inertia::render('demandes/GestionService', [
            'demandes' => $demandes,
            'services' => Service::select('id', 'nom')->get()
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
                $demandes = Demande::with(['materiel.modele'])->whereIn('id', $ids)->get();

                foreach ($demandes as $demande) {
                    $service = Service::where('nom', $demande->service_beneficiaire)->first();
                    $quantiteDemandee = (int)$demande->nbredemande;

                    if ($quantiteDemandee > 0 && $demande->materiel) {
                        $modeleId = $demande->materiel->modele_materiel_id;

                        $receptions = Reception::whereHas('materiels', function($q) use ($modeleId) {
                                $q->where('modele_materiel_id', $modeleId);
                            })
                            ->where('somme', '>', 0)
                            ->orderBy('date_livraison', 'asc')
                            ->get();

                        $quantiteRestante = $quantiteDemandee;

                        foreach ($receptions as $reception) {
                            if ($quantiteRestante <= 0) break;

                            $aPrendre = min($quantiteRestante, $reception->somme);

                            $reception->decrement('somme', $aPrendre);
                            $quantiteRestante -= $aPrendre;
                        }

                        if ($quantiteRestante > 0) {
                            throw new \Exception("Stock insuffisant pour le modèle: " . $demande->nom_materiel);
                        }

                        Materiel::where('demande_id', $demande->id)->update([
                            'etat' => 'Livré',
                            'service_id' => $service ? $service->id : null
                        ]);
                    }

                    PieceMateriel::where('demande_id', $demande->id)->update([
                        'statut' => 'Livré'
                    ]);

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
        $demandeur = $request->query('demandeur');

        $query = Demande::with([
            'pieces',
            'materiel',
            'modele'
        ])->where('service_beneficiaire', $serviceNom);

        if ($demandeur) {
            $query->where('demandeur_nom', $demandeur);
        }

        $demandes = $query->whereIn('statut', ['Validé', 'En attente', 'Clôturé'])->get();

        if ($demandes->isEmpty()) {
            return back()->with('error', "Aucune demande trouvée.");
        }

        $demandesPretes = $demandes->map(function ($demande) {
            $quantite = $demande->nombre_article ?? $demande->nbredemande ?? 0;
            $nomMateriel = $demande->nom_materiel;

            if (empty($nomMateriel) && $demande->modele) {
                $nomMateriel = $demande->modele->nom;
            }

            if (empty($nomMateriel) && $demande->materiel && $demande->materiel->modele) {
                $nomMateriel = $demande->materiel->modele->nom;
            }

            return [
                'id' => $demande->id,
                'numcomande' => $demande->numcomande,
                'nom_materiel' => $nomMateriel ?: 'MATÉRIEL',
                'numero_serie' => $demande->numero_serie ?? ($demande->materiel->numero_serie ?? '—'),
                'nbredemande' => $quantite,
                'demandeur_nom' => $demande->demandeur_nom,
                'description' => $demande->description,
                'pieces' => $demande->pieces->map(function($p) {
                    return [
                        'id' => $p->id,
                        'nom_piece' => $p->nom_piece,
                        'numero_serie' => $p->numero_serie ?? '—'
                    ];
                }),
                'est_uniquement_piece' => (int)$quantite === 0,
                'a_des_pieces_au_total' => $demande->materiel ? $demande->materiel->pieces()->exists() : false,
            ];
        });

        return Inertia::render('demandes/BonCommande', [
            'service'   => $serviceNom,
            'demandes'  => $demandesPretes,
            'demandeur' => $demandeur ?? ($demandes->first()->demandeur_nom ?? ''),
            'date'      => $request->query('date') ?? now()->format('d/m/Y')
        ]);
    }

    /**
     * 9. Historique
     */
    public function historique(Request $request)
    {
        $query = Demande::with(['pieces', 'materiel.pieces'])
            ->where('statut', 'Clôturé');

        if ($request->filled('service')) {
            $query->where('service_beneficiaire', $request->service);
        }

        if ($request->filled('year')) {
            $query->whereYear('date_demande', $request->year);
        }

        if ($request->filled('month')) {
            if ($request->filled('year')) {
                $query->whereMonth('date_demande', $request->month);
            } else {
                $query->whereYear('date_demande', date('Y'))
                      ->whereMonth('date_demande', $request->month);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom_materiel', 'like', "%{$search}%")
                  ->orWhere('service_beneficiaire', 'like', "%{$search}%")
                  ->orWhere('numero_serie', 'like', "%{$search}%")
                  ->orWhere('numcomande', 'like', "%{$search}%")
                  ->orWhereHas('pieces', function($sq) use ($search) {
                      $sq->where('numero_serie', 'like', "%{$search}%");
                  });
            });
        }

        $historique = $query->latest('date_demande')->paginate(15)->withQueryString();

        $historique->getCollection()->transform(function ($demande) {
            $demande->est_sortie_uniquement_piece = ((int)$demande->nbredemande === 0 && $demande->pieces->isNotEmpty());
            $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
            $demande->date_affichee = $demande->date_demande
                ? \Carbon\Carbon::parse($demande->date_demande)->format('d/m/Y')
                : '—';
            return $demande;
        });

        return Inertia::render('demandes/Historique', [
            'historique' => $historique,
            'services'   => Service::select('id', 'nom')->orderBy('nom')->get(),
            'filters'    => $request->only(['search', 'year', 'month', 'service'])
        ]);
    }

    /**
     * 10. Export PDF
     */
    public function exportPDF(Request $request)
    {
        $query = Demande::with(['pieces'])
            ->where('statut', 'Clôturé')
            ->orderBy('date_demande', 'desc')
            ->orderBy('numcomande', 'desc');

        $filters = $request->only(['service', 'year', 'month', 'search']);

        if ($filters['service'] ?? null) {
            $query->where('service_beneficiaire', $filters['service']);
        }
        if ($filters['year'] ?? null) {
            $query->whereYear('date_demande', $filters['year']);
        }
        if ($filters['month'] ?? null) {
            $query->whereMonth('date_demande', (int) $filters['month']);
        }
        if ($filters['search'] ?? null) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nom_materiel', 'like', "%{$search}%")
                  ->orWhere('numero_serie', 'like', "%{$search}%")
                  ->orWhere('numcomande', 'like', "%{$search}%");
            });
        }

        $count = $query->count();
        if ($count > 1000) {
            return back()->with('error', "Trop de données ($count lignes). Veuillez filtrer par mois.");
        }

        $demandes = $query->get();

        $donnees = $demandes->groupBy([
            fn($d) => \Carbon\Carbon::parse($d->date_demande)->format('Y-m-d'),
            'service_beneficiaire',
            'demandeur_nom',
            'numcomande'
        ], preserveKeys: true);

        try {
            $serviceLabel = $filters['service'] ?? 'Tous les services';
            $sousTitre = "UNITÉ : " . strtoupper($serviceLabel);

            if ($filters['month'] ?? null) {
                $moisInt = (int) $filters['month'];
                $moisFm = \Carbon\Carbon::now()->month($moisInt)->translatedFormat('F');
                $sousTitre .= " - PÉRIODE : " . strtoupper($moisFm) . " " . ($filters['year'] ?? date('Y'));
            }

            $pdf = Pdf::loadView('pdf.historique', [
                'donnees'   => $donnees,
                'titre'     => "HISTORIQUE DES SORTIES MATÉRIELS",
                'sousTitre' => $sousTitre
            ])->setPaper('a4', 'portrait');

            return $pdf->download('historique_sorties_' . date('dmY') . '.pdf');

        } catch (\Exception $e) {
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
                'etat' => 'Disponible'
            ]);

            PieceMateriel::whereIn('demande_id', $demandeIds)->update([
                'demande_id' => null,
                'statut' => 'En Stock'
            ]);

            Demande::whereIn('id', $demandeIds)->delete();

            return back()->with('success', "La commande #$numcomande a été annulée et les stocks ont été libérés.");
        });
    }
}
