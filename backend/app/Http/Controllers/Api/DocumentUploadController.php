<?php

/**
 * Controleur DocumentUploadController — Upload de documents.
 *
 * Recoit un fichier, extrait le texte, cree le Document en base
 * et dispatch le job de traitement asynchrone (chunking + embeddings).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Services\Audit\AuditService;
use App\Services\Document\ExtractorFactory;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/documents/upload",
    post: new OA\Post(
        operationId: "documents-upload",
        summary: "Uploader un document",
        description: "Upload un fichier PDF/Word et lance le traitement automatique d'extraction de texte",
        tags: ["Documents"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["fichier"],
                    properties: [
                        new OA\Property(
                            property: "fichier",
                            type: "string",
                            format: "binary",
                            description: "Fichier PDF ou Word (max 50MB)"
                        ),
                        new OA\Property(
                            property: "mission_id",
                            type: "integer",
                            nullable: true,
                            description: "ID de la mission associée"
                        ),
                        new OA\Property(
                            property: "type",
                            type: "string",
                            nullable: true,
                            enum: ["document_client", "document_interne", "referentiel"],
                            description: "Type de document"
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Document uploadé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Document uploadé et en cours de traitement."),
                        new OA\Property(property: "document", ref: "#/components/schemas/Document")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation du fichier")
        ]
    )
)]

class DocumentUploadController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private ExtractorFactory $extractorFactory,
    ) {}

    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $fichier = $request->file('fichier');

        // Creer le document en base
        $document = Document::create([
            'mission_id' => $request->mission_id,
            'uploaded_by' => auth()->id(),
            'titre' => $request->titre,
            'description' => $request->description,
            'nom_fichier_original' => $fichier->getClientOriginalName(),
            'type_mime' => $fichier->getMimeType(),
            'taille_octets' => $fichier->getSize(),
            'type' => $request->type ?? 'document_client',
            'statut' => 'en_attente',
            'is_confidentiel' => $request->boolean('is_confidentiel', true),
            'hash_fichier' => hash_file('sha256', $fichier->path()),
        ]);

        // Attacher le fichier via Spatie Media Library
        $document->addMedia($fichier)->toMediaCollection('fichiers');

        // Tenter l'extraction de texte immediatement
        try {
            $media = $document->getFirstMedia('fichiers');
            if ($media && $this->extractorFactory->supporte($document->type_mime)) {
                $contenu = $this->extractorFactory->extraire($media->getPath(), $document->type_mime);
                $document->update(['contenu_extrait' => $contenu]);
            }
        } catch (\Throwable $e) {
            // L'extraction sera retentee dans le job
        }

        // Dispatcher le job de traitement (chunking + embeddings).
        // dispatchAfterResponse : execute le job dans le meme process PHP juste
        // apres l'envoi de la reponse — pas besoin d'un `php artisan queue:work`.
        ProcessDocumentJob::dispatchAfterResponse($document);

        // Audit
        $this->audit->uploadDocument($document);

        return response()->json([
            'document' => $document->only([
                'id', 'titre', 'nom_fichier_original', 'type_mime',
                'taille_octets', 'type', 'statut',
            ]),
            'message' => 'Document uploade avec succes. Traitement en cours.',
        ], 201);
    }
}
