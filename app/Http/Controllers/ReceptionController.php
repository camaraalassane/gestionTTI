<?php

namespace App\Http\Controllers;

use App\Models\Reception;
use App\Models\Materiel;
use App\Models\Contrat; // Respect de ta minuscule sur le modèle
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    /**
     * Affiche la liste des réceptions (vue principale)
     */
public function index()
    {
        return Inertia::render('materiel/ReceptionContracts', [
            'receptions' => Reception::with(['categorie:id,nom', 'contrat'])
                ->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString()
        ]);
    }

   /**
 * NOUVEAU : Récupère la liste des différents lots pour la traçabilité
 * Utilisé par la Modale 2
 */
public function getLotsJson($id)
{
    $receptionInitiale = Reception::findOrFail($id);
    
    // On récupère toutes les réceptions du contrat
    $receptions = Reception::where('numero_contrat', $receptionInitiale->numero_contrat)
        ->with(['materiels.modele', 'materiels.categorie']) // Charge les matériels avec leurs modèles
        ->orderBy('date_livraison', 'asc')
        ->get();

    // On groupe par date de livraison
    $lots = $receptions->groupBy(function($reception) {
        return $reception->date_livraison;
    })->map(function($group, $date) {
        $premiereReception = $group->first();
        
        // Récupérer tous les matériels de ce lot (toutes les réceptions de cette date)
        $materielsDuLot = collect();
        foreach ($group as $reception) {
            $materielsDuLot = $materielsDuLot->concat($reception->materiels);
        }
        
        return [
            'id' => $premiereReception->id,
            'date_livraison' => $date,
            'quantite_recue' => $materielsDuLot->count(),
            'materiels' => $materielsDuLot->map(function($materiel) {
                return [
                    'id' => $materiel->id,
                    'nom' => $materiel->modele->nom ?? $materiel->nom ?? 'Nom inconnu',
                    'numero_serie' => $materiel->numero_serie,
                    'etat' => $materiel->etat,
                    'statut' => $materiel->statut,
                    'categorie' => $materiel->categorie->nom ?? null,
                ];
            })->values(),
        ];
    })->values();

    return response()->json($lots);
}

    /**
     * RÉCUPÉRÉ DE TES DONNÉES : Détails de tous les matériels d'un contrat
     * Utilisé par la Modale 1
     */
public function getMaterielsJson($id)
{
    $receptionInitiale = Reception::findOrFail($id);

    return Materiel::whereHas('reception', function ($query) use ($receptionInitiale) {
            $query->where('numero_contrat', $receptionInitiale->numero_contrat);
        })
        ->with([
            'modele:id,nom',        // ICI : La désignation est dans le modèle
            'categorie:id,nom', 
            'pieces:id,materiel_id,nom_piece,statut'
        ])
        ->get()
        ->map(function ($m) {
            return [
                'id' => $m->id,
                'nom' => $m->modele ? $m->modele->nom : 'N/A', // Désignation via la relation
                'numero_serie' => $m->numero_serie,
                'etat' => $m->etat,
                'categorie_nom' => $m->categorie?->nom ?? 'N/A',
                'pieces' => $m->pieces,
                'est_complet' => true, // Adaptez selon votre logique
                'statut' => 'En Stock' // Adaptez selon votre logique
            ];
        });
}

    /**
     * RÉCUPÉRÉ DE TES DONNÉES : API de vérification pour le formulaire
     */
public function checkContrat($numero)
{
    $contrat = Contrat::where('numero_contrat', $numero)->first();

    if ($contrat) {
        $stats = Reception::where('numero_contrat', $numero)
            ->selectRaw('SUM(unite) as total_livre')
            ->first();

        return response()->json([
            'exists'      => true,
            'fournisseur' => $contrat->fournisseur,
            'total_prevu' => (int) $contrat->quantite_totale_prevue,
            'deja_recu'   => (int) ($stats->total_livre ?? 0),
            'scan_contrat'=> $contrat->scan_contrat, // On le prend ici !
        ]);
    }
    return response()->json(['exists' => false]);
}

    /**
     * RÉCUPÉRÉ DE TES DONNÉES : Téléchargement du scan physique
     */
   public function downloadContrat($id)
    {
        $reception = Reception::findOrFail($id);

        if (!$reception->scan_contrat) {
            return back()->with('error', 'Aucun fichier associé.');
        }

        $path = storage_path('app/public/' . $reception->scan_contrat);

        if (!file_exists($path)) {
            return back()->with('error', 'Fichier introuvable sur le serveur.');
        }

        // CORRECTION : Nettoyer le nom du fichier pour éviter l'erreur Symfony
        $safeName = str_replace(['/', '\\'], '-', $reception->numero_contrat);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $fileName = "Contrat_" . $safeName . "." . $extension;

        if (ob_get_length()) ob_end_clean();

        return response()->download($path, $fileName);
    }

    /**
     * RÉCUPÉRÉ DE TES DONNÉES : Export PDF Global
     */
 public function exportPdf($id)
{
    // 1. Charger la réception avec son contrat
    $reception = Reception::with('contrat')->findOrFail($id);

    // 2. Récupérer les matériels avec OPTIMISATION (with)
    $materiels = Materiel::whereHas('reception', function ($query) use ($reception) {
            $query->where('numero_contrat', $reception->numero_contrat);
        })
        ->with([
            'modele',           // AJOUTÉ : Pour avoir le nom du matériel
            'pieces', 
            'categorie'
        ])
        ->get();

    // 3. Transformer les données pour s'assurer que le nom est disponible
    $materielsData = $materiels->map(function($materiel) {
        return (object)[
            'id' => $materiel->id,
            'nom' => $materiel->modele ? $materiel->modele->nom : ($materiel->nom ?? 'N/A'),
            'numero_serie' => $materiel->numero_serie ?? '—',
            'etat' => $materiel->etat ?? 'N/A',
            'statut' => $materiel->statut ?? 'N/A',
            'categorie' => $materiel->categorie,
            'pieces' => $materiel->pieces->map(function($piece) {
                return (object)[
                    'nom_piece' => $piece->nom_piece,
                    'numero_serie' => $piece->numero_serie ?? '—',
                    'statut' => $piece->statut ?? 'N/A'
                ];
            })
        ];
    });

    // 4. NETTOYER le nom du fichier
    $safeNumeroContrat = str_replace(['/', '\\', ' ', '.'], '-', $reception->numero_contrat);
    $fileName = "Inventaire_" . $safeNumeroContrat . ".pdf";

    // 5. Générer le PDF
    $pdf = Pdf::loadView('pdf.inventaire_contrat', [
        'reception' => $reception,
        'materiels' => $materielsData,  // Utiliser les données transformées
        'date' => now()->format('d/m/Y')
    ])->setPaper('a4', 'portrait');

    // 6. Retourner le flux avec le nom nettoyé
    return $pdf->stream($fileName);
}
    /**
     * NOUVEAU : Export PDF spécifique à un SEUL LOT
     */
public function exportPdfLot(Request $request, $lotId)
{
    try {
        $ref = Reception::with(['contrat', 'categorie'])->findOrFail($lotId);
        $dateCible = $ref->date_livraison;
        $numContrat = $ref->numero_contrat;

        // Récupérer les matériels AVEC la relation 'modele'
        $materiels = Materiel::whereHas('reception', function ($query) use ($dateCible, $numContrat) {
                $query->where('date_livraison', $dateCible)
                      ->where('numero_contrat', $numContrat);
            })
            ->with([
                'modele',           // Pour le nom
                'pieces', 
                'categorie:id,nom'
            ])
            ->get();

        // Ajouter le nom du modèle à l'objet matériel
        $materiels->each(function($materiel) {
            // Si le nom n'est pas défini, utiliser celui du modèle
            if (!isset($materiel->nom) && $materiel->modele) {
                $materiel->nom = $materiel->modele->nom;
            }
        });

        $pdf = Pdf::loadView('pdf.inventaire_contrat', [
            'reception' => $ref,
            'materiels' => $materiels,  // On passe les objets, pas un tableau
            'titre' => "BON DE RÉCEPTION - LOT DU " . date('d/m/Y', strtotime($dateCible)),
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $safeNum = str_replace(['/', '\\', ' ', '.'], '-', $numContrat);
        $fileName = "Lot_" . $safeNum . "_" . date('Ymd', strtotime($dateCible)) . ".pdf";

        return $pdf->stream($fileName);
        
    } catch (\Exception $e) {
        \Log::error('Erreur exportPdfLot: ' . $e->getMessage());
        return back()->with('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
    }
}
}
