<?php
namespace App\Http\Controllers;

use App\Models\Reception;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Materiel;
use Barryvdh\DomPDF\Facade\Pdf;
class ReceptionController extends Controller
{
   public function index()
{
    // Si le dossier est "materiel" (minuscule)
    // Et le fichier est "ReceptionContracts.vue"
    return Inertia::render('materiel/ReceptionContracts', [
        'receptions' => Reception::with('categorie:id,nom')
            ->orderBy('created_at', 'desc')
            ->get()
    ]);
}
// N'oublie pas d'importer le modèle Materiel en haut

public function getMaterielsJson($id)
{
    // 1. On trouve d'abord la ligne de réception sur laquelle on a cliqué
    $receptionInitiale = Reception::findOrFail($id);

    // 2. Au lieu de filtrer par 'reception_id', on filtre par 'numero_contrat'
    // Pour récupérer TOUT ce qui appartient à ce contrat, peu importe la ligne.
    return Materiel::whereHas('reception', function ($query) use ($receptionInitiale) {
            $query->where('numero_contrat', $receptionInitiale->numero_contrat);
        })
        ->with('pieces') // Charge les pièces pour chaque matériel
        ->get();
}

public function downloadContrat($id)
{
    $reception = Reception::findOrFail($id);

    if (!$reception->scan_contrat) {
        return back()->with('error', 'Aucun fichier associé à ce contrat.');
    }

    // On récupère le chemin absolu sur le disque
    $path = storage_path('app/public/' . str_replace('public/', '', $reception->scan_contrat));

    if (!file_exists($path)) {
        return back()->with('error', 'Fichier physique introuvable sur le serveur.');
    }

    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $fileName = "Contrat_{$reception->numero_contrat}.{$extension}";

    // Nettoyage du tampon de sortie
    if (ob_get_length()) ob_end_clean();

    // Utilisation de la réponse globale download (plus fiable pour l'IDE)
    return response()->download($path, $fileName, [
        'Content-Type' => 'application/pdf', // Force le type si c'est un PDF
    ]);
}

public function exportPdf($id)
{
    $reception = Reception::findOrFail($id);

    // Récupération de tous les matériels du même contrat
    $materiels = Materiel::whereHas('reception', function ($query) use ($reception) {
            $query->where('numero_contrat', $reception->numero_contrat);
        })
        ->with(['pieces', 'categorie'])
        ->get();

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.inventaire_contrat', [
        'reception' => $reception,
        'materiels' => $materiels,
        'date' => now()->format('d/m/Y')
    ]);

    return $pdf->download("Inventaire_{$reception->numero_contrat}.pdf");
}

}
