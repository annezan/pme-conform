<?php

/**
 * Controleur CharteController — Consultation des chartes publiees.
 *
 * Toutes les chartes actives sont visibles par les utilisateurs authentifies.
 * L'administration (admin) peut les publier/modifier — geree separement.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charte;
use App\Models\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/chartes",
    get: new OA\Get(
        operationId: "chartes-index",
        summary: "Lister les chartes actives",
        description: "Retourne la liste des chartes actives avec le statut de signature de l'utilisateur",
        tags: ["Chartes"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des chartes avec statut de signature",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Charte")
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/chartes/{id}",
    get: new OA\Get(
        operationId: "chartes-show",
        summary: "Afficher une charte",
        description: "Retourne les détails complets d'une charte avec son contenu et statut de signature",
        tags: ["Chartes"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la charte",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de la charte",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "charte", ref: "#/components/schemas/Charte"),
                        new OA\Property(property: "signature_existante", type: "object", nullable: true, properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "signee_le", type: "string", format: "date-time"),
                            new OA\Property(property: "signature_valide", type: "boolean"),
                            new OA\Property(property: "ip_signature", type: "string", nullable: true)
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Charte non disponible")
        ]
    )
)]

class CharteController extends Controller
{
    /**
     * Liste des chartes actives avec statut de signature de l'utilisateur.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $clientId = $user->clients()->value('clients.id');

        $chartes = Charte::actives()
            ->orderBy('type')
            ->orderByDesc('publiee_le')
            ->get()
            ->map(function (Charte $charte) use ($user, $clientId) {
                $signatureActive = Signature::where([
                    'charte_id' => $charte->id,
                    'user_id' => $user->id,
                    'statut' => 'signee',
                ])->first();

                return [
                    'id' => $charte->id,
                    'type' => $charte->type,
                    'titre' => $charte->titre,
                    'version' => $charte->version,
                    'publiee_le' => $charte->publiee_le?->toIso8601String(),
                    'obligatoire' => $charte->obligatoire,
                    'signee' => $signatureActive !== null,
                    'signature_valide' => $signatureActive
                        && $signatureActive->hash_contenu_signe === $charte->hash_contenu,
                    'signee_le' => $signatureActive?->signee_le?->toIso8601String(),
                    'client_rattache' => $clientId !== null,
                ];
            });

        return response()->json(['data' => $chartes]);
    }

    /**
     * Detail d'une charte avec son contenu HTML et hash pour signature.
     */
    public function show(Request $request, Charte $charte): JsonResponse
    {
        abort_unless($charte->active, 404, 'Charte non disponible.');

        $user = $request->user();
        $signatureActive = Signature::where([
            'charte_id' => $charte->id,
            'user_id' => $user->id,
            'statut' => 'signee',
        ])->latest('signee_le')->first();

        return response()->json([
            'charte' => $charte,
            'signature_existante' => $signatureActive ? [
                'id' => $signatureActive->id,
                'signee_le' => $signatureActive->signee_le?->toIso8601String(),
                'signature_valide' => $signatureActive->hash_contenu_signe === $charte->hash_contenu,
                'ip_signature' => $signatureActive->ip_signature,
            ] : null,
        ]);
    }
}
