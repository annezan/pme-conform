<?php

/**
 * Controleur ClientController — CRUD des clients.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\SecteurActivite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/clients",
    get: new OA\Get(
        operationId: "clients-index",
        summary: "Lister tous les clients",
        description: "Retourne la liste paginée des clients avec filtres optionnels",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (prospect, actif, inactif, archive)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["prospect", "actif", "inactif", "archive"])
            ),
            new OA\Parameter(
                name: "recherche",
                in: "query",
                description: "Terme de recherche dans raison sociale",
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
                description: "Liste des clients",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Client")
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
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    ),
    post: new OA\Post(
        operationId: "clients-store",
        summary: "Créer un client",
        description: "Crée un nouveau client avec ses informations",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["raison_sociale"],
                properties: [
                    new OA\Property(property: "raison_sociale", type: "string", maxLength: 255, example: "Techno Corp"),
                    new OA\Property(property: "sigle", type: "string", maxLength: 50, nullable: true, example: "TC"),
                    new OA\Property(property: "secteurs_activite_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 3, 7]),
                    new OA\Property(property: "numero_registre_commerce", type: "string", maxLength: 100, nullable: true, example: "123456789"),
                    new OA\Property(property: "adresse", type: "string", nullable: true, example: "123 Rue de la Tech"),
                    new OA\Property(property: "ville", type: "string", maxLength: 100, nullable: true, example: "Paris"),
                    new OA\Property(property: "pays", type: "string", maxLength: 100, nullable: true, example: "France"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, nullable: true, example: "+33 1 23 45 67 89"),
                    new OA\Property(property: "email", type: "string", format: "email", maxLength: 255, nullable: true, example: "contact@technocorp.com"),
                    new OA\Property(property: "site_web", type: "string", maxLength: 255, nullable: true, example: "https://www.technocorp.com"),
                    new OA\Property(property: "contact_principal_nom", type: "string", maxLength: 255, nullable: true, example: "Jean Dupont"),
                    new OA\Property(property: "contact_principal_email", type: "string", format: "email", maxLength: 255, nullable: true, example: "j.dupont@technocorp.com"),
                    new OA\Property(property: "contact_principal_telephone", type: "string", maxLength: 20, nullable: true, example: "+33 6 12 34 56 78"),
                    new OA\Property(property: "contact_principal_poste", type: "string", maxLength: 255, nullable: true, example: "Directeur Technique"),
                    new OA\Property(property: "statut", type: "string", enum: ["prospect", "actif", "inactif", "archive"], nullable: true, example: "prospect"),
                    new OA\Property(property: "notes", type: "string", nullable: true, example: "Client important pour le Q4"),
                                    ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Client créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "client", ref: "#/components/schemas/Client")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/clients/{id}",
    get: new OA\Get(
        operationId: "clients-show",
        summary: "Afficher un client",
        description: "Retourne les détails complets d'un client avec ses missions et référentiels",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du client",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "client", ref: "#/components/schemas/Client")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "clients-update",
        summary: "Mettre à jour un client",
        description: "Met à jour les informations d'un client existant",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
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
                    new OA\Property(property: "raison_sociale", type: "string", maxLength: 255, example: "Techno Corp Updated"),
                    new OA\Property(property: "sigle", type: "string", maxLength: 50, nullable: true, example: "TCU"),
                    new OA\Property(property: "secteurs_activite_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 3, 7]),
                    new OA\Property(property: "adresse", type: "string", nullable: true, example: "456 Avenue de l'Innovation"),
                    new OA\Property(property: "ville", type: "string", maxLength: 100, nullable: true, example: "Lyon"),
                    new OA\Property(property: "pays", type: "string", maxLength: 100, nullable: true, example: "France"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, nullable: true, example: "+33 4 12 34 56 78"),
                    new OA\Property(property: "email", type: "string", format: "email", maxLength: 255, nullable: true, example: "contact.updated@technocorp.com"),
                    new OA\Property(property: "site_web", type: "string", maxLength: 255, nullable: true, example: "https://updated.technocorp.com"),
                    new OA\Property(property: "contact_principal_nom", type: "string", maxLength: 255, nullable: true, example: "Marie Durand"),
                    new OA\Property(property: "contact_principal_email", type: "string", format: "email", maxLength: 255, nullable: true, example: "m.durand@technocorp.com"),
                    new OA\Property(property: "contact_principal_telephone", type: "string", maxLength: 20, nullable: true, example: "+33 6 98 76 54 32"),
                    new OA\Property(property: "contact_principal_poste", type: "string", maxLength: 255, nullable: true, example: "Directrice Marketing"),
                    new OA\Property(property: "statut", type: "string", enum: ["prospect", "actif", "inactif", "archive"], nullable: true, example: "actif"),
                    new OA\Property(property: "notes", type: "string", nullable: true, example: "Client converti - contrat signé"),
                                    ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Client mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "client", ref: "#/components/schemas/Client")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "clients-destroy",
        summary: "Supprimer un client",
        description: "Supprime un client de manière irréversible",
        tags: ["Clients"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Client supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Client supprimé.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/clients/{id}/documents",
    get: new OA\Get(
        operationId: "clients-documents",
        summary: "Lister les documents d'un client",
        description: "Retourne tous les documents du client (toutes missions confondues)",
        tags: ["Clients", "Documents"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des documents du client",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "mission", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "reference", type: "string"),
                                        new OA\Property(property: "titre", type: "string")
                                    ]),
                                    new OA\Property(property: "chunks_count", type: "integer"),
                                    new OA\Property(property: "type", type: "string", example: "document_client"),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Client non trouvé")
        ]
    )
)]

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query()->withCount('missions');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('recherche')) {
            $query->where('raison_sociale', 'ilike', "%{$request->recherche}%");
        }

        // User sans permission de gerer tous les clients : ne voir que ceux qu'il a assignes.
        $user = $request->user();
        if (! $user->hasPermissionTo('view-all-clients')) {
            $query->whereHas('utilisateurs', fn ($q) => $q->where('users.id', $user->id));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'raison_sociale' => 'required|string|max:255',
            'sigle' => 'nullable|string|max:50',
            'description_activite' => 'required|string|min:10',
            'secteurs_activite_ids' => 'nullable|array',
            'secteurs_activite_ids.*' => 'integer|exists:secteurs_activite,id',
            'numero_registre_commerce' => 'nullable|string|max:100',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'pays' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site_web' => 'nullable|string|max:255',
            'contact_principal_nom' => 'nullable|string|max:255',
            'contact_principal_email' => 'nullable|email|max:255',
            'contact_principal_telephone' => 'nullable|string|max:20',
            'contact_principal_poste' => 'nullable|string|max:255',
            'statut' => 'nullable|in:prospect,actif,inactif,archive',
            'notes' => 'nullable|string',
            ]);

        // Traiter les secteurs d'activité pour gérer les tableaux d'IDs
        $secteursActiviteIds = $this->traiterSecteursActiviteIds($data['secteurs_activite_ids'] ?? []);
        unset($data['secteurs_activite_ids']);

        $data['created_by'] = $request->user()->id;
        $client = Client::create($data);
        
        // Synchroniser les secteurs d'activité avec la table normalisée
        if (! empty($secteursActiviteIds)) {
            $client->synchroniserSecteurs($secteursActiviteIds);
        }
        
        return response()->json(['client' => $client->load('secteursActivite:id,nom')], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load([
            'missions' => fn ($q) => $q->latest()->take(10),
            'utilisateurs:id,nom,prenom,email',
            'secteursActivite:id,nom',
        ]);

        return response()->json(['client' => $client]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'raison_sociale' => 'sometimes|string|max:255',
            'sigle' => 'nullable|string|max:50',
            'description_activite' => 'sometimes|required|string|min:10',
            'secteurs_activite_ids' => 'nullable|array',
            'secteurs_activite_ids.*' => 'integer|exists:secteurs_activite,id',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'pays' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contact_principal_nom' => 'nullable|string|max:255',
            'contact_principal_email' => 'nullable|email|max:255',
            'contact_principal_telephone' => 'nullable|string|max:20',
            'contact_principal_poste' => 'nullable|string|max:255',
            'statut' => 'nullable|in:prospect,actif,inactif,archive',
            'notes' => 'nullable|string',
                    ]);

        // Traiter les secteurs d'activité pour gérer les tableaux d'IDs
        $secteursActiviteIds = $this->traiterSecteursActiviteIds($data['secteurs_activite_ids'] ?? null);
        unset($data['secteurs_activite_ids']);

        $data['updated_by'] = $request->user()->id;
        $client->update($data);

        // Synchroniser les secteurs d'activité si fournis
        if ($secteursActiviteIds !== null) {
            $client->synchroniserSecteurs($secteursActiviteIds);
        }

        return response()->json(['client' => $client->fresh(['secteursActivite:id,nom'])]);
    }

    public function destroy(Client $client): JsonResponse
    {
        // auth()->id() : l'ancien code utilisait $request->user() alors que
        // $request n'etait pas parametre de la methode (erreur fatale).
        $client->update(['deleted_by' => auth()->id()]);

        // Comptes rattaches a cette entreprise AVANT sa suppression.
        $utilisateurs = $client->utilisateurs()->get();

        $client->delete();

        // On supprime aussi les comptes de l'entreprise (symetrie avec la
        // suppression d'un utilisateur). On epargne le compte connecte et tout
        // compte encore rattache a une AUTRE entreprise active : la relation
        // clients() exclut deja l'entreprise qu'on vient de soft-delete.
        foreach ($utilisateurs as $utilisateur) {
            if ($utilisateur->id === auth()->id()) {
                continue;
            }

            if ($utilisateur->clients()->count() === 0) {
                $utilisateur->delete();
            }
        }

        return response()->json(['message' => 'Client supprime.']);
    }

    /**
     * Liste tous les documents du client (toutes missions confondues).
     * Utilise par le stepper d'analyse pour pre-charger les fichiers existants.
     */
    public function documents(Client $client): JsonResponse
    {
        // Les documents appartiennent au Client directement (documents.client_id),
        // independamment d'une mission. On filtre par client_id pour rattraper :
        //   - les uploads via /mes-documents (mission_id = NULL apres la refonte Option B)
        //   - les pieces de matrice (mission_id rempli)
        //   - les documents dont la mission a ete soft-deleted (withTrashed sur la relation)
        $documents = Document::query()
            ->where('client_id', $client->id)
            ->with(['mission' => fn ($q) => $q->withTrashed()->select('id', 'reference', 'titre', 'deleted_at')])
            ->withCount('chunks')
            ->where('type', 'document_client')
            ->latest()
            ->get();

        return response()->json(['data' => $documents]);
    }

    /**
     * Traite le champ secteurs_activite_ids pour gérer les tableaux d'IDs
     * Convertit "1,2,3" en [1, 2, 3]
     */
    private function traiterSecteursActiviteIds($secteursActiviteIds): ?array
    {
        if (is_string($secteursActiviteIds)) {
            $secteursActiviteIds = explode(',', $secteursActiviteIds);
            $secteursActiviteIds = array_map('trim', $secteursActiviteIds);
            $secteursActiviteIds = array_filter($secteursActiviteIds, 'is_numeric');
            $secteursActiviteIds = array_map('intval', $secteursActiviteIds);
        }
        
        return $secteursActiviteIds;
    }
}
