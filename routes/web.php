<?php

use App\Http\Controllers\{
    ProfileController,
    MaterielController,
    DemandeController,
    ParametreController,
    DashboardController,
    ReceptionController,
    InventaireController,
    AdminController
};

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

// --- REDIRECTION LOGIQUE ---
Route::get('/', function () {
    if (!Auth::check()) return redirect()->route('login');
    return Auth::user()->role === 'admin'
        ? redirect()->route('admin.stats.global')
        : redirect()->route('selection');
});

// =========================================================================
// ROUTES API POUR CHARGEMENT DYNAMIQUE
// =========================================================================
Route::middleware(['auth'])->prefix('api')->group(function () {
    // Route pour les demandes (sorties) - utilisée par DemandeController
    Route::get('/modeles/search', [DemandeController::class, 'searchModeles'])->name('api.modeles.search');
    Route::get('/materiels/by-modele/{modele_id}', [DemandeController::class, 'getMaterielsByModele'])->name('api.materiels.by-modele');

    // AJOUT : Routes pour les statistiques des services
    Route::get('/service-stats/{serviceId}', [DashboardController::class, 'getServiceStats'])->name('api.service-stats');
    Route::get('/services-stats', [DashboardController::class, 'getAllServicesStats'])->name('api.services-stats');
       // ⬇️⬇️⬇️ AJOUTE CETTE LIGNE ⬇️⬇️⬇️
    Route::get('/modeles-par-service', [DashboardController::class, 'getModelesParService'])->name('api.modeles-par-service');
});

// =========================================================================
// 1. NAVIGATION ADMIN
// =========================================================================
Route::middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/role', [AdminController::class, 'updateRole'])->name('users.updateRole');
    Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('users.destroy');
    Route::get('/stats', [AdminController::class, 'globalDashboard'])->name('stats.global');
    Route::get('/bin', [AdminController::class, 'listTrash'])->name('trash.index');
    Route::post('/bin/{id}/back', [AdminController::class, 'restoreFromTrash'])->name('trash.restore');
    Route::delete('/bin/{id}/kill', [AdminController::class, 'forceDelete'])->name('trash.force');
    Route::post('/key/{user}/set', [AdminController::class, 'generateCode'])->name('users.generateCode');
    Route::post('/key/{user}/del', [AdminController::class, 'revokeCode'])->name('users.revokeCode');
});

// =========================================================================
// 2. ZONE UTILISATEUR (user.only)
// =========================================================================
Route::middleware(['auth', 'verified', 'user.only'])->group(function () {

    // --- LE HUB (Accès Libre) ---
    Route::get('/hub', function () {
        return Inertia::render('SelectionPage');
    })->name('selection');

    // Validation du code
    Route::post('/auth-code', [MaterielController::class, 'verifyAccess'])->name('materiel.verify');

    // ---------------------------------------------------------------------
    // NAVIGATION DEMANDES (ACCÈS SANS CODE)
    // ---------------------------------------------------------------------
    Route::get('/form', [DemandeController::class, 'create'])->name('demandes.create');
    Route::get('/area', [DemandeController::class, 'gestionService'])->name('demandes.gestion_service');
    Route::get('/logs', [DemandeController::class, 'historique'])->name('demandes.historique');
    Route::get('/edit/{service}', [DemandeController::class, 'editGroupe'])->name('demandes.edit_groupe');
    Route::get('/pdf/{service}', [DemandeController::class, 'imprimer_bon'])->name('demandes.imprimer_bon');
    Route::post('/ok', [DemandeController::class, 'validerGroupe'])->name('demandes.valider_groupe');
    Route::post('/end', [DemandeController::class, 'cloturer_groupe'])->name('demandes.cloturer_groupe');
    Route::post('/save', [DemandeController::class, 'store_group'])->name('demandes.store_group');
    Route::post('/sn/{id}', [DemandeController::class, 'updateSerialNumber'])->name('demandes.update_sn');
    Route::put('/sync', [DemandeController::class, 'updateGroupe'])->name('demandes.update_groupe');
    Route::resource('forms', DemandeController::class)->except(['show', 'create'])->names('demandes');
    Route::get('/demandes/export-pdf', [DemandeController::class, 'exportPDF'])->name('demandes.pdf');
    Route::delete('/demandes/commande/{numcomande}', [DemandeController::class, 'destroy_by_commande'])->name('demandes.destroy_by_commande');

    // ---------------------------------------------------------------------
    // NAVIGATION SÉCURISÉE (Protégée par hasMaterialCode)
    // ---------------------------------------------------------------------
    Route::middleware(['hasMaterialCode'])->group(function () {

        Route::get('/main', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/materiel/{id}/historique', [MaterielController::class, 'historique'])->name('materiel.historique');
        Route::get('/materiel/{id}/historique/pdf', [MaterielController::class, 'exportHistorique'])->name('materiel.historique.pdf');

        // 1. Matériel & Stock
        // Route pour la réception de stock (recherche de modèles)
        Route::get('/recherche-modeles', [MaterielController::class, 'searchModelesReception'])->name('materiel.recherche-modeles');

        Route::put('/modele/{modele}', [MaterielController::class, 'updateModele'])->name('materiel.update.modele');
        Route::get('/list', [MaterielController::class, 'list'])->name('materiel.index');
        Route::get('/materiel/export/{format}', [MaterielController::class, 'export'])->name('materiel.export');
        Route::get('/stock', [MaterielController::class, 'index'])->name('materiel.indexmat');
        Route::post('/batch', [MaterielController::class, 'store_group'])->name('materiel.store_group');
        Route::get('/check-contrat/{numero}', [ReceptionController::class, 'checkContrat'])->name('reception.check');
        Route::resource('asset', MaterielController::class)->except(['index'])->names('materiel');

        // 2. Inventaire / Audit
        Route::post('/audit/clear-cache', [InventaireController::class, 'clearCache'])->name('inventaire.clear-cache');
        Route::resource('audit', InventaireController::class)->names('inventaire');
        Route::get('/audit/{id}/pdf', [InventaireController::class, 'downloadPdf'])->name('inventaire.pdf');

        // 3. Module Paramètres
        Route::get('/setup', [ParametreController::class, 'index'])->name('parametres.index');
        Route::post('/setup/cat', [ParametreController::class, 'storeCategorie'])->name('categories.store');
        Route::delete('/setup/cat/{id}', [ParametreController::class, 'destroyCategorie'])->name('categories.destroy');
        Route::post('/setup/srv', [ParametreController::class, 'storeService'])->name('services.store');
        Route::delete('/setup/srv/{id}', [ParametreController::class, 'destroyService'])->name('services.destroy');

        // 4. Contrats & Réceptions
        Route::get('/docs', [ReceptionController::class, 'index'])->name('reception.index');
        Route::get('/docs/dl/{id}', [ReceptionController::class, 'downloadContrat'])->name('reception.download');
        Route::get('/docs/api/{id}', [ReceptionController::class, 'getMaterielsJson'])->name('reception.api');
        Route::get('/docs/pdf/{id}', [ReceptionController::class, 'exportPdf'])->name('reception.pdf');
        Route::get('/check-sn/{sn}', [MaterielController::class, 'checkSn']);
        Route::get('/docs/api/lots/{id}', [ReceptionController::class, 'getLotsJson'])->name('reception.api.lots');
        Route::get('/docs/pdf-lot/{lotId}', [ReceptionController::class, 'exportPdfLot'])->name('reception.pdf.lot');

    }); // Fin hasMaterialCode
}); // Fin user.only

// =========================================================================
// 3. PROFIL COMMUN
// =========================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/me', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/me/up', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/me/del', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
