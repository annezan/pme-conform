<?php

/**
 * Middleware CheckRole — Vérifie que l'utilisateur a le rôle requis.
 *
 * Utilisation dans les routes : ->middleware('role:admin,manager')
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            abort(401, 'Non authentifié.');
        }

        if (! $request->user()->hasAnyRole($roles)) {
            abort(403, 'Accès non autorisé pour votre rôle.');
        }

        return $next($request);
    }
}
