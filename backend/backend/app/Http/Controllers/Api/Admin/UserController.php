<?php

/**
 * Controleur UserController — Gestion des utilisateurs (admin).
 */

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewUserCredentialsMail;
use App\Models\Client;
use App\Models\User;
use App\Services\Security\GenerateurMotDePasse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/admin/users/{id}",
    get: new OA\Get(
        operationId: "admin-users-show",
        summary: "Afficher un utilisateur",
        description: "Retourne les détails d'un utilisateur spécifique avec son rôle et ses clients",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'utilisateur",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de l'utilisateur",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Utilisateur non trouvé"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)")
        ]
    ),
    put: new OA\Put(
        operationId: "admin-users-update",
        summary: "Modifier un utilisateur",
        description: "Met à jour les informations d'un utilisateur existant",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'utilisateur",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, nullable: true, example: "Dupont"),
                    new OA\Property(property: "prenom", type: "string", maxLength: 255, nullable: true, example: "Jean"),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true, example: "jean.dupont@example.com"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, nullable: true, example: "+33 6 12 34 56 78"),
                    new OA\Property(property: "poste", type: "string", maxLength: 255, nullable: true, example: "Consultant"),
                    new OA\Property(property: "role_id", type: "integer", nullable: true, example: 3),
                    new OA\Property(property: "client_id", type: "integer", nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur mis à jour",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Utilisateur non trouvé"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "admin-users-destroy",
        summary: "Supprimer un utilisateur",
        description: "Supprime définitivement un utilisateur du système",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'utilisateur",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur supprimé",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Utilisateur supprime.")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Non autorisé (impossible de supprimer son propre compte)"),
            new OA\Response(response: 404, description: "Utilisateur non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/users",
    get: new OA\Get(
        operationId: "admin-users-index",
        summary: "Lister tous les utilisateurs",
        description: "Retourne la liste paginée de tous les utilisateurs avec rôles et clients",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "recherche",
                in: "query",
                description: "Recherche dans nom, prénom, email",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre d'éléments par page",
                required: false,
                schema: new OA\Schema(type: "integer", default: 15)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des utilisateurs",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/User")
                        ),
                        new OA\Property(
                            property: "links",
                            type: "object",
                            properties: [
                                new OA\Property(property: "first", type: "string"),
                                new OA\Property(property: "last", type: "string"),
                                new OA\Property(property: "prev", type: "string", nullable: true),
                                new OA\Property(property: "next", type: "string", nullable: true)
                            ]
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer"),
                                new OA\Property(property: "from", type: "integer"),
                                new OA\Property(property: "last_page", type: "integer"),
                                new OA\Property(property: "per_page", type: "integer"),
                                new OA\Property(property: "to", type: "integer"),
                                new OA\Property(property: "total", type: "integer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)")
        ]
    ),
    post: new OA\Post(
        operationId: "admin-users-store",
        summary: "Créer un utilisateur",
        description: "Crée un nouvel utilisateur avec rôles et permissions",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nom", "prenom", "email", "password", "role_id"],
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, example: "Dupont"),
                    new OA\Property(property: "prenom", type: "string", maxLength: 255, example: "Jean"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "jean.dupont@example.com"),
                    new OA\Property(property: "password", type: "string", minLength: 8, example: "password123"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, nullable: true, example: "+33 6 12 34 56 78"),
                    new OA\Property(property: "poste", type: "string", maxLength: 255, nullable: true, example: "Consultant"),
                    new OA\Property(property: "role_id", type: "integer", example: 3),
                    new OA\Property(property: "client_id", type: "integer", nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Utilisateur créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "message", type: "string", example: "Utilisateur créé avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role:id,name', 'clients:id,raison_sociale');

        if ($request->filled('recherche')) {
            $terme = $request->recherche;
            $query->where(function ($q) use ($terme) {
                $q->where('nom', 'ilike', "%{$terme}%")
                  ->orWhere('prenom', 'ilike', "%{$terme}%")
                  ->orWhere('email', 'ilike', "%{$terme}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        // Conformement au cahier des charges : le mot de passe N'EST PAS saisi par
        // l'admin. Il est genere automatiquement, envoye par email a l'utilisateur,
        // et celui-ci sera oblige de le changer a sa premiere connexion.
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => [
                'required', 'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'telephone' => 'nullable|string|max:20',
            'poste' => 'nullable|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'client_id' => 'nullable|exists:clients,id',
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un compte actif.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail saisie n\'est pas valide.',
            'role_id.required' => 'Veuillez sélectionner un rôle.',
            'role_id.exists' => 'Le rôle sélectionné n\'existe pas.',
            'client_id.exists' => 'L\'entreprise sélectionnée n\'existe pas.',
        ]);

        $motDePasseTemp = GenerateurMotDePasse::temporaire(16);

        // Si un user soft-delete existe avec cet email, on le RESTAURE avec les
        // nouvelles infos et un nouveau mdp temporaire (transparent pour l'admin).
        $existant = User::onlyTrashed()->where('email', $data['email'])->first();
        if ($existant) {
            $existant->restore();
            $existant->update([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'password' => bcrypt($motDePasseTemp),
                'telephone' => $data['telephone'] ?? null,
                'poste' => $data['poste'] ?? null,
                'is_active' => true,
                'compte_valide' => true,
                'valide_le' => now(),
                'valide_par' => $request->user()->id,
                'must_change_password' => true,
                'mdp_temporaire_expire_le' => now()->addDays(7),
                'email_verified_at' => now(),
                'role_id' => $data['role_id'],
            ]);
            if (! empty($data['client_id'])) {
                $existant->clients()->syncWithoutDetaching([$data['client_id']]);
            }

            $this->envoyerEmailCredentials($existant, $motDePasseTemp, $data['client_id'] ?? null, $request->user());

            return response()->json([
                'user' => $existant->load('role:id,name', 'clients:id,raison_sociale'),
            ], 201);
        }

        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'password' => bcrypt($motDePasseTemp),
            'telephone' => $data['telephone'] ?? null,
            'poste' => $data['poste'] ?? null,
            'is_active' => true,
            // Compte cree par un admin via /admin/utilisateurs : auto-valide.
            'compte_valide' => true,
            'valide_le' => now(),
            'valide_par' => $request->user()->id,
            // Mot de passe temporaire : changement obligatoire a la 1ere connexion.
            'must_change_password' => true,
            'mdp_temporaire_expire_le' => now()->addDays(7),
            'email_verified_at' => now(),
            'role_id' => $data['role_id'],
        ]);

        if (! empty($data['client_id'])) {
            $user->clients()->syncWithoutDetaching([$data['client_id']]);
        }

        $this->envoyerEmailCredentials($user, $motDePasseTemp, $data['client_id'] ?? null, $request->user());

        return response()->json(['user' => $user->load('role:id,name', 'clients:id,raison_sociale')], 201);
    }

    /**
     * Envoie au nouvel utilisateur ses identifiants (incluant le mot de passe
     * temporaire en clair) par e-mail. Echec silencieux : ne bloque pas la
     * creation si l'envoi echoue (logs warning + admin peut reset le mdp).
     */
    private function envoyerEmailCredentials(User $user, string $motDePasseTemp, ?int $clientId, User $createdBy): void
    {
        $nomEntreprise = $clientId
            ? optional(Client::find($clientId))->raison_sociale
            : null;

        try {
            Mail::to($user->email)->send(new NewUserCredentialsMail(
                user: $user,
                motDePasseTemporaire: $motDePasseTemp,
                nomEntreprise: $nomEntreprise,
                createPar: trim($createdBy->prenom . ' ' . $createdBy->nom),
            ));
        } catch (\Throwable $e) {
            Log::warning("Email credentials non envoye pour user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(User $user): JsonResponse
    {
        $user->load('role:id,name', 'clients:id,raison_sociale');

        return response()->json(['user' => $user]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'telephone' => 'sometimes|string|max:20',
            'poste' => 'sometimes|string|max:255',
            'role_id' => 'sometimes|exists:roles,id',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $clientId = array_key_exists('client_id', $data) ? $data['client_id'] : '__not_provided__';
        unset($data['client_id']);

        $user->update($data);

        // Gestion du rattachement entreprise
        if ($clientId !== '__not_provided__') {
            if ($clientId) {
                $user->clients()->sync([$clientId]);
            } else {
                $user->clients()->detach();
            }
        }

        
        return response()->json(['user' => $user->fresh()->load('role:id,name', 'clients:id,raison_sociale')]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprime.']);
    }

    /**
     * Bascule l'etat actif/inactif d'un utilisateur.
     * Un compte desactive ne peut plus se connecter (cf. middleware EnsureUserIsActive).
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Impossible de désactiver votre propre compte.',
            ], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => $user->is_active
                ? 'Compte activé. L\'utilisateur peut désormais se connecter.'
                : 'Compte désactivé. L\'utilisateur ne peut plus se connecter.',
            'user' => $user->fresh(['role:id,name', 'clients:id,raison_sociale']),
        ]);
    }
}
