<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParametreController extends Controller
{
    /**
     * Affiche la page des paramètres avec la liste complète.
     */
    public function index()
    {
        return Inertia::render('materiel/parametres', [
            'categories' => Categorie::latest()->get(),
            'services'   => Service::latest()->get(),
        ]);
    }

    /**
     * Ajouter une nouvelle catégorie.
     */
    public function storeCategorie(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:categories,nom',
        ]);

        Categorie::create($validated);
        return redirect()->back()->with('success', 'Catégorie ajoutée avec succès !');
    }

    /**
     * Ajouter un nouveau service.
     */
    public function storeService(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:services,nom',
        ]);

        Service::create($validated);
        return redirect()->back()->with('success', 'Service ajouté avec succès !');
    }

    /**
     * Supprimer une catégorie (avec vérification de dépendance).
     */
    public function destroyCategorie($id)
    {
        $categorie = Categorie::findOrFail($id);

        // Vérifier si des matériels utilisent cette catégorie
        if ($categorie->materels()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Impossible de supprimer : cette catégorie contient des matériels.']);
        }

        $categorie->delete();
        return redirect()->back()->with('success', 'Catégorie supprimée.');
    }

    /**
     * Supprimer un service (avec vérification de dépendance).
     */
    public function destroyService($id)
    {
        $service = Service::findOrFail($id);

        // Vérifier si des matériels ou demandes sont liés à ce service
        if ($service->materiels()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Impossible de supprimer : du matériel est affecté à ce service.']);
        }

        $service->delete();
        return redirect()->back()->with('success', 'Service supprimé.');
    }
}
