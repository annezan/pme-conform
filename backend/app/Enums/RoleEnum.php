<?php

/**
 * Enum RoleEnum — Rôles disponibles sur la plateforme.
 *
 * Utilisé par Spatie Permission pour le RBAC.
 * - admin : accès total, gestion des modules et utilisateurs
 * - manager : supervision des missions et consultants
 * - consultant : travail sur les missions, utilisation des agents
 * - client : consultation de ses propres dossiers uniquement
 */

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case CONSULTANT = 'consultant';
    case CLIENT_ADMIN = 'client_admin'; // Responsable de l'entreprise cliente
    case CLIENT = 'client';             // Utilisateur standard de l'entreprise cliente

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::MANAGER => 'Manager',
            self::CONSULTANT => 'Consultant',
            self::CLIENT_ADMIN => 'Responsable entreprise',
            self::CLIENT => 'Utilisateur entreprise',
        };
    }

    /**
     * Retourne les permissions associées à chaque rôle.
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMIN => [
                // Gestion utilisateurs
                'users.view', 'users.create', 'users.edit', 'users.delete',
                // Gestion clients
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                // Gestion missions
                'missions.view', 'missions.create', 'missions.edit', 'missions.delete',
                // Documents
                'documents.view', 'documents.upload', 'documents.download', 'documents.delete',
                // Agents IA & recherche
                'agents.view', 'agents.use', 'agents.configure',
                'ia.research', 'ia.execute_tasks',
                // Analyses d'ecarts
                'analyses.view_all', 'analyses.executer', 'analyses.cloturer', 'analyses.assigner',
                // Modules
                'modules.view', 'modules.manage',
                // Administration
                'admin.dashboard', 'admin.settings', 'admin.audit_logs',
                // Conversations
                'conversations.view', 'conversations.create', 'conversations.view_all',
                // PME-CONFORM : gestion globale
                'chartes.manage', 'traitements.view_all', 'registres.view_all', 'registres.generer',
                'plans_actions.view_all', 'plans_actions.create', 'plans_actions.cloturer',
                // Taches consultants
                'taches.view_all', 'taches.assigner', 'taches.cloturer',
            ],
            self::MANAGER => [
                'users.view',
                'clients.view', 'clients.create', 'clients.edit',
                'missions.view', 'missions.create', 'missions.edit',
                'documents.view', 'documents.upload', 'documents.download',
                'agents.view', 'agents.use',
                'ia.research', 'ia.execute_tasks',
                'analyses.view_all', 'analyses.executer', 'analyses.cloturer', 'analyses.assigner',
                'modules.view',
                'admin.dashboard',
                'conversations.view', 'conversations.create', 'conversations.view_all',
                'traitements.view_all', 'registres.view_all', 'registres.generer',
                'plans_actions.view_all', 'plans_actions.create', 'plans_actions.cloturer',
                'taches.view_all', 'taches.assigner', 'taches.cloturer',
            ],
            self::CONSULTANT => [
                'clients.view',
                'missions.view', 'missions.edit',
                'documents.view', 'documents.upload', 'documents.download',
                'agents.view', 'agents.use',
                // Consultant peut faire des recherches IA et executer les taches IA qui lui sont confiees
                'ia.research', 'ia.execute_tasks',
                'conversations.view', 'conversations.create',
                // Consultant ASC : analyse + plans d'actions sur ses clients
                'analyses.view_all', 'analyses.executer',
                'traitements.view_all', 'registres.view_all', 'registres.generer',
                'plans_actions.view_all', 'plans_actions.create', 'plans_actions.cloturer',
                // Voit les taches qui lui sont assignees, peut les marquer terminees
                'taches.view_mine', 'taches.cloturer',
            ],
            self::CLIENT_ADMIN => [
                // Memes permissions que CLIENT + gestion interne
                'missions.view',
                'documents.view', 'documents.upload', 'documents.download',
                'conversations.view', 'conversations.create',
                'traitements.view', 'traitements.create', 'traitements.edit', 'traitements.valider', 'traitements.archiver',
                'charte.signer',
                'registres.generer', 'registres.view',
                'plans_actions.view', 'plans_actions.mettre_a_jour', 'plans_actions.accepter',
                // Gestion des users de SA propre entreprise
                'entreprise.users.manage',
                'entreprise.profile.edit',
            ],
            self::CLIENT => [
                'missions.view',
                'documents.view', 'documents.upload', 'documents.download',
                'conversations.view', 'conversations.create',
                // Saisie/consultation de traitements + signature + registre
                'traitements.view', 'traitements.create', 'traitements.edit',
                'charte.signer',
                'registres.generer', 'registres.view',
                'plans_actions.view', 'plans_actions.mettre_a_jour',
            ],
        };
    }
}
