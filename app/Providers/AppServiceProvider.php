<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL; // Import indispensable pour le HTTPS

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Corrige l'erreur de longueur de clé pour les anciennes versions de MySQL
        Schema::defaultStringLength(191);

        // --- SÉCURITÉ TÉLÉCHARGEMENT ---
        // Force le protocole HTTPS si vous n'êtes pas en environnement local (localhost)
        // Cela évite que Chrome bloque les téléchargements (Mixed Content)
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
    }
}
