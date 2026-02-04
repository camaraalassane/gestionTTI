<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Si l'utilisateur est admin, on lui interdit l'accès aux routes "User"
        // et on le redirige vers sa gestion des comptes.
        if (auth()->check() && auth()->user()->role === 'admin') {
            return redirect()->route('admin.users.index');
        }

        return $next($request);
    }
}
