<?php

namespace App\Http\Controllers;

use App\Models\{Materiel, Inventaire, InventaireDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InventaireController extends Controller
{
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

    public function store(Request $request)
    {
        $request->validate([
            'annee' => 'required|string|size:4|unique:inventaires,annee',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $materielsCount = Materiel::whereNull('service_id')
                    ->whereNull('demande_id')
                    ->count();

                if ($materielsCount === 0) {
                    return back()->withErrors(['annee' => 'Aucun matériel en stock à archiver.']);
                }

                $inventaire = Inventaire::create([
                    'annee' => $request->annee,
                    'date_cloture' => now(),
                    'total_items' => 0,
                    'user_id' => Auth::id(),
                ]);

                $totalCount = 0;
                Materiel::whereNull('service_id')
                    ->whereNull('demande_id')
                    ->with('modele:id,nom')
                    ->select('id', 'modele_materiel_id', 'numero_serie', 'etat')
                    ->chunk(200, function ($materiels) use ($inventaire, &$totalCount) {
                        $data = [];
                        foreach ($materiels as $m) {
                            $data[] = [
                                'inventaire_id' => $inventaire->id,
                                'designation'   => $m->modele ? $m->modele->nom : 'N/A', 
                                'numero_serie'  => $m->numero_serie,
                                'etat_materiel' => $m->etat,
                                'localisation'  => 'MAGASIN',
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ];
                            $totalCount++;
                        }
                        InventaireDetail::insert($data);
                    });

                $inventaire->update(['total_items' => $totalCount]);

                return redirect()->back()->with('success', "Inventaire {$request->annee} archivé avec succès ({$totalCount} lignes).");
            });
        } catch (\Exception $e) {
            return back()->withErrors(['annee' => "Erreur lors de l'archivage : " . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $inventaire = Inventaire::with('user:id,name')->findOrFail($id);
        $details = InventaireDetail::where('inventaire_id', $id)->orderBy('id')->paginate(50)->withQueryString();
        $sns = $details->pluck('numero_serie')->filter()->values()->toArray();

        $materielsAssocies = collect();
        if (!empty($sns)) {
            $materielsAssocies = Materiel::with([
                'modele:id,nom',
                'reception' => fn($q) => $q->select('id', 'fournisseur', 'numero_contrat', 'contrat_id'),
                'reception.contrat' => fn($q) => $q->select('id', 'fournisseur', 'numero_contrat'),
                'categorie:id,nom',
                'pieces' => fn($q) => $q->select('id', 'materiel_id', 'nom_piece', 'statut', 'demande_id'),
                'pieces.demande:id,service_beneficiaire,demandeur_nom',
                'demande:id,service_beneficiaire,demandeur_nom'
            ])->whereIn('numero_serie', $sns)->get()->keyBy('numero_serie');
        }

        $details->getCollection()->transform(function ($detail) use ($materielsAssocies) {
            $m = $materielsAssocies->get($detail->numero_serie);
            if (!$m) return ['id' => $detail->id, 'designation' => $detail->designation, 'numero_serie' => $detail->numero_serie, 'categorie' => 'N/A', 'etat_materiel' => $detail->etat_materiel, 'localisation' => $detail->localisation, 'fournisseur' => 'N/A', 'numero_contrat' => 'N/A', 'pieces' => [], 'demande' => null];

            $fournisseur = $m->reception->fournisseur ?? 'N/A';
            $numeroContrat = $m->reception->numero_contrat ?? 'N/A';
            if ($m->reception?->contrat && $fournisseur === 'N/A') {
                $fournisseur = $m->reception->contrat->fournisseur ?? 'N/A';
                $numeroContrat = $m->reception->contrat->numero_contrat ?? 'N/A';
            }

            return [
                'id' => $detail->id,
                'designation' => $detail->designation,
                'numero_serie' => $detail->numero_serie,
                'categorie' => $m->categorie?->nom ?? 'N/A',
                'etat_materiel' => $detail->etat_materiel,
                'localisation' => $detail->localisation,
                'fournisseur' => $fournisseur,
                'numero_contrat' => $numeroContrat,
                'pieces' => collect($m->pieces)->map(fn($p) => ['nom' => $p->nom_piece, 'demande' => $p->demande ? ['service' => $p->demande->service_beneficiaire] : null]),
                'demande' => $m->demande ? ['service' => $m->demande->service_beneficiaire, 'demandeur' => $m->demande->demandeur_nom] : null,
            ];
        });

        return Inertia::render('materiel/InventaireShow', [
            'inventaire' => ['id' => $inventaire->id, 'annee' => $inventaire->annee, 'date_cloture' => $inventaire->date_cloture, 'total_items' => $inventaire->total_items, 'responsable' => $inventaire->user?->name ?? 'Système'],
            'details' => $details,
        ]);
    }

    public function downloadPdf($id)
    {
        $inventaire = Inventaire::with('user')->findOrFail($id);
        $details = InventaireDetail::where('inventaire_id', $id)->get();
        $sns = $details->pluck('numero_serie')->filter()->values()->toArray();

        $materiels = collect();
        if (!empty($sns)) {
            $materiels = Materiel::with(['modele:id,nom', 'reception:id,fournisseur,numero_contrat', 'categorie:id,nom', 'pieces.demande', 'demande'])
                ->whereIn('numero_serie', $sns)->get()->keyBy('numero_serie');
        }

        $detailsTransformes = $details->map(function ($detail) use ($materiels) {
            $m = $materiels->get($detail->numero_serie);
            return [
                'designation' => $detail->designation,
                'numero_serie' => $detail->numero_serie,
                'categorie' => $m?->categorie?->nom ?? 'N/A',
                'etat_materiel' => $detail->etat_materiel,
                'fournisseur' => $m?->reception->fournisseur ?? 'N/A',
                'numero_contrat' => $m?->reception->numero_contrat ?? 'N/A',
                'localisation' => $detail->localisation,
                'pieces' => collect($m->pieces ?? [])->map(fn($p) => ['nom' => $p->nom_piece, 'demande' => $p->demande ? ['service' => $p->demande->service_beneficiaire] : null])->toArray(),
                'demande' => $m?->demande ? ['service' => $m->demande->service_beneficiaire, 'demandeur' => $m->demande->demandeur_nom] : null,
            ];
        });

        // CORRECTION : La clé 'date' est bien ajoutée ici pour la vue Blade
        $data = [
            'title' => "INVENTAIRE - " . $inventaire->annee,
            'inventaire' => $inventaire,
            'details' => $detailsTransformes,
            'responsable' => $inventaire->user?->name ?? 'Système',
            'date' => now(), // Variable transmise à la vue
            'total_lignes' => $detailsTransformes->count(),
        ];

        return Pdf::loadView('pdf.inventaire', $data)
            ->setPaper('a4', 'landscape')
            ->download("Inventaire_{$inventaire->annee}.pdf");
    }
}