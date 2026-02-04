<?php

namespace App\Http\Controllers;

use App\Models\{Materiel, Inventaire, InventaireDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class InventaireController extends Controller
{
    public function index()
    {
        return Inertia::render('materiel/InventaireIndex', [
            'historique' => Inventaire::with('user:id,name')
                ->orderBy('annee', 'desc')
                ->get()
        ]);
    }

    public function store(Request $request)
{
    $request->validate([
        'annee' => 'required|unique:inventaires,annee'
    ]);

    try {
        DB::transaction(function () use ($request) {
            // 1. Récupère uniquement le matériel au magasin
            $materiels = Materiel::with('pieces')->whereNull('service_id')->get();

            if ($materiels->isEmpty()) {
                throw new \Exception("Aucun matériel en stock (magasin) à inventorier.");
            }

            // 2. Création de l'entête
            $inventaire = Inventaire::create([
                'annee' => $request->annee,
                'date_cloture' => now(),
                'total_items' => $materiels->count(),
                'user_id' => Auth::id(),
            ]);

            // 3. Préparer les détails (Capture uniquement le nom propre)
            $data = [];
            foreach ($materiels as $m) {
                // On n'ajoute PLUS les parenthèses ici, car le front-end 
                // s'occupe déjà d'afficher le nombre de pièces via la relation.
                $data[] = [
                    'inventaire_id' => $inventaire->id,
                    'designation'   => $m->nom, // NOM PROPRE UNIQUEMENT
                    'numero_serie'  => $m->numero_serie,
                    'etat_materiel' => $m->etat,
                    'localisation'  => 'MAGASIN',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            // 4. Insertion massive
            DB::table('inventaire_details')->insert($data);
        });

        return back()->with('success', "L'inventaire {$request->annee} a été archivé avec succès.");

    } catch (\Exception $e) {
        return back()->withErrors(['annee' => $e->getMessage()]);
    }
}

public function show($id)
{
    $inventaire = Inventaire::with('user:id,name')->findOrFail($id);

    $details = InventaireDetail::where('inventaire_id', $id)
        ->get()
        ->map(function ($detail) {
            $materiel = Materiel::with(['reception', 'categorie', 'pieces'])
                ->where('numero_serie', $detail->numero_serie)
                ->first();

            $derniereDemande = null;
            if ($materiel) {
                $derniereDemande = DB::table('demandes')
                    ->where('materiel_id', $materiel->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            return [
                'id'             => $detail->id,
                // CORRECTION ICI : On coupe la chaîne à la première parenthèse et on nettoie les espaces
                'designation'    => trim(explode('(', $detail->designation)[0]),
                
                'numero_serie'   => $detail->numero_serie,
                'etat_materiel'  => $detail->etat_materiel,
                'localisation'   => $detail->localisation,
                'fournisseur'    => $materiel?->reception?->fournisseur ?? 'N/A',
                'numero_contrat' => $materiel?->reception?->numero_contrat ?? 'N/A',
                'categorie'      => $materiel?->categorie?->nom ?? 'NON CLASSÉ',
                'est_complet'    => $materiel ? $materiel->est_complet : true,
                
                'demande'        => $derniereDemande ? [
                    'demandeur' => $derniereDemande->demandeur_nom,
                    'service'   => $derniereDemande->service_beneficiaire,
                    'statut'    => $derniereDemande->statut
                ] : null,

                'pieces'         => $materiel?->pieces->map(function($p) {
                    $demandePiece = null;
                    if ($p->demande_id) {
                        $demandePiece = DB::table('demandes')->where('id', $p->demande_id)->first();
                    }
                    return [
                        'id'           => $p->id,
                        'nom'          => $p->nom_piece,
                        'numero_serie' => $p->numero_serie,
                        'demande'      => $demandePiece ? [
                            'service' => $demandePiece->service_beneficiaire
                        ] : null
                    ];
                }) ?? []
            ];
        });

    return Inertia::render('materiel/InventaireShow', [
        'inventaire' => $inventaire,
        'details'    => $details
    ]);
}
 public function downloadPdf($id)
{
    $inventaire = Inventaire::with(['user'])->findOrFail($id);

    $details = InventaireDetail::where('inventaire_id', $id)->get()->map(function($d) {
        // 1. On cherche le matériel lié par numéro de série
        $m = \App\Models\Materiel::with(['reception', 'pieces', 'categorie'])
            ->where('numero_serie', $d->numero_serie)
            ->first();

        // 2. On cherche la dernière demande pour le matériel principal
        $derniereDemande = null;
        if ($m) {
            $derniereDemande = DB::table('demandes')
                ->where('materiel_id', $m->id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // 3. On prépare les données pour le PDF (Format tableau pour Blade)
        return [
            'designation'    => $d->designation,
            'numero_serie'   => $d->numero_serie,
            'etat_materiel'  => $d->etat_materiel,
            'fournisseur'    => $m?->reception?->fournisseur ?? 'N/A',
            'numero_contrat' => $m?->reception?->numero_contrat ?? 'N/A',
            'categorie'      => $m?->categorie?->nom ?? 'NON CLASSÉ',
            
            // Infos de demande (Demandeur / Service)
            'demande' => $derniereDemande ? [
                'demandeur' => $derniereDemande->demandeur_nom,
                'service'   => $derniereDemande->service_beneficiaire
            ] : null,

            // Liste des pièces avec leurs propres demandes
            'pieces' => $m?->pieces->map(function($p) {
                $demandePiece = null;
                if ($p->demande_id) {
                    $demandePiece = DB::table('demandes')->where('id', $p->demande_id)->first();
                }
                return [
                    'nom'     => $p->nom_piece,
                    'demande' => $demandePiece ? ['service' => $demandePiece->service_beneficiaire] : null
                ];
            })->toArray() ?? []
        ];
    });

    $data = [
        'inventaire'  => $inventaire,
        'title'       => "INVENTAIRE MAGASIN " . $inventaire->annee,
        'date'        => $inventaire->date_cloture ?? now(),
        'responsable' => $inventaire->user->name,
        'details'     => $details // C'est maintenant une collection de tableaux
    ];

    return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.inventaire', $data)
        ->setPaper('a4', 'landscape')
        ->download("Inventaire_{$inventaire->annee}.pdf");
}
}