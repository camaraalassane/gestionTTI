<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        // 1. Vérifier si l'utilisateur est connecté
        if (!$user) {
            return redirect()->route('login');
        }

        // 2. Si c'est un ADMIN : On laisse passer la requête
        if ($user->role === 'admin') {
            return $next($request);
        }

        // 3. Si c'est un USER (non-admin) : On le redirige vers son espace
        // On suppose que votre route s'appelle 'selection' ou 'user.dashboard'
        return redirect()->route('selection')->with('error', 'Accès administrateur requis. Vous avez été redirigé vers votre espace.');
    }
}
