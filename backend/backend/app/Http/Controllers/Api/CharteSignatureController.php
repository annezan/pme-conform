<?php

/**
 * Controleur CharteSignatureController — Signature de chartes IA/sous-traitance.
 *
 * Processus :
 *   1. L'UI affiche la charte + son hash (GET /api/chartes/{id}).
 *   2. L'utilisateur coche la case d'acceptation et soumet le hash qu'il a lu.
 *   3. Le serveur verifie que le hash n'a pas change entre-temps (409 sinon).
 *   4. Une signature existante sur la meme charte est revoquee au profit de la nouvelle.
 *   5. Tracabilite : user + client + IP + user-agent + hash + timestamp.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charte;
use App\Models\Signature;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/chartes/{charte_id}/signer",
    post: new OA\Post(
        operationId: "chartes-signer",
        summary: "Signer une charte",
        description: "Signe électroniquement une charte avec validation d'intégrité du contenu",
        tags: ["Chartes"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "charte_id",
                in: "path",
                description: "ID de la charte",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["accepte_contenu", "hash_affiche"],
                properties: [
                    new OA\Property(property: "accepte_contenu", type: "boolean", example: true),
                    new OA\Property(property: "hash_affiche", type: "string", minLength: 64, maxLength: 64, example: "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Charte signée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "signature", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "charte_id", type: "integer"),
                            new OA\Property(property: "user_id", type: "integer"),
                            new OA\Property(property: "client_id", type: "integer"),
                            new OA\Property(property: "hash_contenu_signe", type: "string"),
                            new OA\Property(property: "ip_signature", type: "string"),
                            new OA\Property(property: "user_agent_signature", type: "string"),
                            new OA\Property(property: "statut", type: "string", example: "signee"),
                            new OA\Property(property: "signee_le", type: "string", format: "date-time")
                        ]),
                        new OA\Property(property: "message", type: "string", example: "Charte signée avec succès.")
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: "Le contenu de la charte a changé",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Le contenu de la charte a changé depuis votre affichage. Rechargez la page pour relire la dernière version.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Charte non active ou permission refusée"),
            new OA\Response(response: 422, description: "Aucune entreprise rattachée"),
            new OA\Response(response: 404, description: "Charte non trouvée")
        ]
    )
)]

class CharteSignatureController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function signer(Request $request, Charte $charte): JsonResponse
    {
        $data = $request->validate([
            'accepte_contenu' => 'required|accepted',
            'hash_affiche' => 'required|string|size:64',
        ]);

        abort_unless($charte->active, 403, 'Cette charte n\'est plus active.');
        abort_unless(
            $request->user()->hasPermissionTo('sign-chartes'),
            403,
            'Vous n\'avez pas la permission de signer une charte.'
        );

        if ($data['hash_affiche'] !== $charte->hash_contenu) {
            return response()->json([
                'message' => 'Le contenu de la charte a change depuis votre affichage. Rechargez la page pour relire la derniere version.',
            ], 409);
        }

        $user = $request->user();
        $clientId = $user->clients()->value('clients.id');

        abort_unless($clientId, 422, 'Aucune entreprise rattachee a votre compte. Contactez l\'administrateur.');

        // Revoquer toute signature active anterieure sur la meme charte
        Signature::where([
            'charte_id' => $charte->id,
            'user_id' => $user->id,
            'statut' => 'signee',
        ])->update([
            'statut' => 'revoquee',
            'revoquee_le' => now(),
            'raison_revocation' => 'Re-signature',
        ]);

        $signature = Signature::create([
            'charte_id' => $charte->id,
            'user_id' => $user->id,
            'client_id' => $clientId,
            'hash_contenu_signe' => $charte->hash_contenu,
            'ip_signature' => $request->ip(),
            'user_agent_signature' => mb_substr((string) $request->userAgent(), 0, 255),
            'statut' => 'signee',
            'signee_le' => now(),
        ]);

        $this->audit->log('charte.signee', [
            'charte_id' => $charte->id,
            'charte_type' => $charte->type,
            'charte_version' => $charte->version,
            'signature_id' => $signature->id,
        ]);

        return response()->json([
            'signature' => $signature,
            'message' => 'Charte signee avec succes.',
        ], 201);
    }

    /**
     * Liste des signatures de l'utilisateur connecte (historique).
     */
    public function mesSignatures(Request $request): JsonResponse
    {
        $signatures = Signature::where('user_id', $request->user()->id)
            ->with('charte:id,type,titre,version,hash_contenu')
            ->orderByDesc('signee_le')
            ->get()
            ->map(fn (Signature $s) => [
                'id' => $s->id,
                'charte' => [
                    'id' => $s->charte->id,
                    'type' => $s->charte->type,
                    'titre' => $s->charte->titre,
                    'version' => $s->charte->version,
                ],
                'statut' => $s->statut,
                'signature_valide' => $s->statut === 'signee'
                    && $s->hash_contenu_signe === $s->charte->hash_contenu,
                'signee_le' => $s->signee_le?->toIso8601String(),
                'revoquee_le' => $s->revoquee_le?->toIso8601String(),
                'raison_revocation' => $s->raison_revocation,
                'ip_signature' => $s->ip_signature,
            ]);

        return response()->json(['data' => $signatures]);
    }

    /**
     * Revoque une signature (l'utilisateur se desengage).
     */
    public function revoquer(Request $request, Signature $signature): JsonResponse
    {
        abort_unless(
            $signature->user_id === $request->user()->id,
            403,
            'Vous ne pouvez revoquer que vos propres signatures.'
        );

        abort_unless($signature->statut === 'signee', 422, 'Cette signature n\'est pas active.');

        $data = $request->validate([
            'raison' => 'nullable|string|max:500',
        ]);

        $signature->update([
            'statut' => 'revoquee',
            'revoquee_le' => now(),
            'raison_revocation' => $data['raison'] ?? 'Revocation par l\'utilisateur',
        ]);

        $this->audit->log('charte.revoquee', [
            'signature_id' => $signature->id,
            'charte_id' => $signature->charte_id,
        ]);

        return response()->json([
            'signature' => $signature->fresh(),
            'message' => 'Signature revoquee. Vous pouvez re-signer la charte a tout moment.',
        ]);
    }

    /**
     * (Admin/Manager) Liste les signatures pour un client donne — pour le suivi.
     */
    public function signaturesClient(Request $request, int $clientId): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionTo('view-signatures'),
            403,
            'Permission view-signatures requise.'
        );

        $signatures = Signature::where('client_id', $clientId)
            ->with(['charte:id,type,titre,version', 'user:id,nom,prenom,email'])
            ->orderByDesc('signee_le')
            ->get();

        return response()->json(['data' => $signatures]);
    }
}
