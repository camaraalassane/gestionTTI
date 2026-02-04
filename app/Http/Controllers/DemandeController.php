<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\Materiel;
use App\Models\Service;
use App\Models\PieceMateriel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DemandeController extends Controller
{
    /**
     * 1. Liste des demandes "En attente"
     */
public function index(Request $request)
{
    $search = $request->input('search');

    $demandes = Demande::query()
        // On charge les pièces de la demande ET la relation matériel avec ses propres pièces
        ->with([
            'pieces', 
            'materiel.pieces' // Nécessaire pour savoir si le matériel est censé avoir des composants
        ])
        ->where('statut', 'En attente')
        ->latest()
        ->when($search, function ($query, $search) {
            $query->where(function($q) use ($search) {
                $q->where('numcomande', 'like', "%{$search}%")
                  ->orWhere('service_beneficiaire', 'like', "%{$search}%")
                  ->orWhere('nom_materiel', 'like', "%{$search}%")
                  ->orWhere('numero_serie', 'like', "%{$search}%")
                  ->orWhere('demandeur_nom', 'like', "%{$search}%");
            });
        })
        ->paginate(15)
        ->withQueryString();

    // Transformation de la collection pour ajouter le flag "a_des_pieces_au_total"
    $demandes->getCollection()->transform(function ($demande) {
        // On vérifie si le matériel associé existe et s'il a au moins une pièce enregistrée en base
        $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
        return $demande;
    });

    return Inertia::render('demandes/index', [
        'demandes' => $demandes,
        'filters' => $request->only(['search'])
    ]);
}

   /**
 * 2. Formulaire de création
 */
public function create(Request $request)
{
    // On récupère les matériels :
    // 1. Soit ceux qui sont 'Disponible'
    // 2. SOIT ceux qui ont au moins une pièce dont la demande_id est nulle
    $materiels = Materiel::with(['categorie', 'pieces'])
        ->where('etat', 'Disponible')
        ->orWhereHas('pieces', function ($query) {
            $query->whereNull('demande_id');
        })
        ->get();

    return Inertia::render('demandes/create', [
        'materiels' => $materiels,
        'services'  => Service::select('id', 'nom')->get(),
    ]);
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
    ]);

    try {
        return DB::transaction(function () use ($validated, $request) {
            // --- Génération du Numéro de Commande ---
            $prefix = 'CMD-' . date('Y') . '-';
            $lastDemande = Demande::where('numcomande', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            $lastNum = $lastDemande ? intval(substr($lastDemande->numcomande, -4)) : 0;
            $numCmd = $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

            foreach ($validated['items'] as $item) {
                /** @var \App\Models\Materiel $mat */
                $mat = Materiel::findOrFail($item['materiel_id']);

                // 1. Création de la Demande
                $demande = Demande::create([
                    'numcomande' => $numCmd,
                    'materiel_id' => $mat->id,
                    'nom_materiel' => $mat->nom,
                    'nbredemande' => $item['nbredemande'],
                    'numero_serie' => $mat->numero_serie,
                    'categorie' => $mat->categorie->nom ?? 'N/A',
                    'demandeur_nom' => $validated['demandeur_nom'],
                    'service_beneficiaire' => $validated['service_beneficiaire'],
                    'date_demande' => $validated['date_demande'],
                    'statut' => 'En attente',
                    'description' => $item['description'] ?? '',
                ]);

                // 2. Logique de Verrouillage
                if (isset($item['type_sortie']) && $item['type_sortie'] === 'pieces') {
                    // CAS A : SORTIE DE PIÈCES UNIQUEMENT (Le matériel reste dispo)
                    if (!empty($item['pieces_selectionnees'])) {
                        PieceMateriel::whereIn('id', $item['pieces_selectionnees'])->update([
                            'demande_id' => $demande->id,
                            'statut' => 'En attente'
                        ]);

                        $noms = PieceMateriel::whereIn('id', $item['pieces_selectionnees'])->pluck('nom_piece')->toArray();
                        $demande->update([
                            'description' => "SORTIE PIÈCES : " . implode(', ', $noms) . " | " . ($item['description'] ?? '')
                        ]);
                    }
                } else {
                    // CAS B : SORTIE UNITÉ (Complet ou Seul)
                    // On verrouille le Matériel
                    $mat->update(['demande_id' => $demande->id, 'etat' => 'En attente']);

                    // On ne verrouille les pièces QUE si elles ont été cochées
                    if (!empty($item['pieces_selectionnees'])) {
                        PieceMateriel::whereIn('id', $item['pieces_selectionnees'])->update([
                            'demande_id' => $demande->id,
                            'statut' => 'En attente'
                        ]);
                    } else {
                        // Optionnel : On marque dans la description que c'est une sortie sans accessoires
                        $demande->update([
                            'description' => "(MATÉRIEL SEUL) " . ($item['description'] ?? '')
                        ]);
                    }
                }
            }
            return redirect()->route('demandes.index')->with('success', "Commande $numCmd enregistrée.");
        });
    } catch (\Exception $e) {
        Log::error("Erreur Store: " . $e->getMessage());
        return back()->with('error', "Erreur : " . $e->getMessage());
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
                // Vérifier si des pièces sont liées à cette demande précise
                $aDesPiecesSelectionnees = PieceMateriel::where('demande_id', $demande->id)->exists();

                if ($aDesPiecesSelectionnees) {
                    // LOGIQUE PIÈCES : On livre les pièces, le matériel reste DISPONIBLE
                    PieceMateriel::where('demande_id', $demande->id)->update(['statut' => 'Livré']);
                } else if ($demande->materiel) {
                    // LOGIQUE UNITÉ : Pas de pièces, donc on livre le matériel entier
                    $service = Service::where('nom', $demande->service_beneficiaire)->first();
                    $demande->materiel->update([
                        'etat' => 'Livré',
                        'demande_id' => $demande->id,
                        'service_id' => $service ? $service->id : $demande->materiel->service_id
                    ]);
                }
                $demande->update(['statut' => 'Validé']);
            }
            return back()->with('success', "Validé avec succès.");
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
        // On garde tes relations et on ajoute materiel.pieces pour le flag
        ->with(['pieces:id,demande_id,nom_piece,numero_serie', 'materiel.pieces'])
        ->select('id', 'materiel_id', 'nom_materiel', 'numero_serie', 'service_beneficiaire', 'statut', 'nbredemande', 'demandeur_nom', 'description', 'date_demande')
        ->latest()
        ->get();

    // AJOUT UNIQUEMENT DE LA TRANSFORMATION
    $demandes->transform(function ($demande) {
        $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
        return $demande;
    });
    // FIN DE L'AJOUT

    return Inertia::render('demandes/GestionService', [
        'demandes' => $demandes,
        'services' => Service::select('id', 'nom')->get()
    ]);
}

    /**
     * 6. Clôturer / Archiver
     */
 public function cloturer_groupe(Request $request)
{
    $ids = $request->input('ids');
    if (!$ids || empty($ids)) return back()->with('error', 'Aucune sélection.');

    try {
        DB::transaction(function () use ($ids) {
            // 1. Mise à jour groupée des PIÈCES rattachées à ces demandes
            PieceMateriel::whereIn('demande_id', $ids)->update(['statut' => 'Livré']);

            // 2. Mise à jour groupée des MATÉRIELS rattachés à ces demandes
            Materiel::whereIn('demande_id', $ids)->update(['etat' => 'Livré']);

            // 3. Passage au statut final pour les demandes
            Demande::whereIn('id', $ids)->update(['statut' => 'Clôturé']);
        });

        return back()->with('success', 'Demandes clôturées et stock mis à jour.');
    } catch (\Exception $e) {
        Log::error("Erreur Clôture: " . $e->getMessage());
        return back()->with('error', 'Une erreur est survenue lors de la clôture.');
    }
}


    /**
     * 7. Mise à jour manuelle du S/N
     */
    public function updateSerialNumber(Request $request, $id)
    {
        $request->validate(['numero_serie' => 'required|string']);
        $demande = Demande::findOrFail($id);
        $demande->update(['numero_serie' => $request->numero_serie]);

        return back()->with('success', 'Numéro de série mis à jour.');
    }

    /**
     * 8. Impression du Bon
     */
 public function imprimer_bon(Request $request, $service)
{
    $serviceNom = trim($service);
    $demandeur = $request->query('demandeur');

    // On charge 'pieces' ET 'materiel' pour avoir les noms et S/N
    $query = Demande::with(['pieces', 'materiel'])->where('service_beneficiaire', $serviceNom);

    if ($demandeur) {
        $query->where('demandeur_nom', $demandeur);
    }

    $demandes = $query->whereIn('statut', ['Validé', 'En attente', 'Clôturé'])->get();

    // On transforme pour que 'item.nom_materiel' existe directement comme dans ton template
   $demandesPretes = $demandes->map(function ($demande) {
    return array_merge($demande->toArray(), [
        'nom_materiel' => $demande->materiel->nom ?? 'MATÉRIEL INCONNU',
        'numero_serie' => $demande->materiel->numero_serie ?? '—',
        'nbredemande'  => $demande->nombre_article ?? 1,
        // CETTE LIGNE EST LA CLÉ :
        // On vérifie si le matériel possède des pièces rattachées dans la table globale
        'a_des_pieces_au_total' => $demande->materiel ? $demande->materiel->pieces()->exists() : false,
    ]);
});

    if ($demandes->isEmpty()) {
        return back()->with('error', "Aucune demande trouvée.");
    }

    return Inertia::render('demandes/BonCommande', [
        'service'   => $serviceNom,
        'demandes'  => $demandesPretes, // On envoie les données transformées
        'demandeur' => $demandeur ?? ($demandes->first()->demandeur_nom ?? ''),
        'date'      => $request->query('date') ?? now()->format('d/m/Y')
    ]);
}

    /**
     * 9. Historique
     */
 public function historique(Request $request)
{
    // On ajoute 'materiel.pieces' dans le with pour la vérification
    $query = Demande::with(['pieces', 'materiel.pieces'])
        ->where('statut', 'Clôturé')
        ->latest();

    if ($request->has('search')) {
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

    $historique = $query->paginate(15)->withQueryString();

    // AJOUT UNIQUEMENT DE CETTE TRANSFORMATION
    $historique->getCollection()->transform(function ($demande) {
        $demande->a_des_pieces_au_total = $demande->materiel && $demande->materiel->pieces->isNotEmpty();
        return $demande;
    });
    // FIN DE L'AJOUT

    return Inertia::render('demandes/Historique', [
        'historique' => $historique,
        'services'   => Service::select('id', 'nom')->get(),
        'filters'    => $request->only(['search'])
    ]);
}

    /**
     * 10. Suppression / Annulation
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $demande = Demande::findOrFail($id);

            // 1. Libérer le matériel SEULEMENT s'il appartient à cette demande
            // Cela évite de remettre en stock un matériel déjà livré ailleurs
            Materiel::where('demande_id', $demande->id)->update([
                'demande_id' => null,
                'etat' => 'Disponible'
            ]);

            // 2. Libérer uniquement les pièces liées à cette demande précise
            PieceMateriel::where('demande_id', $demande->id)->update([
                'demande_id' => null,
                'statut' => 'En Stock'
            ]);

            $demande->delete();
            return back()->with('success', 'Annulation réussie. Les éléments ont été libérés sans toucher aux autres livraisons.');
        });
    }
}
