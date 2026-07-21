<?php

/**
 * Middleware AuditActivity — Journalise chaque requête HTTP dans l'audit trail.
 *
 * Enregistre l'utilisateur, l'IP, l'action (méthode + URI) et le résultat.
 */

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ne journaliser que les actions modificatrices et les accès aux agents
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $resultat = $response->getStatusCode() < 400 ? 'succes' : 'echec';

            AuditLog::enregistrer(
                action: strtolower($request->method()) . '.' . $request->path(),
                description: "Requête {$request->method()} sur {$request->path()}",
                categorie: $this->determinerCategorie($request->path()),
                resultat: $resultat,
            );
        }

        return $response;
    }

    private function determinerCategorie(string $path): string
    {
        if (str_starts_with($path, 'api/agents') || str_starts_with($path, 'agents')) {
            return 'agent';
        }
        if (str_starts_with($path, 'api/documents') || str_starts_with($path, 'documents')) {
            return 'document';
        }
        if (str_starts_with($path, 'login') || str_starts_with($path, 'logout')) {
            return 'auth';
        }
        if (str_starts_with($path, 'admin')) {
            return 'admin';
        }

        return 'general';
    }
}
