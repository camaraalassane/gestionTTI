<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasMaterialCode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
public function handle(Request $request, Closure $next): Response
{
    // Si la session "accès accordé" n'existe pas, on renvoie à la sélection
    if (!$request->session()->has('material_access_granted')) {
        return redirect()->route('selection')->with('error', "Veuillez saisir votre code d'accès.");
    }

    return $next($request);
}
}
