<?php

/**
 * Middleware ForcePasswordChange — Bloque l'utilisateur sur toutes les routes
 * tant qu'il n'a pas change son mot de passe temporaire (must_change_password = true).
 *
 * Exceptions (routes toujours autorisees) :
 *   - /api/user            (recuperer son profil pour afficher la page)
 *   - /api/user/change-temporary-password (l'endpoint qui change le mdp)
 *   - /api/logout          (se deconnecter)
 *
 * Le frontend doit gerer la redirection automatique vers /changer-mot-de-passe
 * en lisant le flag user.must_change_password dans le payload /api/user.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Routes (path apres /api/) autorisees meme quand must_change_password = true.
     */
    private const ROUTES_AUTORISEES = [
        'user',
        'user/change-temporary-password',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        // Recuperer le path apres /api/ pour comparaison
        $path = ltrim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        if (in_array($path, self::ROUTES_AUTORISEES, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Vous devez changer votre mot de passe temporaire avant d\'acceder a cette ressource.',
            'code' => 'MUST_CHANGE_PASSWORD',
        ], 403);
    }
}
