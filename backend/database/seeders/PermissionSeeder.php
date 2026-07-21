<?php

/**
 * Seeder pour les permissions de la plateforme ASC-IA.
 *
 * Crée les permissions basées sur les fonctionnalités analysées
 * des routes API et des contrôleurs existants.
 */

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mode idempotent : on cree uniquement les permissions absentes en base,
        // ce qui permet de re-seeder apres ajout d'une nouvelle permission sans
        // detruire les enregistrements existants.
        $permissions = [
            // === AUTHENTIFICATION ===
            ['name' => 'login', 'description' => 'Se connecter à la plateforme', 'group' => 'auth', 'is_active' => true],
            ['name' => 'logout', 'description' => 'Se déconnecter de la plateforme', 'group' => 'auth', 'is_active' => true],
            ['name' => 'view-profile', 'description' => 'Voir son profil utilisateur', 'group' => 'auth', 'is_active' => true],
            ['name' => 'update-profile', 'description' => 'Mettre à jour son profil', 'group' => 'auth', 'is_active' => true],
            ['name' => 'update-password', 'description' => 'Changer son mot de passe', 'group' => 'auth', 'is_active' => true],

            // === DASHBOARD ===
            ['name' => 'view-dashboard', 'description' => 'Voir le dashboard', 'group' => 'dashboard', 'is_active' => true],

            // === LLM & AGENTS IA ===
            ['name' => 'view-agents', 'description' => 'Voir les agents IA', 'group' => 'llm', 'is_active' => true],
            ['name' => 'view-all-agents', 'description' => 'Gérer les agents IA', 'group' => 'llm', 'is_active' => true],
            ['name' => 'view-llm-status', 'description' => 'Voir le statut du LLM', 'group' => 'llm', 'is_active' => true],
            ['name' => 'view-llm-models', 'description' => 'Voir les modèles LLM disponibles', 'group' => 'llm', 'is_active' => true],

            // === CONVERSATIONS (CHATBOT) ===
            ['name' => 'view-conversations', 'description' => 'Voir les conversations', 'group' => 'conversations', 'is_active' => true],
            ['name' => 'create-conversations', 'description' => 'Créer des conversations', 'group' => 'conversations', 'is_active' => true],
            ['name' => 'view-all-conversations', 'description' => 'Gérer les conversations', 'group' => 'conversations', 'is_active' => true],

            // === DOCUMENTS ===
            ['name' => 'view-documents', 'description' => 'Voir les documents', 'group' => 'documents', 'is_active' => true],
            ['name' => 'upload-documents', 'description' => 'Uploader des documents', 'group' => 'documents', 'is_active' => true],
            ['name' => 'generate-documents', 'description' => 'Générer des documents', 'group' => 'documents', 'is_active' => true],
            ['name' => 'view-all-documents', 'description' => 'Gérer les documents', 'group' => 'documents', 'is_active' => true],

            // === DONNÉES DE RÉFÉRENCE ===
            ['name' => 'view-ref-data', 'description' => 'Voir les données de référence', 'group' => 'reference', 'is_active' => true],
            ['name' => 'view-all-ref-data', 'description' => 'Gérer les données de référence', 'group' => 'reference', 'is_active' => true],

            // === SECTEURS D'ACTIVITÉ ===
            ['name' => 'view-secteurs', 'description' => 'Voir les secteurs d\'activité', 'group' => 'secteurs', 'is_active' => true],
            ['name' => 'create-secteurs', 'description' => 'Créer des secteurs d\'activité', 'group' => 'secteurs', 'is_active' => true],
            ['name' => 'update-secteurs', 'description' => 'Mettre à jour les secteurs d\'activité', 'group' => 'secteurs', 'is_active' => true],
            ['name' => 'delete-secteurs', 'description' => 'Supprimer des secteurs d\'activité', 'group' => 'secteurs', 'is_active' => true],
            ['name' => 'view-all-secteurs', 'description' => 'Gérer les secteurs d\'activité', 'group' => 'secteurs', 'is_active' => true],

            // === TÂCHES ===
            ['name' => 'view-taches', 'description' => 'Voir les tâches', 'group' => 'taches', 'is_active' => true],
            ['name' => 'create-taches', 'description' => 'Créer des tâches', 'group' => 'taches', 'is_active' => true],
            ['name' => 'update-taches', 'description' => 'Mettre à jour les tâches', 'group' => 'taches', 'is_active' => true],
            ['name' => 'delete-taches', 'description' => 'Supprimer des tâches', 'group' => 'taches', 'is_active' => true],
            ['name' => 'view-all-taches', 'description' => 'Gérer les tâches', 'group' => 'taches', 'is_active' => true],

            // === MISSIONS ===
            ['name' => 'view-missions', 'description' => 'Voir les missions', 'group' => 'missions', 'is_active' => true],
            ['name' => 'create-missions', 'description' => 'Créer des missions', 'group' => 'missions', 'is_active' => true],
            ['name' => 'update-missions', 'description' => 'Mettre à jour les missions', 'group' => 'missions', 'is_active' => true],
            ['name' => 'delete-missions', 'description' => 'Supprimer des missions', 'group' => 'missions', 'is_active' => true],
            ['name' => 'view-all-missions', 'description' => 'Gérer les missions', 'group' => 'missions', 'is_active' => true],

            // === MÉTHODE 2 : MATRICE DE COLLECTE ===
            ['name' => 'view-matrice', 'description' => 'Voir les matrices de collecte', 'group' => 'matrice', 'is_active' => true],
            ['name' => 'create-matrice', 'description' => 'Créer des matrices de collecte', 'group' => 'matrice', 'is_active' => true],
            ['name' => 'update-matrice', 'description' => 'Mettre à jour les matrices de collecte', 'group' => 'matrice', 'is_active' => true],
            ['name' => 'submit-matrice', 'description' => 'Soumettre une matrice', 'group' => 'matrice', 'is_active' => true],
            ['name' => 'validate-matrice', 'description' => 'Valider une matrice', 'group' => 'matrice', 'is_active' => true],
            ['name' => 'view-all-matrice', 'description' => 'Gérer les matrices de collecte', 'group' => 'matrice', 'is_active' => true],

            // === ORGANIGRAMME ===
            ['name' => 'view-organigramme', 'description' => 'Voir les organigrammes', 'group' => 'organigramme', 'is_active' => true],
            ['name' => 'update-organigramme', 'description' => 'Mettre à jour les organigrammes', 'group' => 'organigramme', 'is_active' => true],
            ['name' => 'upload-organigramme', 'description' => 'Uploader des organigrammes', 'group' => 'organigramme', 'is_active' => true],
            ['name' => 'freeze-organigramme', 'description' => 'Figer un organigramme', 'group' => 'organigramme', 'is_active' => true],
            ['name' => 'view-all-organigramme', 'description' => 'Gérer les organigrammes', 'group' => 'organigramme', 'is_active' => true],

            // === QUESTIONNAIRES ===
            ['name' => 'view-questionnaires', 'description' => 'Voir les questionnaires', 'group' => 'questionnaires', 'is_active' => true],
            ['name' => 'create-questionnaires', 'description' => 'Créer des questionnaires', 'group' => 'questionnaires', 'is_active' => true],
            ['name' => 'update-questionnaires', 'description' => 'Mettre à jour les questionnaires', 'group' => 'questionnaires', 'is_active' => true],
            ['name' => 'delete-questionnaires', 'description' => 'Supprimer des questionnaires', 'group' => 'questionnaires', 'is_active' => true],
            ['name' => 'regenerate-questionnaires', 'description' => 'Régénérer des questionnaires', 'group' => 'questionnaires', 'is_active' => true],
            ['name' => 'view-all-questionnaires', 'description' => 'Gérer les questionnaires', 'group' => 'questionnaires', 'is_active' => true],

            // === CLIENTS ===
            ['name' => 'view-clients', 'description' => 'Voir les clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'create-clients', 'description' => 'Créer des clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'update-clients', 'description' => 'Mettre à jour les clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'delete-clients', 'description' => 'Supprimer des clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'view-all-clients', 'description' => 'Gérer les clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'view-client-documents', 'description' => 'Voir les documents des clients', 'group' => 'clients', 'is_active' => true],
            ['name' => 'view-all-client-organisme', 'description' => 'Gérer les organismes des clients', 'group' => 'clients', 'is_active' => true],

            // === TRAITEMENTS (PME-CONFORM) ===
            ['name' => 'view-traitements', 'description' => 'Voir les traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'create-traitements', 'description' => 'Créer des traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'update-traitements', 'description' => 'Mettre à jour les traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'delete-traitements', 'description' => 'Supprimer des traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'validate-traitements', 'description' => 'Valider des traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'archive-traitements', 'description' => 'Archiver des traitements de données', 'group' => 'traitements', 'is_active' => true],
            ['name' => 'view-all-traitements', 'description' => 'Gérer les traitements de données', 'group' => 'traitements', 'is_active' => true],

            // === CHARTES & SIGNATURES ===
            ['name' => 'view-chartes', 'description' => 'Voir les chartes', 'group' => 'chartes', 'is_active' => true],
            ['name' => 'create-chartes', 'description' => 'Créer des chartes', 'group' => 'chartes', 'is_active' => true],
            ['name' => 'sign-chartes', 'description' => 'Signer des chartes', 'group' => 'chartes', 'is_active' => true],
            ['name' => 'view-signatures', 'description' => 'Voir les signatures', 'group' => 'chartes', 'is_active' => true],
            ['name' => 'revoke-signatures', 'description' => 'Révoquer des signatures', 'group' => 'chartes', 'is_active' => true],
            ['name' => 'view-all-chartes', 'description' => 'Gérer les chartes', 'group' => 'chartes', 'is_active' => true],

            // === REGISTRE KYC ===
            ['name' => 'view-registres-kyc', 'description' => 'Voir les registres KYC', 'group' => 'kyc', 'is_active' => true],
            ['name' => 'create-registres-kyc', 'description' => 'Créer des registres KYC', 'group' => 'kyc', 'is_active' => true],
            ['name' => 'download-registres-kyc', 'description' => 'Télécharger des registres KYC', 'group' => 'kyc', 'is_active' => true],
            ['name' => 'delete-registres-kyc', 'description' => 'Supprimer des registres KYC', 'group' => 'kyc', 'is_active' => true],
            ['name' => 'view-all-registres-kyc', 'description' => 'Gérer les registres KYC', 'group' => 'kyc', 'is_active' => true],

            // === PLANS D'ACTIONS ===
            ['name' => 'view-plans-actions', 'description' => 'Voir les plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'create-plans-actions', 'description' => 'Créer des plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'update-plans-actions', 'description' => 'Mettre à jour les plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'delete-plans-actions', 'description' => 'Supprimer des plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'accept-plans-actions', 'description' => 'Accepter des plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'close-plans-actions', 'description' => 'Clôturer des plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'manage-plans-actions-items', 'description' => 'Gérer les items des plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],
            ['name' => 'view-all-plans-actions', 'description' => 'Gérer les plans d\'actions', 'group' => 'plans-actions', 'is_active' => true],

            // === PORTEFEUILLE CONSULTANT ===
            ['name' => 'view-portefeuille', 'description' => 'Voir le portefeuille consultant', 'group' => 'portefeuille', 'is_active' => true],
            ['name' => 'view-all-portefeuille', 'description' => 'Gérer le portefeuille consultant', 'group' => 'portefeuille', 'is_active' => true],

            // === ESPACE CLIENT ===
            ['name' => 'access-client-space', 'description' => 'Accéder à l\'espace client', 'group' => 'client-space', 'is_active' => true],
            ['name' => 'view-client-documents', 'description' => 'Voir les documents de l\'espace client', 'group' => 'client-space', 'is_active' => true],
            ['name' => 'upload-client-documents', 'description' => 'Uploader des documents dans l\'espace client', 'group' => 'client-space', 'is_active' => true],
            ['name' => 'view-all-client-documents', 'description' => 'Gérer les documents de l\'espace client', 'group' => 'client-space', 'is_active' => true],
            ['name' => 'view-client-questionnaires', 'description' => 'Voir les formulaires/questionnaires de l\'espace client', 'group' => 'client-space', 'is_active' => true],
            ['name' => 'access-audit-flash', 'description' => 'Acceder a l\'Audit Flash (3 minutes) de l\'espace client', 'group' => 'client-space', 'is_active' => true],

            // === RÉFÉRENTIELS ===
            ['name' => 'view-referentiels', 'description' => 'Voir les référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'create-referentiels', 'description' => 'Créer des référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'update-referentiels', 'description' => 'Mettre à jour les référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'delete-referentiels', 'description' => 'Supprimer des référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'reindex-referentiels', 'description' => 'Réindexer des référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'upload-referentiel-files', 'description' => 'Uploader des fichiers de référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'input-referentiel-content', 'description' => 'Saisir du contenu de référentiels', 'group' => 'referentiels', 'is_active' => true],
            ['name' => 'view-all-referentiels', 'description' => 'Gérer les référentiels', 'group' => 'referentiels', 'is_active' => true],

            // === ANALYSES ===
            ['name' => 'view-analyses', 'description' => 'Voir les analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'create-analyses', 'description' => 'Créer des analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'update-analyses', 'description' => 'Mettre à jour les analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'delete-analyses', 'description' => 'Supprimer des analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'regenerate-analysis-reports', 'description' => 'Régénérer des rapports d\'analyse', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'cancel-analyses', 'description' => 'Annuler des analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'restart-analyses', 'description' => 'Relancer des analyses', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'enrich-analyses', 'description' => 'Enrichir des analyses avec IA', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'download-analysis-reports', 'description' => 'Télécharger des rapports d\'analyse', 'group' => 'analyses', 'is_active' => true],
            ['name' => 'view-all-analyses', 'description' => 'Gérer les analyses', 'group' => 'analyses', 'is_active' => true],

            // === ÉCARTS ===
            ['name' => 'view-ecarts', 'description' => 'Voir les écarts', 'group' => 'ecarts', 'is_active' => true],
            ['name' => 'update-ecarts', 'description' => 'Mettre à jour les écarts', 'group' => 'ecarts', 'is_active' => true],
            ['name' => 'delete-ecarts', 'description' => 'Supprimer des écarts', 'group' => 'ecarts', 'is_active' => true],
            ['name' => 'view-all-ecarts', 'description' => 'Gérer les écarts', 'group' => 'ecarts', 'is_active' => true],

            // === ADMINISTRATION ===
            ['name' => 'manage-users', 'description' => 'Gérer les utilisateurs', 'group' => 'admin', 'is_active' => true],
            ['name' => 'manage-roles', 'description' => 'Gérer les rôles', 'group' => 'admin', 'is_active' => true],
            ['name' => 'manage-permissions', 'description' => 'Gérer les permissions', 'group' => 'admin', 'is_active' => true],
            ['name' => 'view-audit-logs', 'description' => 'Voir les logs d\'audit', 'group' => 'admin', 'is_active' => true],
            ['name' => 'manage-modules', 'description' => 'Gérer les modules', 'group' => 'admin', 'is_active' => true],
            ['name' => 'view-all-agents-admin', 'description' => 'Gérer les agents IA (admin)', 'group' => 'admin', 'is_active' => true],

            // === VALIDATION DES COMPTES ===
            ['name' => 'validate-accounts', 'description' => 'Valider les comptes utilisateurs inscrits via /inscription', 'group' => 'admin', 'is_active' => true],

            // === GESTION DES UTILISATEURS PAR CLIENT_ADMIN ===
            ['name' => 'manage-client-users', 'description' => 'Gerer les utilisateurs de son entreprise (creation, modification, suppression)', 'group' => 'client-space', 'is_active' => true],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );
        }

        $this->command->info(count($permissions) . ' permissions ont été créées avec succès.');
    }
}
