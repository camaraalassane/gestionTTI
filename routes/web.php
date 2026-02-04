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
// 1. NAVIGATION ADMIN (Gestion des utilisateurs & Corbeille)
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
    // NAVIGATION DEMANDES (Les 10 Routes "Libres" pour User Simple)
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

    // ---------------------------------------------------------------------
    // NAVIGATION SÉCURISÉE (Protégée par hasMaterialCode)
    // ---------------------------------------------------------------------
    Route::middleware(['hasMaterialCode'])->group(function () {
// Dashboard global (Libre)
    Route::get('/main', [DashboardController::class, 'index'])->name('dashboard');

        // 1. Matériel & Stock
        Route::get('/list', [MaterielController::class, 'list'])->name('materiel.index');
        Route::get('/stock', [MaterielController::class, 'index'])->name('materiel.indexmat');
        Route::post('/batch', [MaterielController::class, 'store_group'])->name('materiel.store_group');
        Route::get('/exp', [MaterielController::class, 'exportRange'])->name('materiel.export');
        Route::resource('asset', MaterielController::class)->except(['index'])->names('materiel');

        // 2. Inventaire / Audit
        Route::resource('check', InventaireController::class)->names('inventaire');
        Route::get('/check/{id}/pdf', [InventaireController::class, 'downloadPdf'])->name('inventaire.pdf');

        // 3. Module Paramètres (Catégories/Services) -> DÉPLACÉ ICI
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
    }); // Fin du middleware hasMaterialCode
}); // Fin du middleware user.only

// =========================================================================
// 3. PROFIL COMMUN
// =========================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/me', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/me/up', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/me/del', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
