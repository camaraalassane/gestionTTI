<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. Middlewares globaux pour Inertia
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // 2. Définition de TOUS les alias
        $middleware->alias([
            'admin'           => \App\Http\Middleware\AdminMiddleware::class,
            'hasMaterialCode' => \App\Http\Middleware\EnsureHasMaterialCode::class,
            // AJOUT DE L'ALIAS USER
            'user.only'       => \App\Http\Middleware\UserOnly::class, 
        ]);

        // 3. Redirection automatique des invités
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
