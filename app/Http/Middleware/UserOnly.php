<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Importe la Façade
use Symfony\Component\HttpFoundation\Response;

class UserOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Utilisation de la Façade au lieu de la fonction globale auth()
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.users.index');
        }

        return $next($request);
    }
}
