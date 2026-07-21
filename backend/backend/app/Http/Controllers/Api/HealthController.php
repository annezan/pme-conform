<?php

/**
 * Controleur HealthController — Endpoints de sante de la plateforme.
 *
 * Fournit un resume operationnel pour monitoring externe (Uptime Kuma, Grafana...).
 * Ne necessite pas d'authentification pour faciliter le probing.
 */

namespace App\Http\Controllers\Api;

use App\Contracts\LLMConnectorInterface;
use App\Http\Controllers\Controller;
use App\Services\RAG\PgvectorChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/health/ping",
    get: new OA\Get(
        operationId: "health-ping",
        summary: "Health check léger",
        description: "Vérification simple de disponibilité de l'application",
        tags: ["System"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application disponible",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "ok"),
                        new OA\Property(property: "timestamp", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
                        new OA\Property(property: "app", type: "string", example: "DCP Platform"),
                        new OA\Property(property: "version", type: "string", example: "1.0.0")
                    ]
                )
            ),
            new OA\Response(response: 503, description: "Service indisponible")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/health/detailed",
    get: new OA\Get(
        operationId: "health-detailed",
        summary: "Health check détaillé",
        description: "Vérification complète de tous les services (DB, migrations, pgvector, Ollama, queue)",
        tags: ["System"],
        responses: [
            new OA\Response(
                response: 200,
                description: "État de santé détaillé des services",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "db", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", enum: ["ok", "error"]),
                            new OA\Property(property: "message", type: "string", nullable: true),
                            new OA\Property(property: "latency_ms", type: "number", nullable: true)
                        ]),
                        new OA\Property(property: "migrations", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", enum: ["ok", "error"]),
                            new OA\Property(property: "message", type: "string", nullable: true),
                            new OA\Property(property: "pending_count", type: "integer", nullable: true)
                        ]),
                        new OA\Property(property: "pgvector", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", enum: ["ok", "error", "not_available"]),
                            new OA\Property(property: "message", type: "string", nullable: true)
                        ]),
                        new OA\Property(property: "ollama", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", enum: ["ok", "error", "not_available"]),
                            new OA\Property(property: "message", type: "string", nullable: true),
                            new OA\Property(property: "models_count", type: "integer", nullable: true)
                        ]),
                        new OA\Property(property: "queue", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", enum: ["ok", "error"]),
                            new OA\Property(property: "message", type: "string", nullable: true),
                            new OA\Property(property: "pending_jobs", type: "integer", nullable: true)
                        ]),
                        new OA\Property(property: "overall", type: "string", enum: ["healthy", "degraded", "unhealthy"], example: "healthy"),
                        new OA\Property(property: "timestamp", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z")
                    ]
                )
            ),
            new OA\Response(response: 503, description: "Service indisponible")
        ]
    )
)]

class HealthController extends Controller
{
    /**
     * Health check leger (200 si l'app repond).
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'app' => config('app.name'),
            'version' => config('app.version', 'n/a'),
        ]);
    }

    /**
     * Health check approfondi : DB + migrations + pgvector + Ollama + queue.
     */
    public function detaille(): JsonResponse
    {
        $resultats = [
            'db' => $this->verifierDb(),
            'migrations' => $this->verifierMigrations(),
            'pgvector' => $this->verifierPgvector(),
            'ollama' => $this->verifierOllama(),
            'queue' => $this->verifierQueue(),
            'storage' => $this->verifierStorage(),
        ];

        $operationnel = collect($resultats)->every(fn ($r) => $r['ok'] === true || ($r['degrade'] ?? false));
        $statusCode = $operationnel ? 200 : 503;

        return response()->json([
            'status' => $operationnel ? 'ok' : 'degrade',
            'timestamp' => now()->toIso8601String(),
            'checks' => $resultats,
        ], $statusCode);
    }

    private function verifierDb(): array
    {
        try {
            DB::select('SELECT 1');

            return ['ok' => true, 'driver' => DB::getDriverName()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifierMigrations(): array
    {
        $tables = ['users', 'clients', 'traitements', 'chartes', 'signatures', 'plans_actions', 'registres_kyc'];
        $manquantes = [];
        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                $manquantes[] = $t;
            }
        }

        return ['ok' => empty($manquantes), 'tables_manquantes' => $manquantes];
    }

    private function verifierPgvector(): array
    {
        try {
            $dispo = app(PgvectorChecker::class)->estDisponible();

            return ['ok' => $dispo, 'degrade' => ! $dispo, 'mode' => $dispo ? 'vectoriel' : 'full-text (fallback)'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'degrade' => true, 'error' => $e->getMessage()];
        }
    }

    private function verifierOllama(): array
    {
        try {
            $dispo = app(LLMConnectorInterface::class)->estDisponible();

            return ['ok' => $dispo, 'degrade' => ! $dispo];
        } catch (\Throwable $e) {
            return ['ok' => false, 'degrade' => true, 'error' => $e->getMessage()];
        }
    }

    private function verifierQueue(): array
    {
        try {
            $attente = DB::table('jobs')->count();
            $echecs = DB::table('failed_jobs')->count();

            return [
                'ok' => true,
                'driver' => config('queue.default'),
                'jobs_en_attente' => $attente,
                'jobs_echoues' => $echecs,
                'alerte' => $attente > 100 || $echecs > 10,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifierStorage(): array
    {
        try {
            $path = storage_path('app');
            $ecrivable = is_dir($path) && is_writable($path);

            return ['ok' => $ecrivable, 'writable' => $ecrivable];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
