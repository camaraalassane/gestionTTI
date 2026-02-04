<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * Le template racine chargé lors de la première visite.
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Détermine la version du contenu (utile pour le cache).
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Définit les props partagées par défaut.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user() ? [
                'id'            => $request->user()->id,
                'name'          => $request->user()->name,
                'role'          => $request->user()->role,
                'code_materiel' => $request->user()->code_materiel,
                // AJOUTE CETTE LIGNE :
                'has_granted_access' => $request->session()->has('material_access_granted'),
            ] : null,
        ],

            // Gestion des notifications Flash (indispensable pour tes retours de code)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // Optimisation de Ziggy : On utilise une "Closure" (fn) 
            // pour que les routes ne soient chargées que si nécessaire.
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }
}