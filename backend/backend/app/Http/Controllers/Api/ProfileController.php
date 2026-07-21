<?php

/**
 * Controleur ProfileController — Gestion du profil utilisateur connecte.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/profile",
    put: new OA\Put(
        operationId: "profile-update",
        summary: "Mettre à jour le profil utilisateur",
        description: "Met à jour les informations du profil de l'utilisateur connecté",
        tags: ["Profil"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, nullable: true, example: "Durand"),
                    new OA\Property(property: "prenom", type: "string", maxLength: 255, nullable: true, example: "Marie"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, nullable: true, example: "+33 6 98 76 54 32"),
                    new OA\Property(property: "poste", type: "string", maxLength: 255, nullable: true, example: "Directrice Marketing")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Profil mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Profil mis à jour."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/profile/password",
    put: new OA\Put(
        operationId: "profile-password-update",
        summary: "Changer le mot de passe",
        description: "Change le mot de passe de l'utilisateur connecté",
        tags: ["Profil"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", example: "ancien_mot_de_passe123"),
                    new OA\Property(property: "password", type: "string", minLength: 8, example: "nouveau_mot_de_passe123"),
                    new OA\Property(property: "password_confirmation", type: "string", minLength: 8, example: "nouveau_mot_de_passe123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Mot de passe changé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Mot de passe modifié avec succès.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(
                response: 422,
                description: "Erreur de validation",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object")
                    ]
                )
            )
        ]
    )
)]

class ProfileController extends Controller
{
    /**
     * Met a jour le profil de l'utilisateur connecte.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'poste' => 'nullable|string|max:255',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis a jour.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'poste' => $user->poste,
                // Le modele User est mono-role via role_id (pas la pivot multi-role
                // Spatie). On retourne le nom du role dans un tableau pour rester
                // compatible avec le format attendu par le front (['admin']).
                'roles' => $user->role ? [$user->role->name] : [],
                'permissions' => $user->role
                    ? $user->role->permissions()->pluck('name')
                    : collect(),
            ],
        ]);
    }

    /**
     * Change le mot de passe de l'utilisateur connecte.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            // Si l'user avait un mdp temporaire, on clear le flag.
            'must_change_password' => false,
            'mdp_temporaire_expire_le' => null,
        ]);

        return response()->json([
            'message' => 'Mot de passe modifie avec succes.',
        ]);
    }

    /**
     * Endpoint dedie au CHANGEMENT INITIAL d'un mot de passe temporaire.
     * Difference avec updatePassword : pas de current_password requis (le
     * temporaire est en train d'etre remplace), mais l'user DOIT etre dans
     * l'etat must_change_password=true pour utiliser cet endpoint.
     *
     * Au succes : le flag est efface, l'user peut acceder a toute la plateforme.
     */
    public function changeTemporaryPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! $user->must_change_password) {
            return response()->json([
                'message' => 'Aucun changement de mot de passe temporaire n\'est requis pour votre compte.',
            ], 422);
        }

        if ($user->mdp_temporaire_expire_le && $user->mdp_temporaire_expire_le->isPast()) {
            return response()->json([
                'message' => 'Votre mot de passe temporaire a expire. Veuillez utiliser "Mot de passe oublie" pour en demander un nouveau.',
                'code' => 'TEMP_PASSWORD_EXPIRED',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'mdp_temporaire_expire_le' => null,
        ]);

        return response()->json([
            'message' => 'Mot de passe modifie avec succes. Vous pouvez desormais utiliser la plateforme.',
        ]);
    }
}
