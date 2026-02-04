<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\Categorie;
use App\Models\Reception;
use App\Models\MaterielSupprime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MaterielController extends Controller
{
    /**
     * 0. Vérification de la clé d'accès (Inchangé)
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
     * CORRIGÉ : Utilise 'etat' au lieu de nullité de demande_id
     */
    public function index()
    {
        return Inertia::render('materiel/indexmat', [
            'categories' => Categorie::query()
                ->select('id', 'nom')
                ->withCount(['materiels as stock_reel' => function($query) {
                    $query->where('etat', 'Disponible'); // Correction ici
                }])->get()
        ]);
    }

    /**
     * 2. Liste détaillée (OPTIMISÉE)
     * CORRIGÉ : Stats basées sur le nouveau champ 'etat'
     */
public function list(Request $request)
{
    $search = $request->input('search');
    $receptionId = $request->input('reception_id');

    $baseQuery = Materiel::query()
        ->when($search, fn($q) => $q->where('nom', 'like', "%{$search}%")->orWhere('numero_serie', 'like', "%{$search}%"))
        ->when($receptionId, fn($q) => $q->where('reception_id', $receptionId));

    // --- CALCUL DES STATISTIQUES CORRIGÉ ---
    $stats = [
        'total'      => Materiel::count(),
        'disponible' => Materiel::where('etat', 'Disponible')->count(),
        'en_attente' => Materiel::where('etat', 'En attente')->count(), // AJOUT DE CETTE LIGNE (Le fameux 3)
        'livres'     => Materiel::where('etat', 'Livré')->count(),     // Sera maintenant 14

        'pieces_sorties' => DB::table('pieces_materiels')
            ->whereNotNull('demande_id')
            ->count(),
    ];

    return Inertia::render('materiel/listemateriel', [
        'materiels' => (clone $baseQuery)
            ->with(['categorie', 'demande', 'pieces.demande'])
            ->latest()
            ->paginate(50)
            ->withQueryString(),
        'stats' => $stats, // Envoi des stats corrigées à Vue
        'toutesLesStatsServices' => DB::table('materiels')
            ->join('demandes', 'materiels.demande_id', '=', 'demandes.id')
            ->select('demandes.service_beneficiaire as nom', DB::raw('count(*) as total'))
            ->groupBy('demandes.service_beneficiaire')
            ->orderBy('total', 'desc')
            ->get(),
        'filters' => $request->only(['search', 'reception_id']),
    ]);
}

public function store_group(Request $request)
{
    $request->validate([
        'fournisseur' => 'required|string|max:255',
        'numero_contrat' => 'required|string|max:255',
        'date_livraison' => 'required|date',
        'items' => 'required|array|min:1',
        'items.*.details_unites.*.numero_serie' => 'required|string|unique:materiels,numero_serie',
        'scan_contrat' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
    ]);

    try {
        DB::transaction(function () use ($request) {
            $scanPath = $request->hasFile('scan_contrat')
                ? $request->file('scan_contrat')->store('contrats/' . date('Y'), 'public')
                : null;

            // ON RÉCUPÈRE LA CATÉGORIE DU PREMIER ITEM POUR LA RÉCEPTION
            // Car ta table 'receptions' exige un 'categorie_id'
            $firstItem = collect($request->items)->first();
            $defaultCategoryId = $firstItem['categorie_id'];

            $reception = Reception::create([
                'fournisseur'    => $request->fournisseur,
                'numero_contrat' => $request->numero_contrat,
                'date_livraison' => $request->date_livraison,
                'scan_contrat'   => $scanPath,
                'categorie_id'   => $defaultCategoryId, // CORRECTION : Ajout du champ manquant
            ]);

            foreach ($request->items as $item) {
                foreach ($item['details_unites'] as $uniteData) {
                    $materiel = Materiel::create([
                        'nom'           => $uniteData['nom'] ?? 'Matériel sans nom',
                        'numero_serie'  => $uniteData['numero_serie'],
                        'categorie_id'  => $item['categorie_id'], // Chaque matériel garde sa propre catégorie
                        'reception_id'  => $reception->id,
                        'etat'          => 'Disponible',
                        'statut'        => 'Neuf',
                    ]);

                    if (!empty($uniteData['pieces'])) {
                        foreach ($uniteData['pieces'] as $piece) {
                            DB::table('pieces_materiels')->insert([
                                'materiel_id'  => $materiel->id,
                                'nom_piece'    => $piece['nom'],
                                'numero_serie' => $piece['sn'],
                                'statut'       => 'En Stock',
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('materiel.indexmat')->with('success', '✅ Matériel réceptionné avec succès !');
    } catch (\Exception $e) {
        return back()->with('error', '❌ Erreur SQL : ' . $e->getMessage());
    }
}
 /**
     * Formulaire d'édition
     */
    public function edit($id)
{
    // On ajoute 'pieces' à la liste des relations chargées
    $materiel = Materiel::query()
        ->with(['reception', 'categorie', 'demande', 'pieces'])
        ->where('id', $id)
        ->first();

    if (!$materiel) {
        abort(404, "Matériel non trouvé");
    }

    return Inertia::render('materiel/edit', [
        'materiel' => $materiel,
        'categories' => Categorie::query()->select('id', 'nom')->get()
    ]);
}
    /**
     * 4. Mise à jour
     * CORRIGÉ : Gère 'etat' ET 'statut'
     */
public function update(Request $request, $id)
{
    $materiel = Materiel::findOrFail($id);

    $validated = $request->validate([
        'nom'           => 'required|string|max:255',
        'numero_serie'  => 'required|string|unique:materiels,numero_serie,' . $id,
        'categorie_id'  => 'required|exists:categories,id',
        'etat'          => 'required|string',
        'statut'        => 'required|string',
        'description'   => 'nullable|string',
        'pieces'        => 'nullable|array',
        'pieces.*.id'           => 'nullable|exists:pieces_materiels,id',
        'pieces.*.nom_piece'    => 'required_with:pieces|string|max:255',
        'pieces.*.numero_serie' => 'required_with:pieces|string|max:255',
    ]);

    try {
        DB::transaction(function () use ($materiel, $validated) {
            // 1. Mise à jour des infos de base du matériel (Toujours autorisé)
            $materiel->update([
                'nom'          => $validated['nom'],
                'numero_serie' => $validated['numero_serie'],
                'categorie_id' => $validated['categorie_id'],
                'etat'         => $validated['etat'],
                'statut'       => $validated['statut'],
                'description'  => $validated['description'],
            ]);

            // 2. Gestion sécurisée des pièces
            $piecesData = collect($validated['pieces'] ?? []);
            $sentPieceIds = $piecesData->pluck('id')->filter()->toArray();

            // --- PROTECTION CONTRE LA SUPPRESSION ---
            // On ne supprime que les pièces qui ne sont PAS dans l'envoi ET qui n'ont PAS de demande_id
            $materiel->pieces()
                ->whereNotIn('id', $sentPieceIds)
                ->whereNull('demande_id') // <--- Sécurité : on ignore les pièces livrées
                ->delete();

            // --- PROTECTION CONTRE LA MODIFICATION ---
            foreach ($piecesData as $pieceData) {
                if (isset($pieceData['id'])) {
                    // On récupère la pièce en base pour vérifier son état réel
                    $existingPiece = $materiel->pieces()->find($pieceData['id']);

                    // Si la pièce existe et n'est PAS livrée, on peut la modifier
                    if ($existingPiece && is_null($existingPiece->demande_id)) {
                        $existingPiece->update([
                            'nom_piece'    => $pieceData['nom_piece'],
                            'numero_serie' => $pieceData['numero_serie'],
                        ]);
                    }
                    // Si elle a un demande_id, on ignore silencieusement la modification
                } else {
                    // Création d'une nouvelle pièce (toujours autorisé)
                    $materiel->pieces()->create([
                        'nom_piece'    => $pieceData['nom_piece'],
                        'numero_serie' => $pieceData['numero_serie'],
                        'statut'       => 'En Stock',
                    ]);
                }
            }
        });

        return back()->with('success', '✅ Matériel mis à jour. Les pièces livrées sont restées intactes.');
    } catch (\Exception $e) {
        return back()->with('error', '❌ Erreur : ' . $e->getMessage());
    }
}
    /**
     * 5. Suppression et archivage (Inchangé mais vérifie demande_id)
     */
    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $materiel = Materiel::query()->with(['categorie', 'reception', 'pieces'])->findOrFail($id);

                if ($materiel->etat === 'Livré' || $materiel->demande_id) {
                    throw new \Exception("Ce matériel est actuellement affecté et ne peut pas être supprimé.");
                }

                if ($materiel->reception_id) {
                    Reception::query()->where('id', $materiel->reception_id)->decrement('somme');
                }

                /** @var \App\Models\User $user */
                $user = Auth::user();

                MaterielSupprime::create([
                    'nom'             => $materiel->nom,
                    'numero_serie'    => $materiel->numero_serie,
                    'categorie'       => $materiel->categorie->nom ?? 'Inconnue',
                    'fournisseur'     => $materiel->reception->fournisseur ?? 'N/A',
                    'supprime_le'     => now(),
                    'par_utilisateur' => $user->name ?? 'Système',
                ]);

                $materiel->pieces()->delete();
                $materiel->delete();

                return back()->with('success', '🗑️ Matériel supprimé.');
            });
        } catch (\Exception $e) {
            return back()->with('error', '❌ Erreur : ' . $e->getMessage());
        }
    }

    /**
     * 6. Export CSV
     * CORRIGÉ : Ajout du statut physique dans l'export
     */
 public function exportRange(Request $request)
{
    $query = Materiel::with(['demande', 'pieces']);

    // 1. Filtrage par dates si présentes
    if ($request->filled('debut') && $request->filled('fin')) {
        $query->whereBetween('created_at', [$request->debut, $request->fin]);
        $periode = [
            'debut' => \Carbon\Carbon::parse($request->debut)->format('d/m/Y'),
            'fin' => \Carbon\Carbon::parse($request->fin)->format('d/m/Y')
        ];
    } else {
        // Si pas de dates, on définit une chaîne simple
        $periode = "Inventaire Global Complet";
    }

    $materiels = $query->get();
    $date = now()->format('d/m/Y H:i');

    // 2. Envoi des variables à la vue
    // On doit passer 'materiels', 'periode' et 'date'
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.inventaire_global', [
        'materiels' => $materiels,
        'periode'   => $periode,
        'date'      => $date
    ]);

    return $pdf->stream('Rapport_Inventaire.pdf');
}
}
