<?php

/**
 * Seeder des rôles et permissions.
 *
 * Crée les rôles de base et leurs permissions associées
 * via les modèles personnalisés Role et Permission.
 * Crée un utilisateur admin par défaut.
 */

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        
        // Créer les permissions si elles n'existent pas
        // Lancer le seeder de permissions (gère les doublons avec firstOrCreate)
        $this->command->call('db:seed', ['class' => 'PermissionSeeder']);

        // Admin : TOUTES les permissions actives de la plateforme.
        // Calculé dynamiquement pour que chaque nouvelle permission créée dans
        // le PermissionSeeder soit automatiquement attachée à l'admin, sans
        // avoir à mettre à jour cette liste manuellement. L'admin conserve
        // aussi son bypass Gate::before (ceinture + bretelles) : meme si on
        // decoche une permission dans l'UI, le bypass le rattrape.
        $toutesPermissionsActives = Permission::where('is_active', true)
            ->pluck('name')
            ->all();

        // Définir les rôles de base avec leurs permissions
        $rolesData = [
            [
                'name' => 'admin',
                'description' => 'Administrateur système - accès complet à la plateforme',
                'is_active' => true,
                'permissions' => $toutesPermissionsActives,
            ],
            [
                'name' => 'manager',
                'description' => 'Manager - supervision des missions et consultants',
                'is_active' => true,
                'permissions' => [
                    'view-dashboard', 'view-agents', 'view-llm-status', 'view-llm-models',
                    'view-conversations', 'create-conversations', 'view-all-conversations',
                    'view-documents', 'upload-documents', 'generate-documents',
                    'validate-accounts',
                    'view-ref-data', 'view-secteurs', 'create-secteurs', 'update-secteurs', 'view-taches', 'create-taches',
                    'update-taches', 'delete-taches', 'view-all-taches',
                    // Manager : droits etendus sur missions (voir toutes, modifier
                    // affectations sur n'importe laquelle, supprimer). view-all-missions
                    // active le bypass de scope client dans MissionPolicy.
                    'view-missions', 'create-missions', 'update-missions',
                    'delete-missions', 'view-all-missions',
                    'view-matrice', 'create-matrice', 'update-matrice',
                    'submit-matrice', 'validate-matrice', 'view-all-matrice',
                    'view-organigramme', 'update-organigramme', 'upload-organigramme',
                    'freeze-organigramme', 'view-all-organigramme',
                    'view-questionnaires', 'create-questionnaires', 'update-questionnaires',
                    'delete-questionnaires', 'regenerate-questionnaires', 'view-all-questionnaires',
                    'view-clients', 'create-clients', 'update-clients', 'view-all-clients',
                    'view-client-documents', 'view-all-client-organisme',
                    'view-traitements', 'create-traitements', 'update-traitements',
                    'delete-traitements', 'validate-traitements', 'archive-traitements',
                    'view-all-traitements', 'view-chartes', 'create-chartes', 'sign-chartes',
                    'view-signatures', 'revoke-signatures', 'view-all-chartes',
                    'view-registres-kyc', 'create-registres-kyc', 'download-registres-kyc',
                    'delete-registres-kyc', 'view-all-registres-kyc',
                    'view-plans-actions', 'create-plans-actions', 'update-plans-actions',
                    'delete-plans-actions', 'accept-plans-actions', 'close-plans-actions',
                    'manage-plans-actions-items', 'view-all-plans-actions',
                    'view-portefeuille', 'view-all-portefeuille',
                    'view-referentiels', 'create-referentiels', 'update-referentiels',
                    'delete-referentiels', 'reindex-referentiels', 'upload-referentiel-files',
                    'input-referentiel-content', 'view-all-referentiels',
                    'view-analyses', 'create-analyses', 'update-analyses', 'delete-analyses',
                    'regenerate-analysis-reports', 'cancel-analyses', 'restart-analyses',
                    'enrich-analyses', 'download-analysis-reports', 'view-all-analyses',
                    'view-ecarts', 'update-ecarts', 'delete-ecarts', 'view-all-ecarts',
                ],
            ],
            [
                'name' => 'consultant',
                'description' => 'Consultant - travail sur les missions et utilisation des agents IA',
                'is_active' => true,
                'permissions' => [
                    'view-dashboard', 'view-agents', 'view-llm-status', 'view-llm-models',
                    'view-conversations', 'create-conversations', 'view-all-conversations',
                    'view-documents', 'upload-documents', 'generate-documents',
                    'view-ref-data', 'view-secteurs', 'view-taches',
                    // Consultant : peut creer une mission (devient createur auto,
                    // auto-affecte via pivot mission_user), la modifier, et supprimer
                    // celles qu'il a creees. Pas de view-all-missions : scope automatique
                    // aux missions dont il est createur/responsable/affecte.
                    'view-missions', 'create-missions', 'update-missions', 'delete-missions',
                    'view-matrice', 'update-matrice',
                    'submit-matrice', 'validate-matrice', 'view-organigramme',
                    'update-organigramme', 'upload-organigramme', 'freeze-organigramme',
                    'view-questionnaires', 'update-questionnaires', 'regenerate-questionnaires',
                    'view-clients', 'update-clients', 'view-client-documents',
                    'view-all-client-organisme', 'view-traitements', 'create-traitements',
                    'update-traitements', 'validate-traitements', 'archive-traitements',
                    'view-chartes', 'sign-chartes', 'view-signatures', 'revoke-signatures',
                    'view-registres-kyc', 'create-registres-kyc', 'download-registres-kyc',
                    'view-plans-actions', 'create-plans-actions', 'update-plans-actions',
                    'accept-plans-actions', 'close-plans-actions', 'manage-plans-actions-items',
                    'view-portefeuille', 'view-referentiels', 'create-referentiels',
                    'update-referentiels', 'delete-referentiels', 'reindex-referentiels',
                    'upload-referentiel-files', 'input-referentiel-content',
                    'view-analyses', 'create-analyses', 'update-analyses', 'delete-analyses',
                    'regenerate-analysis-reports', 'cancel-analyses', 'restart-analyses',
                    'enrich-analyses', 'download-analysis-reports', 'view-ecarts',
                    'update-ecarts', 'view-all-ecarts', 'ia-research', 'ia-execute-tasks',
                    'view-matrices', 'upload-matrice-pieces',
                    'view-all-client-documents', 'access-client-space', 'view-client-questionnaires',
                    'view-all-client-documents', 'view-all-questionnaires',
                ],
            ],
            [
                'name' => 'client_admin',
                'description' => 'Responsable entreprise cliente - gestion de son espace client',
                'is_active' => true,
                'permissions' => [
                    'login', 'logout', 'view-profile', 'update-profile', 'update-password',
                    'view-dashboard', 'access-client-space',
                    // Documents client
                    'view-client-documents', 'view-all-client-documents',
                    // Formulaires/questionnaires
                    'view-client-questionnaires',
                    // Audit Flash 3 min (reserve au client_admin selon Phase 5)
                    'access-audit-flash',
                    // Gestion des utilisateurs de l'entreprise
                    'manage-client-users',
                    // Matrice de collecte (remplir + soumettre)
                    'view-matrice', 'update-matrice', 'submit-matrice',
                    // Organigramme (consultation)
                    'view-organigramme',
                    // Traitements DCP : le client_admin declare ses propres traitements
                    'view-traitements', 'create-traitements', 'update-traitements',
                    'validate-traitements', 'delete-traitements',
                    // Ecarts d'analyse (consultation des ses propres ecarts)
                    'view-ecarts',
                    // Chartes
                    'view-chartes', 'sign-chartes', 'view-signatures',
                    // Registre KYC
                    'view-registres-kyc', 'download-registres-kyc',
                    // Plans d'actions (accept + close + suivi des items via le kanban)
                    'view-plans-actions', 'accept-plans-actions', 'close-plans-actions',
                    'manage-plans-actions-items',
                ],
            ],
            [
                'name' => 'client',
                'description' => 'Utilisateur standard entreprise cliente - acces limite aux questionnaires de son pole',
                'is_active' => true,
                // Phase 5 : restriction stricte. Un user standard n'a acces qu'au
                // menu Questionnaires (de son pole). Toutes les autres
                // fonctionnalites (matrice, organigramme, traitements, ecarts,
                // chartes, plans d'actions, registres) sont reservees au
                // client_admin de son entreprise.
                'permissions' => [
                    'login', 'logout', 'view-profile', 'update-profile', 'update-password',
                    'view-dashboard', 'access-client-space',
                    'view-client-questionnaires',
                ],
            ],
        ];

        // Créer les rôles et assigner les permissions.
        //
        // Comportement ADDITIF-PUR base sur l'historique `seeded_permissions` :
        //
        // - Une permission n'est attachee a un role QUE si elle n'a jamais ete
        //   listee dans ce seeder auparavant (donc on ne re-attache jamais
        //   une permission qu'un admin a retiree via l'UI).
        // - Les permissions retirees du code seeder restent attachees en base
        //   (rien n'est jamais detache).
        // - Pour les roles existants au moment de la migration, l'historique
        //   est NULL. Au premier passage, on l'INITIALISE avec les permissions
        //   actuellement seedees ET les permissions actuellement attachees, ce
        //   qui empeche un "rattrapage" non-souhaite.
        // - Pour les roles crees par ce seeder (wasRecentlyCreated), on
        //   applique l'ensemble des permissions normalement.
        foreach ($rolesData as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            $historique = $role->seeded_permissions ?? null;

            if ($role->wasRecentlyCreated) {
                // Role tout neuf : on applique l'integralite du seeder.
                $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
                $role->permissions()->sync($permissionIds);
                $role->update(['seeded_permissions' => array_values(array_unique($permissions))]);
                continue;
            }

            if ($historique === null) {
                // Premier passage apres la migration sur un role pre-existant :
                // on initialise l'historique sans rien modifier en base.
                // On considere que les permissions deja attachees ET celles
                // listees dans le seeder courant ont toutes ete "deja vues".
                $dejaAttachees = $role->permissions()->pluck('permissions.name')->all();
                $role->update([
                    'seeded_permissions' => array_values(array_unique(array_merge($dejaAttachees, $permissions))),
                ]);
                continue;
            }

            // Cas normal : on calcule les permissions nouvellement introduites
            // dans le code seeder depuis le dernier passage.
            $nouvelles = array_values(array_diff($permissions, $historique));

            if (! empty($nouvelles)) {
                $permissionIds = Permission::whereIn('name', $nouvelles)->pluck('id')->toArray();
                if (! empty($permissionIds)) {
                    $role->permissions()->syncWithoutDetaching($permissionIds);
                }
            }

            // Mise a jour de l'historique : on memorise toutes les permissions
            // listees dans le seeder courant (que le re-attachement ait eu lieu
            // ou non).
            $role->update([
                'seeded_permissions' => array_values(array_unique(array_merge($historique, $permissions))),
            ]);
        }

        // Créer l'utilisateur admin par défaut.
        // compte_valide = true : sans cela, le premier admin ne peut pas se
        // connecter sur une install fresh (le check d'AuthController::login
        // rejette tout compte ou compte_valide est false avec un message
        // "compte en attente de validation").
        $admin = User::firstOrCreate(
            ['email' => 'admin@asc-ia.local'],
            [
                'nom' => 'Administrateur',
                'prenom' => 'ASC-IA',
                'password' => bcrypt('Admin@2026!'),
                'is_active' => true,
                'compte_valide' => true,
                'valide_le' => now(),
                'email_verified_at' => now(),
            ]
        );

        // Si le user existait deja (firstOrCreate ne touche pas un compte
        // existant), on s'assure quand meme qu'il est bien valide.
        if (! $admin->compte_valide) {
            $admin->update([
                'compte_valide' => true,
                'valide_le' => now(),
            ]);
        }

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->role_id = $adminRole->id;
            $admin->save();
        }

        $this->command->info('Rôles et permissions créés avec succès.');
    }
}
