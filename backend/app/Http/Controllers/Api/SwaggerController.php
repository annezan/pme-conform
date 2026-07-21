<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "DCP Backend API",
    version: "1.0.0",
    description: "API de gestion du projet DCP - Documentation des clients, missions, et analyses",
    contact: new OA\Contact(
        email: "admin@example.com"
    ),
    license: new OA\License(
        name: "MIT",
        url: "https://opensource.org/licenses/MIT"
    )
)]

#[OA\Server(
    url: "http://localhost:8000",
    description: "Serveur de développement"
)]

#[OA\Server(
    url: "http://localhost:8000",
    description: "Serveur de production"
)]

#[OA\Tag(
    name: "Clients",
    description: "Gestion des clients"
)]

#[OA\Tag(
    name: "Secteurs d'activité",
    description: "Gestion des secteurs d'activité"
)]

#[OA\Tag(
    name: "Authentification",
    description: "Gestion de l'authentification"
)]

#[OA\Tag(
    name: "Missions",
    description: "Gestion des missions"
)]

#[OA\Tag(
    name: "Documents",
    description: "Gestion des documents"
)]

#[OA\Tag(
    name: "Health",
    description: "Health checks"
)]

#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]

#[OA\Schema(
    schema: "SecteurActivite",
    type: "object",
    title: "Secteur d'activité",
    description: "Un secteur d'activité normalisé",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nom", type: "string", example: "Technologie"),
        new OA\Property(property: "description", type: "string", example: "Entreprises technologiques et startups"),
        new OA\Property(property: "code", type: "string", example: "TECH"),
        new OA\Property(property: "is_actif", type: "boolean", example: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_by", type: "integer", nullable: true),
        new OA\Property(property: "updated_by", type: "integer", nullable: true),
        new OA\Property(property: "deleted_by", type: "integer", nullable: true),
        new OA\Property(property: "createdBy", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ]),
        new OA\Property(property: "updatedBy", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ]),
        new OA\Property(property: "deletedBy", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "Client",
    type: "object",
    title: "Client",
    description: "Un client de l'entreprise",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "raison_sociale", type: "string", example: "Techno Corp"),
        new OA\Property(property: "sigle", type: "string", nullable: true, example: "TC"),
        new OA\Property(property: "secteur_activite", type: "string", nullable: true, example: "Technologie"),
        new OA\Property(property: "secteurs_activite", type: "array", items: new OA\Items(type: "string"), example: ["Technologie", "Informatique"]),
        new OA\Property(property: "numero_registre_commerce", type: "string", nullable: true, example: "123456789"),
        new OA\Property(property: "adresse", type: "string", nullable: true, example: "123 Rue de la Tech"),
        new OA\Property(property: "ville", type: "string", nullable: true, example: "Paris"),
        new OA\Property(property: "pays", type: "string", nullable: true, example: "France"),
        new OA\Property(property: "telephone", type: "string", nullable: true, example: "+33 1 23 45 67 89"),
        new OA\Property(property: "email", type: "string", nullable: true, example: "contact@technocorp.com"),
        new OA\Property(property: "site_web", type: "string", nullable: true, example: "https://www.technocorp.com"),
        new OA\Property(property: "contact_principal_nom", type: "string", nullable: true, example: "Jean Dupont"),
        new OA\Property(property: "contact_principal_email", type: "string", nullable: true, example: "j.dupont@technocorp.com"),
        new OA\Property(property: "contact_principal_telephone", type: "string", nullable: true, example: "+33 6 12 34 56 78"),
        new OA\Property(property: "contact_principal_poste", type: "string", nullable: true, example: "Directeur Technique"),
        new OA\Property(property: "statut", type: "string", enum: ["prospect", "actif", "inactif", "archive"], example: "actif"),
        new OA\Property(property: "notes", type: "string", nullable: true, example: "Client important pour le Q4"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "missions_count", type: "integer", example: 3),
        new OA\Property(property: "referentiels", type: "array", items: new OA\Items(
            type: "object",
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "code", type: "string"),
                new OA\Property(property: "titre", type: "string"),
                new OA\Property(property: "autorite", type: "string"),
                new OA\Property(property: "type", type: "string"),
                new OA\Property(property: "secteurs_activite", type: "string")
            ]
        ))
    ]
)]

#[OA\Schema(
    schema: "AuthResponse",
    type: "object",
    title: "Réponse d'authentification",
    description: "Réponse retournée après une connexion réussie",
    properties: [
        new OA\Property(property: "user", type: "object", properties: [
            new OA\Property(property: "id", type: "integer", example: 1),
            new OA\Property(property: "nom", type: "string", example: "Dupont"),
            new OA\Property(property: "prenom", type: "string", example: "Jean"),
            new OA\Property(property: "email", type: "string", format: "email", example: "jean.dupont@example.com"),
            new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string"), example: ["admin", "consultant"])
        ]),
        new OA\Property(property: "token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
        new OA\Property(property: "token_type", type: "string", example: "bearer"),
        new OA\Property(property: "expires_in", type: "integer", example: 3600)
    ]
)]

#[OA\Schema(
    schema: "LoginRequest",
    type: "object",
    title: "Requête de connexion",
    description: "Données nécessaires pour la connexion",
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "jean.dupont@example.com"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password123")
    ]
)]

#[OA\Schema(
    schema: "Mission",
    type: "object",
    title: "Mission",
    description: "Une mission d'audit ou de conseil",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "reference", type: "string", example: "AUD-2024-001"),
        new OA\Property(property: "titre", type: "string", example: "Audit de conformité RGPD"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Audit complet de conformité RGPD pour l'entreprise"),
        new OA\Property(property: "type", type: "string", enum: ["audit_conformite", "accompagnement", "formation", "aipd", "declaration_artci", "autre"], example: "audit_conformite"),
        new OA\Property(property: "statut", type: "string", enum: ["brouillon", "en_cours", "en_revue", "termine", "archive"], example: "en_cours"),
        new OA\Property(property: "priorite", type: "string", enum: ["basse", "normale", "haute", "urgente"], example: "normale"),
        new OA\Property(property: "methode", type: "string", enum: ["methode_1", "methode_2"], example: "methode_2"),
        new OA\Property(property: "progression", type: "integer", nullable: true, example: 65),
        new OA\Property(property: "date_debut", type: "string", format: "date", nullable: true, example: "2024-01-15"),
        new OA\Property(property: "date_echeance", type: "string", format: "date", nullable: true, example: "2024-03-15"),
        new OA\Property(property: "date_cloture", type: "string", format: "date", nullable: true),
        new OA\Property(property: "notes_internes", type: "string", nullable: true, example: "Attention aux délais serrés"),
        new OA\Property(property: "client_id", type: "integer", example: 1),
        new OA\Property(property: "responsable_id", type: "integer", example: 2),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "client", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "raison_sociale", type: "string")
        ]),
        new OA\Property(property: "responsable", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "Document",
    type: "object",
    title: "Document",
    description: "Un document de mission ou client",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nom", type: "string", example: "contrat_client.pdf"),
        new OA\Property(property: "type", type: "string", example: "document_client"),
        new OA\Property(property: "statut", type: "string", enum: ["brouillon", "valide", "archive"], example: "valide"),
        new OA\Property(property: "taille", type: "integer", example: 2048576),
        new OA\Property(property: "mission_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "client_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "uploadeur_id", type: "integer", example: 2),
        new OA\Property(property: "chunks_count", type: "integer", example: 15),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "mission", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "reference", type: "string"),
            new OA\Property(property: "titre", type: "string")
        ]),
        new OA\Property(property: "uploadeur", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "Referentiel",
    type: "object",
    title: "Référentiel",
    description: "Un référentiel légal ou réglementaire",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "code", type: "string", example: "RGPD-2016-679"),
        new OA\Property(property: "titre", type: "string", example: "Règlement Général sur la Protection des Données"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Règlement européen sur la protection des données personnelles"),
        new OA\Property(property: "autorite", type: "string", nullable: true, example: "Union Européenne"),
        new OA\Property(property: "version", type: "string", nullable: true, example: "v2.0"),
        new OA\Property(property: "date_publication", type: "string", format: "date", nullable: true, example: "2016-05-04"),
        new OA\Property(property: "date_entree_vigueur", type: "string", format: "date", nullable: true, example: "2018-05-25"),
        new OA\Property(property: "type", type: "string", enum: ["loi", "decret", "arrete", "directive", "norme", "guide", "autre"], example: "reglement"),
        new OA\Property(property: "statut", type: "string", enum: ["actif", "obsolete", "brouillon"], example: "actif"),
        new OA\Property(property: "contenu_extrait", type: "string", nullable: true, example: "Article 1 - La protection des données à caractère personnel..."),
        new OA\Property(property: "source_url", type: "string", nullable: true, example: "https://eur-lex.europa.eu/"),
        new OA\Property(property: "uploaded_by", type: "integer", nullable: true, example: 2),
        new OA\Property(property: "metadata", type: "object", nullable: true, example: ["langue" => "fr", "pays" => "UE"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(
            property: "secteursActivite",
            type: "array",
            items: new OA\Items(
                type: "object",
                properties: [
                    new OA\Property(property: "id", type: "integer", example: 1),
                    new OA\Property(property: "nom", type: "string", example: "Technologie"),
                    new OA\Property(property: "code", type: "string", nullable: true, example: "TECH"),
                ]
            ),
            example: [
                ["id" => 1, "nom" => "Technologie", "code" => "TECH"],
                ["id" => 3, "nom" => "Santé", "code" => "SANTE"]
            ]
        )
    ]
)]

#[OA\Schema(
    schema: "Analyse",
    type: "object",
    title: "Analyse",
    description: "Une analyse d'écarts de conformité",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "titre", type: "string", nullable: true, example: "Analyse RGPD - Q1 2024"),
        new OA\Property(property: "statut", type: "string", enum: ["en_cours", "termine", "erreur"], example: "termine"),
        new OA\Property(property: "mission_id", type: "integer", example: 1),
        new OA\Property(property: "lanceur_id", type: "integer", example: 2),
        new OA\Property(property: "referentiels_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3]),
        new OA\Property(property: "documents_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 2]),
        new OA\Property(property: "questionnaires_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1]),
        new OA\Property(property: "enrichissement_ia", type: "boolean", example: true),
        new OA\Property(property: "rapport_word_path", type: "string", nullable: true),
        new OA\Property(property: "rapport_pptx_path", type: "string", nullable: true),
        new OA\Property(property: "ecarts_count", type: "integer", example: 15),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "mission", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "reference", type: "string"),
            new OA\Property(property: "titre", type: "string"),
            new OA\Property(property: "client", type: "object", properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "raison_sociale", type: "string")
            ])
        ]),
        new OA\Property(property: "lanceur", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "User",
    type: "object",
    title: "Utilisateur",
    description: "Un utilisateur du système",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nom", type: "string", example: "Dupont"),
        new OA\Property(property: "prenom", type: "string", example: "Jean"),
        new OA\Property(property: "nom_complet", type: "string", example: "Jean Dupont"),
        new OA\Property(property: "email", type: "string", format: "email", example: "jean.dupont@example.com"),
        new OA\Property(property: "telephone", type: "string", nullable: true, example: "+33 6 12 34 56 78"),
        new OA\Property(property: "poste", type: "string", nullable: true, example: "Consultant Senior"),
        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string"), example: ["consultant", "manager"]),
        new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["create-missions", "view-clients"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]

#[OA\Schema(
    schema: "Conversation",
    type: "object",
    title: "Conversation",
    description: "Une conversation avec un agent IA",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "titre", type: "string", nullable: true, example: "Analyse RGPD - Questions"),
        new OA\Property(property: "statut", type: "string", enum: ["active", "archivee"], example: "active"),
        new OA\Property(property: "user_id", type: "integer", example: 2),
        new OA\Property(property: "agent_id", type: "integer", example: 1),
        new OA\Property(property: "mission_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "agent", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string", example: "Agent RGPD"),
            new OA\Property(property: "slug", type: "string", example: "agent-rgpd"),
            new OA\Property(property: "icone", type: "string", nullable: true, example: "🛡️"),
            new OA\Property(property: "couleur", type: "string", nullable: true, example: "#3B82F6")
        ]),
        new OA\Property(property: "messages", type: "array", items: new OA\Items(
            type: "object",
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "contenu", type: "string"),
                new OA\Property(property: "role", type: "string", enum: ["user", "assistant"]),
                new OA\Property(property: "sources", type: "array", items: new OA\Items(type: "string"), nullable: true),
                new OA\Property(property: "created_at", type: "string", format: "date-time")
            ]
        ))
    ]
)]

#[OA\Schema(
    schema: "Charte",
    type: "object",
    title: "Charte",
    description: "Une charte de conformité à signer",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "type", type: "string", example: "charte_confidentialite"),
        new OA\Property(property: "titre", type: "string", example: "Charte de confidentialité"),
        new OA\Property(property: "contenu_html", type: "string", example: "<p>Contenu de la charte...</p>"),
        new OA\Property(property: "version", type: "string", example: "v1.0"),
        new OA\Property(property: "publiee_le", type: "string", format: "date-time", example: "2024-01-15T10:00:00Z"),
        new OA\Property(property: "obligatoire", type: "boolean", example: true),
        new OA\Property(property: "hash_contenu", type: "string", example: "abc123def456"),
        new OA\Property(property: "active", type: "boolean", example: true),
        new OA\Property(property: "signee", type: "boolean", example: true),
        new OA\Property(property: "signature_valide", type: "boolean", example: true),
        new OA\Property(property: "signee_le", type: "string", format: "date-time", nullable: true, example: "2024-01-16T14:30:00Z"),
        new OA\Property(property: "client_rattache", type: "boolean", example: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "signature_existante", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "signee_le", type: "string", format: "date-time"),
            new OA\Property(property: "signature_valide", type: "boolean"),
            new OA\Property(property: "ip_signature", type: "string", nullable: true)
        ])
    ]
)]

#[OA\Schema(
    schema: "Ecart",
    type: "object",
    title: "Écart",
    description: "Un écart de conformité identifié lors d'une analyse",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "analyse_id", type: "integer", example: 1),
        new OA\Property(property: "referentiel_id", type: "integer", example: 1),
        new OA\Property(property: "document_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "referentiel_chunk_id", type: "integer", nullable: true),
        new OA\Property(property: "gravite", type: "string", enum: ["critique", "majeur", "mineur", "observation"], example: "majeur"),
        new OA\Property(property: "categorie", type: "string", example: "Protection des données"),
        new OA\Property(property: "description", type: "string", example: "Absence de registre des activités de traitement"),
        new OA\Property(property: "exigence", type: "string", example: "L'article 30 du RGPD exige un registre des activités de traitement"),
        new OA\Property(property: "statut_correction", type: "string", enum: ["ouvert", "en_cours", "traite", "accepte_par_client", "rejete"], example: "ouvert"),
        new OA\Property(property: "assigne_a", type: "integer", nullable: true, example: 2),
        new OA\Property(property: "echeance_correction", type: "string", format: "date", nullable: true, example: "2024-02-15"),
        new OA\Property(property: "notes_consultant", type: "string", nullable: true, example: "Prioriser la mise en conformité"),
        new OA\Property(property: "recommandation", type: "string", nullable: true, example: "Mettre en place un registre RGPD conforme"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "analyse", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "reference", type: "string"),
            new OA\Property(property: "mission", type: "object", properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "reference", type: "string"),
                new OA\Property(property: "titre", type: "string"),
                new OA\Property(property: "client", type: "object", properties: [
                    new OA\Property(property: "id", type: "integer"),
                    new OA\Property(property: "raison_sociale", type: "string")
                ])
            ])
        ]),
        new OA\Property(property: "referentiel", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "code", type: "string"),
            new OA\Property(property: "titre", type: "string"),
            new OA\Property(property: "article_reference", type: "string")
        ]),
        new OA\Property(property: "document", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "titre", type: "string")
        ]),
        new OA\Property(property: "assigne", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "Agent",
    type: "object",
    title: "Agent IA",
    description: "Un agent d'intelligence artificielle spécialisé",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "slug", type: "string", example: "agent-rgpd"),
        new OA\Property(property: "nom", type: "string", example: "Agent RGPD"),
        new OA\Property(property: "description", type: "string", example: "Spécialiste en conformité RGPD et protection des données"),
        new OA\Property(property: "icone", type: "string", nullable: true, example: "🛡️"),
        new OA\Property(property: "couleur", type: "string", nullable: true, example: "#3B82F6"),
        new OA\Property(property: "type", type: "string", enum: ["assistant", "analyst", "expert"], example: "expert"),
        new OA\Property(property: "module_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "is_core", type: "boolean", example: true),
        new OA\Property(property: "ordre_affichage", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "actif", type: "boolean", example: true),
        new OA\Property(property: "conversations_count", type: "integer", example: 5),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "module", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "slug", type: "string"),
            new OA\Property(property: "couleur", type: "string", nullable: true)
        ])
    ]
)]

#[OA\Schema(
    schema: "PlanAction",
    type: "object",
    title: "Plan d'Action",
    description: "Un plan d'action pour corriger les écarts de conformité",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "titre", type: "string", example: "Plan de mise en conformité RGPD"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Plan d'action pour corriger les écarts identifiés lors de l'audit"),
        new OA\Property(property: "statut", type: "string", enum: ["propose", "accepte_client", "en_cours", "cloture", "rejete"], example: "accepte_client"),
        new OA\Property(property: "client_id", type: "integer", example: 1),
        new OA\Property(property: "analyse_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "proposeur_id", type: "integer", example: 2),
        new OA\Property(property: "accepteur_id", type: "integer", nullable: true),
        new OA\Property(property: "date_proposition", type: "string", format: "date-time", example: "2024-01-15T10:00:00Z"),
        new OA\Property(property: "date_acceptation", type: "string", format: "date-time", nullable: true, example: "2024-01-16T14:30:00Z"),
        new OA\Property(property: "date_cloture", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "items_count", type: "integer", example: 15),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "client", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "raison_sociale", type: "string")
        ]),
        new OA\Property(property: "analyse", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "reference", type: "string"),
            new OA\Property(property: "titre", type: "string"),
            new OA\Property(property: "score_conformite", type: "number", nullable: true)
        ]),
        new OA\Property(property: "proposeur", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ]),
        new OA\Property(property: "accepteur", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ]),
        new OA\Property(property: "progression", type: "number", example: 65.5),
        new OA\Property(property: "peut_modifier", type: "boolean", example: true),
        new OA\Property(property: "peut_accepter", type: "boolean", example: false),
        new OA\Property(property: "peut_cloturer", type: "boolean", example: false),
        new OA\Property(property: "peut_mettre_a_jour_items", type: "boolean", example: true),
        new OA\Property(property: "peut_supprimer", type: "boolean", example: false)
    ]
)]

#[OA\Schema(
    schema: "QuestionnaireGenere",
    type: "object",
    title: "Questionnaire Généré",
    description: "Un questionnaire/formulaire généré pour une mission",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "mission_id", type: "integer", example: 1),
        new OA\Property(property: "pole", type: "string", example: "Formulaire mission"),
        new OA\Property(property: "service", type: "string", nullable: true, example: "Direction générale"),
        new OA\Property(property: "titre", type: "string", example: "Formulaire de collecte — Audit RGPD"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Formulaire concu par AS Consulting pour collecter les informations RGPD"),
        new OA\Property(property: "questions", type: "array", items: new OA\Items(
            type: "object",
            properties: [
                new OA\Property(property: "numero", type: "integer", example: 1),
                new OA\Property(property: "texte", type: "string", example: "Quelles sont les catégories de données personnelles traitées ?"),
                new OA\Property(property: "type", type: "string", enum: ["ouverte", "liste", "oui_non"], example: "ouverte"),
                new OA\Property(property: "themes", type: "array", items: new OA\Items(type: "string"), nullable: true),
                new OA\Property(property: "options", type: "array", items: new OA\Items(type: "string"), nullable: true)
            ]
        )),
        new OA\Property(property: "reponses", type: "array", items: new OA\Items(
            type: "object",
            properties: [
                new OA\Property(property: "numero", type: "integer", example: 1),
                new OA\Property(property: "reponse", type: "string", nullable: true, example: "Données clients, employés, prospects"),
                new OA\Property(property: "repondu", type: "boolean", example: true)
            ]
        )),
        new OA\Property(property: "statut", type: "string", enum: ["brouillon", "envoye", "rempli", "valide"], example: "rempli"),
        new OA\Property(property: "source", type: "string", enum: ["manuel", "ia"], example: "ia"),
        new OA\Property(property: "genere_par", type: "integer", nullable: true, example: 2),
        new OA\Property(property: "repondu_par", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "envoye_a", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "rempli_le", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "mission", type: "object", properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "reference", type: "string"),
            new OA\Property(property: "titre", type: "string"),
            new OA\Property(property: "client", type: "object", properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "raison_sociale", type: "string")
            ])
        ]),
        new OA\Property(property: "genereur", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ]),
        new OA\Property(property: "repondeur", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "nom", type: "string"),
            new OA\Property(property: "prenom", type: "string")
        ])
    ]
)]

#[OA\Schema(
    schema: "Module",
    type: "object",
    title: "Module",
    description: "Un module fonctionnel du système",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nom", type: "string", example: "Module RGPD"),
        new OA\Property(property: "slug", type: "string", example: "module-rgpd"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Module spécialisé en conformité RGPD"),
        new OA\Property(property: "icone", type: "string", nullable: true, example: "🛡️"),
        new OA\Property(property: "couleur", type: "string", nullable: true, example: "#3B82F6"),
        new OA\Property(property: "is_active", type: "boolean", example: true),
        new OA\Property(property: "is_core", type: "boolean", example: false),
        new OA\Property(property: "active_depuis", type: "string", format: "date-time", nullable: true, example: "2024-01-15T10:00:00Z"),
        new OA\Property(property: "ordre_affichage", type: "integer", example: 1),
        new OA\Property(property: "configuration", type: "object", nullable: true, example: "param1: value1"),
        new OA\Property(property: "agents_count", type: "integer", example: 3),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "agents", type: "array", items: new OA\Items(
            type: "object",
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "nom", type: "string"),
                new OA\Property(property: "slug", type: "string"),
                new OA\Property(property: "type", type: "string"),
                new OA\Property(property: "is_active", type: "boolean"),
                new OA\Property(property: "module_id", type: "integer")
            ]
        ))
    ]
)]

#[OA\PathItem(
    path: "/api/login",
    post: new OA\Post(
        operationId: "auth-login",
        summary: "Connexion utilisateur",
        description: "Authentifie un utilisateur et retourne un token JWT",
        tags: ["Authentification"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/LoginRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Connexion réussie",
                content: new OA\JsonContent(ref: "#/components/schemas/AuthResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Identifiants invalides",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Identifiants invalides")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Les champs fournis sont invalides."),
                        new OA\Property(property: "errors", type: "object")
                    ]
                )
            )
        ]
    )
)]

#[OA\PathItem(
    path: "/api/health",
    get: new OA\Get(
        operationId: "health-check",
        summary: "Health check",
        description: "Vérifier que l'API fonctionne",
        tags: ["Health"],
        responses: [
            new OA\Response(
                response: 200,
                description: "API fonctionnelle",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "ok"),
                        new OA\Property(property: "timestamp", type: "string", format: "date-time"),
                        new OA\Property(property: "version", type: "string", example: "1.0.0")
                    ]
                )
            )
        ]
    )
)]

class SwaggerController
{
    // Ce contrôleur ne contient que des attributs Swagger
}
