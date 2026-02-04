<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth; // Ajouté pour la clarté
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Affiche la liste des utilisateurs et les stats
     */
    public function index()
    {
        return Inertia::render('Admin/UsersList', [
            'users' => User::select('id', 'name', 'email', 'role', 'created_at', 'code_materiel')
                ->latest()
                ->get(),
            'stats' => [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'users' => User::where('role', 'user')->count(),
            ]
        ]);
    }

    /**
     * Créer un nouvel utilisateur directement depuis l'admin
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:user,admin',
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    /**
     * Changer le rôle d'un utilisateur
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        // Sécurité : Empêcher un admin de s'enlever ses propres droits par erreur
        if ($user->id === Auth::id() && $request->role !== 'admin') {
            return back()->withErrors(['error' => 'Vous ne pouvez pas modifier votre propre rôle.']);
        }

        $user->update(['role' => $request->role]);
        return back()->with('success', 'Rôle mis à jour.');
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy(User $user): RedirectResponse
    {
        // Ici Intelephense reconnaît $user->id car vous passez (User $user)
        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'Action impossible sur votre propre compte.']);
        }

        $user->delete();
        return back()->with('success', 'Utilisateur supprimé.');
    }
    /**
     * Générer un code matériel pour un utilisateur
     */
public function generateCode(User $user)
{
    // Génère un code aléatoire de 6 caractères (ex: AB1234)
    $code = strtoupper(Str::random(6));

    $user->update(['code_materiel' => $code]);

    return back()->with('success', "Code généré pour {$user->name} : $code");
}
// Retirer/Supprimer le code
public function revokeCode(User $user)
{
    $user->update(['code_materiel' => null]);

    return back()->with('success', "Accès retiré pour {$user->name}. Le code a été supprimé.");
}
// 1. Afficher la liste des supprimés
public function listTrash()
{
    $deletedItems = DB::table('materiel_supprimes') // Note: vérifiez l'orthographe de votre table
        ->orderBy('supprime_le', 'desc')
        ->get();

    return Inertia::render('Admin/TrashList', [
        'items' => $deletedItems
    ]);
}

// 2. Réinsérer dans la table 'materiels'
public function restoreFromTrash($id)
{
    $item = DB::table('materiel_supprimes')->where('id', $id)->first();

    if ($item) {
        // Protection : si le nom de catégorie est vide, on met 'Général' ou ID 1
        $categorie = DB::table('categories')->where('nom', $item->categorie)->first();
        $categorieId = $categorie ? $categorie->id : 1;

        // On cherche une réception pour ce fournisseur créée AUJOURD'HUI
        $reception = DB::table('receptions')
            ->where('fournisseur', $item->fournisseur)
            ->whereDate('date_livraison', now()->toDateString())
            ->first();

        if ($reception) {
            DB::table('receptions')->where('id', $reception->id)->increment('unite');
            $receptionId = $reception->id;
        } else {
            $receptionId = DB::table('receptions')->insertGetId([
                'fournisseur'    => $item->fournisseur ?? 'TECH NSI',
                'numero_contrat' => 'RESTORE-' . date('Ymd'),
                'date_livraison' => now()->toDateString(),
                'categorie_id'   => $categorieId,
                'nbrcarton'      => 0,
                'unite'          => 1,
            ]);
        }

        DB::table('materiels')->insert([
            'nom'           => $item->nom,
            'numero_serie'  => $item->numero_serie,
            'etat'          => 'neuf',
            'reception_id'  => $receptionId,
            'categorie_id'  => $categorieId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('materiel_supprimes')->where('id', $id)->delete();
        
        return back()->with('success', 'Matériel réintégré au stock avec succès.');
    }

    return back()->with('error', 'Matériel introuvable dans la corbeille.');
}


// 3. Supprimer définitivement de la base de données
public function forceDelete($id)
{
    DB::table('materiel_supprimes')->where('id', $id)->delete();

    return back()->with('success', 'Matériel supprimé définitivement de la base de données.');
}
public function globalDashboard()
{
    return Inertia::render('Admin/GlobalStats', [
        'stats' => [
            'total_materiels' => DB::table('materiels')->count(),
            'total_demandes'  => DB::table('demandes')->count(),
            'services_actifs' => DB::table('services')->count(),
            // Optionnel : matériels déjà attribués
            'materiels_attribues' => DB::table('materiels')->whereNotNull('service_id')->count(),
        ],

        // Groupement des matériels par service (Information réelle d'octroi)
        // On utilise la table 'materiels' car elle possède le 'service_id'
        'demandes_par_service' => DB::table('materiels')
            ->join('services', 'materiels.service_id', '=', 'services.id')
            ->select('services.nom', DB::raw('count(*) as total'))
            ->groupBy('services.id', 'services.nom')
            ->get(),

        // Les 5 services ayant le plus de matériel octroyé
        'top_services' => DB::table('materiels')
            ->join('services', 'materiels.service_id', '=', 'services.id')
            ->select('services.nom', DB::raw('count(*) as total'))
            ->groupBy('services.id', 'services.nom')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get(),

        // Si vous voulez vraiment compter les demandes par leur nom de service (champ texte)
        // Utilisez cette version pour 'demandes_par_service' si nécessaire :

        'demandes_brutes_par_service' => DB::table('demandes')
            ->select('service_beneficiaire as nom', DB::raw('count(*) as total'))
            ->groupBy('service_beneficiaire')
            ->get(),
    ]);
}

}
