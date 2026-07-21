<?php

/**
 * Service AuditService — Façade pour la journalisation d'audit.
 *
 * Fournit des méthodes spécialisées pour chaque type d'action
 * auditée sur la plateforme (connexion, upload, requête agent, etc.).
 */

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function connexion(int $userId, string $resultat = 'succes'): void
    {
        AuditLog::enregistrer(
            action: 'auth.connexion',
            description: 'Connexion utilisateur',
            categorie: 'auth',
            resultat: $resultat,
            metadata: ['user_id' => $userId],
        );
    }

    public function deconnexion(): void
    {
        AuditLog::enregistrer(
            action: 'auth.deconnexion',
            description: 'Déconnexion utilisateur',
            categorie: 'auth',
        );
    }

    public function uploadDocument(Model $document): void
    {
        AuditLog::enregistrer(
            action: 'document.upload',
            description: "Upload du document : {$document->titre}",
            auditable: $document,
            categorie: 'document',
        );
    }

    public function telechargementDocument(Model $document): void
    {
        AuditLog::enregistrer(
            action: 'document.telechargement',
            description: "Téléchargement du document : {$document->titre}",
            auditable: $document,
            categorie: 'document',
        );
    }

    public function requeteAgent(Model $conversation, string $agentNom): void
    {
        AuditLog::enregistrer(
            action: 'agent.requete',
            description: "Requête à l'agent : {$agentNom}",
            auditable: $conversation,
            categorie: 'agent',
        );
    }

    public function modificationModule(Model $module, string $action): void
    {
        AuditLog::enregistrer(
            action: "module.{$action}",
            description: "Module {$module->nom} : {$action}",
            auditable: $module,
            categorie: 'admin',
        );
    }

    public function actionAdmin(string $action, ?string $description = null, ?array $metadata = null): void
    {
        AuditLog::enregistrer(
            action: "admin.{$action}",
            description: $description,
            categorie: 'admin',
            metadata: $metadata,
        );
    }

    /**
     * Log generique — utilise par les nouveaux modules (traitements, signatures, plans d'actions...).
     * L'action doit etre au format "domaine.evenement" (ex: "traitement.valide").
     */
    public function log(string $action, array $metadata = [], ?string $description = null): void
    {
        $categorie = str_contains($action, '.') ? explode('.', $action)[0] : 'metier';

        AuditLog::enregistrer(
            action: $action,
            description: $description,
            categorie: $categorie,
            metadata: $metadata,
        );
    }
}
