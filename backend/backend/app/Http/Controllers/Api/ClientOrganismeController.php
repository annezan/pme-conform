<?php

/**
 * ClientOrganismeController — Informations generales du client/organisme
 * (responsable de traitement + DPO) utilisees par le registre MOBISOFT.
 *
 * 1 enregistrement par client (HasOne).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientOrganisme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/clients/{client_id}/organisme",
    get: new OA\Get(
        operationId: "clients-organisme-show",
        summary: "Afficher l'organisme du client",
        description: "Retourne les informations de l'organisme (responsable traitement + DPO)",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "client_id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Informations de l'organisme",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "organisme", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "client_id", type: "integer"),
                            new OA\Property(property: "rt_nom", type: "string", nullable: true, example: "Dupont Jean"),
                            new OA\Property(property: "rt_fonction", type: "string", nullable: true, example: "Directeur Général"),
                            new OA\Property(property: "rt_adresse", type: "string", nullable: true, example: "123 rue de la République"),
                            new OA\Property(property: "rt_code_postal", type: "string", nullable: true, example: "75001"),
                            new OA\Property(property: "rt_ville", type: "string", nullable: true, example: "Paris"),
                            new OA\Property(property: "rt_pays", type: "string", nullable: true, example: "France"),
                            new OA\Property(property: "rt_telephone", type: "string", nullable: true, example: "+33 1 23 45 67 89"),
                            new OA\Property(property: "rt_email", type: "string", format: "email", nullable: true, example: "jean.dupont@example.com"),
                            new OA\Property(property: "dpo_nom", type: "string", nullable: true, example: "Martin Sophie"),
                            new OA\Property(property: "dpo_adresse", type: "string", nullable: true),
                            new OA\Property(property: "dpo_code_postal", type: "string", nullable: true),
                            new OA\Property(property: "dpo_ville", type: "string", nullable: true),
                            new OA\Property(property: "dpo_pays", type: "string", nullable: true),
                            new OA\Property(property: "dpo_telephone", type: "string", nullable: true),
                            new OA\Property(property: "dpo_email", type: "string", format: "email", nullable: true, example: "dpo@example.com")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "clients-organisme-upsert",
        summary: "Mettre à jour l'organisme du client",
        description: "Crée ou met à jour les informations de l'organisme du client",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "client_id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "rt_nom", type: "string", maxLength: 255, nullable: true, example: "Dupont Jean"),
                    new OA\Property(property: "rt_fonction", type: "string", maxLength: 255, nullable: true, example: "Directeur Général"),
                    new OA\Property(property: "rt_adresse", type: "string", nullable: true),
                    new OA\Property(property: "rt_code_postal", type: "string", maxLength: 20, nullable: true),
                    new OA\Property(property: "rt_ville", type: "string", maxLength: 100, nullable: true),
                    new OA\Property(property: "rt_pays", type: "string", maxLength: 100, nullable: true),
                    new OA\Property(property: "rt_telephone", type: "string", maxLength: 30, nullable: true),
                    new OA\Property(property: "rt_email", type: "string", format: "email", maxLength: 255, nullable: true),
                    new OA\Property(property: "dpo_nom", type: "string", maxLength: 255, nullable: true),
                    new OA\Property(property: "dpo_adresse", type: "string", nullable: true),
                    new OA\Property(property: "dpo_code_postal", type: "string", maxLength: 20, nullable: true),
                    new OA\Property(property: "dpo_ville", type: "string", maxLength: 100, nullable: true),
                    new OA\Property(property: "dpo_pays", type: "string", maxLength: 100, nullable: true),
                    new OA\Property(property: "dpo_telephone", type: "string", maxLength: 30, nullable: true),
                    new OA\Property(property: "dpo_email", type: "string", format: "email", maxLength: 255, nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Organisme mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "organisme", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class ClientOrganismeController extends Controller
{
    public function show(Client $client): JsonResponse
    {
        $organisme = $client->organisme ?: new ClientOrganisme(['client_id' => $client->id]);

        return response()->json(['organisme' => $organisme]);
    }

    public function upsert(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'rt_nom' => 'nullable|string|max:255',
            'rt_fonction' => 'nullable|string|max:255',
            'rt_adresse' => 'nullable|string',
            'rt_code_postal' => 'nullable|string|max:20',
            'rt_ville' => 'nullable|string|max:100',
            'rt_pays' => 'nullable|string|max:100',
            'rt_telephone' => 'nullable|string|max:30',
            'rt_email' => 'nullable|email|max:255',
            'dpo_nom' => 'nullable|string|max:255',
            'dpo_adresse' => 'nullable|string',
            'dpo_code_postal' => 'nullable|string|max:20',
            'dpo_ville' => 'nullable|string|max:100',
            'dpo_pays' => 'nullable|string|max:100',
            'dpo_telephone' => 'nullable|string|max:30',
            'dpo_email' => 'nullable|email|max:255',
        ]);

        $organisme = ClientOrganisme::updateOrCreate(['client_id' => $client->id], $data);

        return response()->json(['organisme' => $organisme]);
    }
}
