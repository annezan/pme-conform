<?php

/**
 * OrganigrammeController — API organigramme (Methode 2 etape 2).
 *
 * Soit le client uploade un fichier (mode 'upload'), soit il saisit
 * une structure JSON arborescente (mode 'formulaire').
 *
 *   GET    /missions/{mission}/organigramme         : etat actuel
 *   PUT    /missions/{mission}/organigramme         : update structure ou metadata
 *   POST   /missions/{mission}/organigramme/upload  : upload fichier
 *   POST   /missions/{mission}/organigramme/figer   : fige + declenche generation IA
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenererQuestionnairesJob;
use App\Models\Mission;
use App\Models\Organigramme;
use App\Services\Methode2\GenerateurQuestionnaireIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Storage;

#[OA\PathItem(
    path: "/api/missions/{mission_id}/organigramme",
    get: new OA\Get(
        operationId: "organigramme-show",
        summary: "Afficher l'organigramme",
        description: "Retourne l'état actuel de l'organigramme d'une mission",
        tags: ["Organigramme"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Organigramme de la mission",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "organigramme", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "mission_id", type: "integer"),
                            new OA\Property(property: "mode", type: "string", enum: ["formulaire", "upload"], example: "formulaire"),
                            new OA\Property(property: "structure", type: "object", nullable: true),
                            new OA\Property(property: "fichier_path", type: "string", nullable: true),
                            new OA\Property(property: "valide", type: "boolean", example: false),
                            new OA\Property(property: "valide_le", type: "string", format: "date-time", nullable: true),
                            new OA\Property(property: "validateur", type: "object", nullable: true, properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "nom", type: "string"),
                                new OA\Property(property: "prenom", type: "string")
                            ])
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    ),
    put: new OA\Put(
        operationId: "organigramme-update",
        summary: "Mettre à jour l'organigramme",
        description: "Met à jour la structure ou les métadonnées de l'organigramme",
        tags: ["Organigramme"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "mode", type: "string", enum: ["formulaire", "upload"], nullable: true),
                    new OA\Property(property: "structure", type: "object", nullable: true, example: "Structure arborescente"),
                    new OA\Property(property: "metadata", type: "object", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Organigramme mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "organigramme", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class OrganigrammeController extends Controller
{
    public function __construct(
        private GenerateurQuestionnaireIA $generateur,
    ) {}

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $organigramme = Organigramme::firstOrCreate(['mission_id' => $mission->id], ['mode' => 'formulaire']);

        return response()->json(['organigramme' => $organigramme->load('validateur:id,nom,prenom')]);
    }

    public function update(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $data = $request->validate([
            'mode' => 'nullable|in:upload,formulaire',
            'structure' => 'nullable|array',
            'structure.*.pole' => 'required_with:structure|string|max:255',
            'structure.*.services' => 'nullable|array',
            'structure.*.services.*.nom' => 'nullable|string|max:255',
            'structure.*.services.*.postes' => 'nullable|array',
            'structure.*.services.*.postes.*' => 'string|max:255',
        ]);

        $organigramme = Organigramme::firstOrCreate(['mission_id' => $mission->id], ['mode' => 'formulaire']);
        if (! empty($data)) {
            $organigramme->update($data);
        }

        return response()->json(['organigramme' => $organigramme->fresh()]);
    }

    public function uploaderFichier(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $request->validate(['fichier' => 'required|file|max:20480']); // 20 Mo
        $organigramme = Organigramme::firstOrCreate(['mission_id' => $mission->id], ['mode' => 'upload']);

        // Le fichier uploade par le client supplante l'organigramme genere
        // automatiquement : on bascule en mode upload et le precedent fichier
        // est ecrase.
        if ($organigramme->fichier_chemin && Storage::disk('local')->exists($organigramme->fichier_chemin)) {
            Storage::disk('local')->delete($organigramme->fichier_chemin);
        }
        $fichier = $request->file('fichier');
        $chemin = $fichier->store("organigrammes/{$mission->id}", 'local');

        $organigramme->update([
            'mode' => 'upload',
            'fichier_chemin' => $chemin,
            'fichier_mime' => $fichier->getMimeType(),
            'fichier_nom_original' => $fichier->getClientOriginalName(),
            'fichier_taille_octets' => $fichier->getSize(),
        ]);

        return response()->json([
            'organigramme' => $organigramme->fresh(),
            'message' => 'Fichier organigramme uploade. Il remplace desormais l\'organigramme genere automatiquement.',
        ]);
    }

    /**
     * Supprime le fichier organigramme uploade et revient a la structure
     * generee automatiquement (mode formulaire).
     */
    public function supprimerFichier(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $organigramme = Organigramme::where('mission_id', $mission->id)->firstOrFail();
        if ($organigramme->fichier_chemin && Storage::disk('local')->exists($organigramme->fichier_chemin)) {
            Storage::disk('local')->delete($organigramme->fichier_chemin);
        }

        $organigramme->update([
            'fichier_chemin' => null,
            'fichier_mime' => null,
            'fichier_nom_original' => null,
            'fichier_taille_octets' => null,
            // Si une structure existe, on retombe sur le mode formulaire (genere).
            'mode' => ! empty($organigramme->structure) ? 'formulaire' : 'upload',
        ]);

        return response()->json([
            'organigramme' => $organigramme->fresh(),
            'message' => 'Fichier organigramme supprime.',
        ]);
    }

    /**
     * Sert le fichier organigramme uploade pour previsualisation/telechargement.
     */
    public function telechargerFichier(Request $request, Mission $mission)
    {
        $this->verifierAcces($request->user(), $mission);

        $organigramme = Organigramme::where('mission_id', $mission->id)->firstOrFail();
        if (! $organigramme->fichier_chemin || ! Storage::disk('local')->exists($organigramme->fichier_chemin)) {
            return response()->json(['message' => 'Aucun fichier organigramme.'], 404);
        }

        return Storage::disk('local')->download(
            $organigramme->fichier_chemin,
            $organigramme->fichier_nom_original ?: basename($organigramme->fichier_chemin),
        );
    }

    public function figer(Request $request, Mission $mission): JsonResponse
    {
        $organigramme = Organigramme::where('mission_id', $mission->id)->firstOrFail();

        // Validation contenu minimum
        if ($organigramme->mode === 'formulaire' && empty($organigramme->structure)) {
            return response()->json(['message' => 'L\'organigramme structure est vide.'], 422);
        }
        if ($organigramme->mode === 'upload' && empty($organigramme->fichier_chemin)) {
            return response()->json(['message' => 'Aucun fichier organigramme uploade.'], 422);
        }

        $organigramme->update([
            'statut' => 'fige',
            'valide_par' => $request->user()->id,
            'valide_at' => now(),
            // Marque l'etat de progression "en file" tant que le job n'a
            // pas pris la main. Le front peut polling sur metadata.generation.
            'metadata' => array_merge($organigramme->metadata ?? [], [
                'generation' => ['etat' => 'en_file', 'enqueue_at' => now()->toIso8601String()],
            ]),
        ]);

        // Generation asynchrone : llama3.2:3b sur CPU prend 30-90s par
        // questionnaire. Un organigramme de 5+ poles depasserait largement
        // le timeout HTTP. Le job ecrit son progres dans organigrammes.metadata.
        GenererQuestionnairesJob::dispatch(
            $mission->id,
            $organigramme->id,
            $request->user()->id,
        );

        return response()->json([
            'organigramme' => $organigramme->fresh(),
            'message' => 'Organigramme fige. La generation des questionnaires a demarre en arriere-plan ; consultez l\'etat via metadata.generation.',
        ], 202);
    }

    /**
     * Etat de la generation des questionnaires (polling depuis le front).
     */
    public function progressGeneration(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $organigramme = Organigramme::where('mission_id', $mission->id)->firstOrFail();

        return response()->json([
            'generation' => $organigramme->metadata['generation'] ?? null,
            'nb_questionnaires' => $organigramme->questionnaires()->count(),
        ]);
    }

    private function verifierAcces($user, Mission $mission): void
    {
        if (! $user || $user->hasAnyPermission(['view-organigramme', 'view-all-organigramme'])) {
            return;
        }
        $clientIds = $user->clients()->pluck('clients.id');
        if (! $clientIds->contains($mission->client_id)) {
            abort(403, 'Acces refuse : cette mission ne fait pas partie de votre espace.');
        }
    }
}
