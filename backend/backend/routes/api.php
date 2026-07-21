<?php

/**
 * Routes API de la plateforme ASC-IA.
 */

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AnalyseController;
use App\Http\Controllers\Api\AuditFlashLibreController;
use App\Http\Controllers\Api\AuditFlashRendezVousController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientDocumentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentGenerationController;
use App\Http\Controllers\Api\DocumentUploadController;
use App\Http\Controllers\Api\EcartController;
use App\Http\Controllers\Api\EcartPreuveController;
use App\Http\Controllers\Api\LlmController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\ReferentielController;
use App\Http\Controllers\Api\RefDataController;
use App\Http\Controllers\Api\TacheController;
use App\Http\Controllers\Api\ClientOrganismeController;
use App\Http\Controllers\Api\MatriceCollecteController;
use App\Http\Controllers\Api\OrganigrammeController;
use App\Http\Controllers\Api\QuestionnaireGenereController;
use App\Http\Controllers\Api\AscPortefeuilleController;
use App\Http\Controllers\Api\CharteController;
use App\Http\Controllers\Api\CharteSignatureController;
use App\Http\Controllers\Api\PlanActionController;
use App\Http\Controllers\Api\PlanActionItemPreuveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RegistreKycController;
use App\Http\Controllers\Api\SecteurActiviteController;
use App\Http\Controllers\Api\TraitementController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Admin\AgentAdminController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\ModuleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Routes publiques
// ============================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Workflow "Mot de passe oublie" — endpoints publics (pas d'auth requise).
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword']);
Route::get('/public/secteurs-activite', [App\Http\Controllers\Api\SecteurActiviteController::class, 'liste']);
Route::get('/public/pays', [App\Http\Controllers\Api\RefDataController::class, 'pays']);

// Prise de rendez-vous suite a un audit flash (accessible aux visiteurs anonymes)
Route::post('/public/audit-flash/rendez-vous', [AuditFlashRendezVousController::class, 'store']);

// Health checks (pour monitoring externe)
Route::get('/health', [HealthController::class, 'ping']);
Route::get('/health/detaille', [HealthController::class, 'detaille']);

// ============================================================
// Routes protegees par Sanctum
// ============================================================
Route::middleware(['auth:sanctum', 'active', 'audit', 'force.password.change'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Profil utilisateur
    Route::put('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);

    // Changement du mot de passe temporaire (1ere connexion)
    // Accessible meme quand must_change_password=true grace au whitelist du middleware.
    Route::post('/user/change-temporary-password', [ProfileController::class, 'changeTemporaryPassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // LLM
    Route::get('/llm/statut', [LlmController::class, 'statut']);
    Route::get('/llm/modeles', [LlmController::class, 'modeles']);

    // Agents IA
    Route::get('/agents', [AgentController::class, 'index']);
    Route::get('/agents/{agent:slug}', [AgentController::class, 'show']);

    // Conversations (Chatbot)
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'message']);
    Route::post('/conversations/{conversation}/stream', [ConversationController::class, 'stream']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::post('/documents/upload', [DocumentUploadController::class, 'store']);
    Route::post('/documents/generer', [DocumentGenerationController::class, 'generate']);
    Route::get('/documents/generer/{message}/download', [DocumentGenerationController::class, 'download']);

    // Donnees de reference (pays, secteurs, lois groupees)
    Route::get('/ref/pays', [RefDataController::class, 'pays']);
    Route::get('/ref/secteurs', [RefDataController::class, 'secteurs']);
    Route::get('/ref/referentiels-par-secteur', [RefDataController::class, 'referentielsParSecteur']);

    // Secteurs d'activité (CRUD)
    Route::apiResource('secteurs-activite', SecteurActiviteController::class);
    Route::match(['get', 'patch'], '/secteurs-activite/{secteurActivite}/toggle-actif', [SecteurActiviteController::class, 'toggleActif']);
    Route::get('/secteurs-activite-liste', [SecteurActiviteController::class, 'liste']);

    // Taches assignees aux agents AS Consulting
    Route::apiResource('taches', TacheController::class);

    // Methode 2 : Matrice de collecte initiale
    Route::get('/missions/{mission}/matrice', [MatriceCollecteController::class, 'show']);
    Route::post('/missions/{mission}/matrice/initier', [MatriceCollecteController::class, 'initier']);
    Route::put('/missions/{mission}/matrice', [MatriceCollecteController::class, 'update']);
    Route::post('/missions/{mission}/matrice/remettre', [MatriceCollecteController::class, 'remettre']);
    Route::post('/missions/{mission}/matrice/valider', [MatriceCollecteController::class, 'valider']);
    Route::post('/missions/{mission}/matrice/deriver-organigramme', [MatriceCollecteController::class, 'deriverOrganigramme']);
    Route::post('/missions/{mission}/matrice/pieces', [MatriceCollecteController::class, 'uploaderPiece']);
    Route::post('/missions/{mission}/matrice/poles', [MatriceCollecteController::class, 'ajouterPole']);
    Route::put('/missions/{mission}/matrice/item', [MatriceCollecteController::class, 'repondreItem']);
    Route::delete('/matrice-pieces/{piece}', [MatriceCollecteController::class, 'supprimerPiece']);

    // Methode 2 : Organigramme
    Route::get('/missions/{mission}/organigramme', [OrganigrammeController::class, 'show']);
    Route::put('/missions/{mission}/organigramme', [OrganigrammeController::class, 'update']);
    Route::post('/missions/{mission}/organigramme/upload', [OrganigrammeController::class, 'uploaderFichier']);
    Route::delete('/missions/{mission}/organigramme/fichier', [OrganigrammeController::class, 'supprimerFichier']);
    Route::get('/missions/{mission}/organigramme/fichier', [OrganigrammeController::class, 'telechargerFichier']);
    Route::post('/missions/{mission}/organigramme/figer', [OrganigrammeController::class, 'figer']);
    Route::get('/missions/{mission}/organigramme/generation', [OrganigrammeController::class, 'progressGeneration']);

    // Questionnaires / formulaires des missions (Methode 1 et Methode 2)
    Route::get('/missions/{mission}/questionnaires', [QuestionnaireGenereController::class, 'indexParMission']);
    Route::post('/missions/{mission}/questionnaires', [QuestionnaireGenereController::class, 'store']);
    Route::post('/missions/{mission}/questionnaires/regenerer-tous', [QuestionnaireGenereController::class, 'regenererTous']);
    Route::post('/missions/{mission}/questionnaires/publier-tous', [QuestionnaireGenereController::class, 'publierTous']);
    Route::post('/missions/{mission}/questionnaires/depublier-tous', [QuestionnaireGenereController::class, 'depublierTous']);
    Route::get('/questionnaires-generes/{questionnaire}', [QuestionnaireGenereController::class, 'show']);
    Route::put('/questionnaires-generes/{questionnaire}', [QuestionnaireGenereController::class, 'update']);
    Route::put('/questionnaires-generes/{questionnaire}/reponses', [QuestionnaireGenereController::class, 'enregistrerReponses']);
    Route::delete('/questionnaires-generes/{questionnaire}', [QuestionnaireGenereController::class, 'destroy']);
    Route::post('/questionnaires-generes/{questionnaire}/regenerer', [QuestionnaireGenereController::class, 'regenerer']);
    Route::get('/questionnaires-generes/{questionnaire}/regenerer/progress', [QuestionnaireGenereController::class, 'regenererProgress']);
    Route::get('/questionnaires-generes/{questionnaire}/audit-flash-resultat', [QuestionnaireGenereController::class, 'auditFlashResultat']);

    // Phase 4 : workflow de publication + CRUD questions individuelles (ASC).
    Route::post('/questionnaires-generes/{questionnaire}/publier', [QuestionnaireGenereController::class, 'publier']);
    Route::post('/questionnaires-generes/{questionnaire}/depublier', [QuestionnaireGenereController::class, 'depublier']);
    Route::get('/questionnaires-generes/{questionnaire}/export-pdf', [QuestionnaireGenereController::class, 'exportPdf']);
    Route::post('/questionnaires-generes/{questionnaire}/questions', [QuestionnaireGenereController::class, 'ajouterQuestion']);
    Route::put('/questionnaires-generes/{questionnaire}/questions/{numero}', [QuestionnaireGenereController::class, 'modifierQuestion']);
    Route::delete('/questionnaires-generes/{questionnaire}/questions/{numero}', [QuestionnaireGenereController::class, 'supprimerQuestion']);

    // Clients
    Route::apiResource('clients', ClientController::class);
    Route::get('/clients/{client}/documents', [ClientController::class, 'documents']);
    Route::get('/clients/{client}/organisme', [ClientOrganismeController::class, 'show']);
    Route::put('/clients/{client}/organisme', [ClientOrganismeController::class, 'upsert']);

    // Traitements de donnees (PME-CONFORM)
    Route::get('/traitements', [TraitementController::class, 'index']);
    // Phase 5 : pre-remplissage du formulaire a partir du profil client.
    Route::get('/traitements/preremplir', [TraitementController::class, 'preremplir']);
    Route::post('/traitements/creer-depuis-questionnaires', [TraitementController::class, 'creerDepuisQuestionnaires']);
    Route::post('/traitements', [TraitementController::class, 'store']);
    Route::get('/traitements/{traitement}', [TraitementController::class, 'show']);
    Route::put('/traitements/{traitement}', [TraitementController::class, 'update']);
    Route::delete('/traitements/{traitement}', [TraitementController::class, 'destroy']);
    Route::post('/traitements/{traitement}/valider', [TraitementController::class, 'valider']);
    Route::post('/traitements/{traitement}/archiver', [TraitementController::class, 'archiver']);
    Route::get('/traitements/{traitement}/historique', [TraitementController::class, 'historique']);

    // Chartes & signatures
    Route::get('/chartes', [CharteController::class, 'index']);
    Route::get('/chartes/{charte}', [CharteController::class, 'show']);
    Route::post('/chartes/{charte}/signer', [CharteSignatureController::class, 'signer']);
    Route::get('/mes-signatures', [CharteSignatureController::class, 'mesSignatures']);
    Route::post('/signatures/{signature}/revoquer', [CharteSignatureController::class, 'revoquer']);
    Route::get('/clients/{client}/signatures', [CharteSignatureController::class, 'signaturesClient'])
        ->whereNumber('client');

    // Registre KYC
    Route::get('/registres-kyc', [RegistreKycController::class, 'index']);
    Route::post('/registres-kyc', [RegistreKycController::class, 'store']);
    Route::get('/registres-kyc/{registre}', [RegistreKycController::class, 'show']);
    Route::get('/registres-kyc/{registre}/telecharger', [RegistreKycController::class, 'telecharger']);
    Route::delete('/registres-kyc/{registre}', [RegistreKycController::class, 'destroy']);

    // Plans d'actions
    Route::get('/plans-actions', [PlanActionController::class, 'index']);
    Route::post('/plans-actions', [PlanActionController::class, 'store']);
    Route::get('/plans-actions/{plan}', [PlanActionController::class, 'show']);
    Route::put('/plans-actions/{plan}', [PlanActionController::class, 'update']);
    Route::delete('/plans-actions/{plan}', [PlanActionController::class, 'destroy']);
    Route::post('/plans-actions/{plan}/accepter', [PlanActionController::class, 'accepter']);
    Route::post('/plans-actions/{plan}/cloturer', [PlanActionController::class, 'cloturer']);
    Route::post('/plans-actions/{plan}/soumettre', [PlanActionController::class, 'soumettre']);
    Route::post('/plans-actions/{plan}/rouvrir', [PlanActionController::class, 'rouvrir']);
    Route::post('/plans-actions/{plan}/items', [PlanActionController::class, 'ajouterItem']);
    Route::put('/plans-actions/{plan}/items/{item}', [PlanActionController::class, 'mettreAJourItem']);
    Route::delete('/plans-actions/{plan}/items/{item}', [PlanActionController::class, 'supprimerItem']);

    // Preuves justificatives attachees aux items d'un plan d'action.
    // Le client uploade ses preuves sur chaque item ; un job LLM les compare
    // aux recommandations de l'ecart lie pour produire un verdict.
    Route::get('/plan-action-items/{item}/preuves', [PlanActionItemPreuveController::class, 'index']);
    Route::post('/plan-action-items/{item}/preuves', [PlanActionItemPreuveController::class, 'store']);
    Route::get('/plan-action-item-preuves/{preuve}/telecharger', [PlanActionItemPreuveController::class, 'telecharger']);
    Route::delete('/plan-action-item-preuves/{preuve}', [PlanActionItemPreuveController::class, 'destroy']);

    // Notifications in-app (bell icon)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/marquer-lue', [NotificationController::class, 'marquerLue']);
    Route::post('/notifications/marquer-toutes-lues', [NotificationController::class, 'marquerToutesLues']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Portefeuille consultant ASC (vue 360)
    Route::get('/asc/portefeuille', [AscPortefeuilleController::class, 'index']);
    Route::get('/asc/portefeuille/{client}', [AscPortefeuilleController::class, 'show']);

    // Espace client — documents et formulaires lies aux missions de l'entreprise
    Route::prefix('client')->group(function () {
        Route::post('/initialiser', [ClientDocumentController::class, 'initialiser']);
        Route::get('/documents', [ClientDocumentController::class, 'index']);
        Route::post('/documents', [ClientDocumentController::class, 'store']);
        Route::get('/documents/{document}', [ClientDocumentController::class, 'show']);
        Route::delete('/documents/{document}', [ClientDocumentController::class, 'destroy']);

        // Formulaires/questionnaires : acces lecture pour le client connecte.
        // L'edition et la suppression passent par les routes /questionnaires-generes/* (controle d'acces inclus).
        Route::get('/questionnaires', [QuestionnaireGenereController::class, 'indexClient']);

        // Gestion des utilisateurs de l'entreprise par le client_admin.
        Route::middleware('permission:manage-client-users')->group(function () {
            Route::get('/users', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'index']);
            Route::get('/poles', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'poles']);
            Route::get('/services', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'services']);
            Route::post('/users', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'store']);
            Route::put('/users/{user}', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'update']);
            Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'resetPassword']);
            Route::delete('/users/{user}', [\App\Http\Controllers\Api\Client\ClientUsersController::class, 'destroy']);
        });

        // Audit Flash en self-service (sans mission) — reserve au client_admin
        // (Phase 5 : un user standard n'a pas acces a l'Audit Flash).
        Route::middleware('permission:access-audit-flash')->group(function () {
            Route::get('/audit-flash', [AuditFlashLibreController::class, 'showOuCreer']);
            Route::post('/audit-flash/reset', [AuditFlashLibreController::class, 'reset']);
        });

        // Cartographie initiale : matrices de collecte du client (par mission methode 2).
        // L'edition / l'upload / la derivation organigramme passent par les routes /missions/{mission}/matrice/*
        // qui appliquent le controle d'acces client.
        Route::get('/matrices', [MatriceCollecteController::class, 'indexClient']);
    });

    // Vue admin/manager/consultant : tous les Audit Flash libres du portefeuille
    Route::get('/admin/audit-flash-libres', [AuditFlashLibreController::class, 'indexAdmin']);

    // Prise de rendez-vous suite a un audit flash — version connectee
    Route::post('/audit-flash/rendez-vous', [AuditFlashRendezVousController::class, 'store']);
    Route::get('/admin/audit-flash/rendez-vous', [AuditFlashRendezVousController::class, 'index']);
    Route::get('/admin/audit-flash/rendez-vous/{rendezVous}', [AuditFlashRendezVousController::class, 'show']);
    Route::put('/admin/audit-flash/rendez-vous/{rendezVous}', [AuditFlashRendezVousController::class, 'update']);

    // Missions
    Route::apiResource('missions', MissionController::class);

    // Consultants affectes a une mission (pivot mission_user).
    // Le createur d'une mission est auto-attache ; on ajoute/retire les autres
    // consultants via ces routes. Manager/admin peuvent gerer n'importe quelle
    // mission grace a la permission view-all-missions (cf. MissionPolicy).
    Route::get('/missions/{mission}/consultants', [MissionController::class, 'listerConsultants']);
    Route::get('/missions/{mission}/consultants/candidats', [MissionController::class, 'candidatsConsultants']);
    Route::post('/missions/{mission}/consultants', [MissionController::class, 'attacherConsultants']);
    Route::delete('/missions/{mission}/consultants/{user}', [MissionController::class, 'detacherConsultant']);

    // Referentiels (corpus legaux/reglementaires)
    Route::apiResource('referentiels', ReferentielController::class);
    Route::post('/referentiels/{referentiel}/reindexer', [ReferentielController::class, 'reindexer']);
    Route::post('/referentiels/{referentiel}/fichier', [ReferentielController::class, 'uploaderFichier']);
    
    // Analyses d'ecarts
    Route::get('/analyses', [AnalyseController::class, 'index']);
    Route::post('/analyses', [AnalyseController::class, 'store']);
    Route::get('/analyses/{analyse}', [AnalyseController::class, 'show']);
    Route::delete('/analyses/{analyse}', [AnalyseController::class, 'destroy']);
    Route::post('/analyses/{analyse}/regenerer-rapport', [AnalyseController::class, 'regenererRapport']);
    Route::post('/analyses/{analyse}/annuler', [AnalyseController::class, 'annuler']);
    Route::post('/analyses/{analyse}/relancer', [AnalyseController::class, 'relancer']);
    Route::post('/analyses/{analyse}/refaire', [AnalyseController::class, 'refaire']);
    Route::get('/analyses/{analyse}/versions', [AnalyseController::class, 'versions']);
    Route::get('/analyses/{analyse}/versions/{version}', [AnalyseController::class, 'versionShow']);
    Route::post('/analyses/{analyse}/enrichir', [AnalyseController::class, 'enrichirIA']);
    Route::post('/analyses/{analyse}/annuler-enrichissement', [AnalyseController::class, 'annulerEnrichissement']);
    Route::get('/analyses/{analyse}/rapport', [AnalyseController::class, 'telechargerRapport']);

    // Ecarts
    Route::get('/ecarts', [EcartController::class, 'index']);
    Route::get('/ecarts/{ecart}', [EcartController::class, 'show']);
    Route::put('/ecarts/{ecart}', [EcartController::class, 'update']);
    Route::delete('/ecarts/{ecart}', [EcartController::class, 'destroy']);

    // Preuves justificatives attachees a un ecart (correction)
    Route::get('/ecarts/{ecart}/preuves', [EcartPreuveController::class, 'index']);
    Route::post('/ecarts/{ecart}/preuves', [EcartPreuveController::class, 'store']);
    Route::get('/ecart-preuves/{preuve}/telecharger', [EcartPreuveController::class, 'telecharger']);
    Route::delete('/ecart-preuves/{preuve}', [EcartPreuveController::class, 'destroy']);

    // ============================================================
    // Routes admin — Permissions granulaires par sous-groupe
    // ============================================================
    // Chaque sous-groupe est protege par UNE PERMISSION specifique. L'admin peut
    // creer dynamiquement des roles personnalises (ex. "Gestionnaire RH" avec
    // seulement manage-users, ou "Auditeur securite" avec seulement view-audit-logs)
    // sans devoir leur donner tous les droits admin.
    Route::prefix('admin')->group(function () {
        Route::middleware('permission:manage-users')->group(function () {
            Route::apiResource('users', UserController::class);
            // Activer / desactiver un compte (sans le supprimer).
            Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
        });

        Route::middleware('permission:manage-modules')->group(function () {
            Route::get('/modules', [ModuleController::class, 'index']);
            Route::get('/modules/{module}', [ModuleController::class, 'show']);
            Route::put('/modules/{module}', [ModuleController::class, 'update']);
            Route::post('/modules/{module}/toggle', [ModuleController::class, 'toggleActive']);
        });

        Route::middleware('permission:view-all-agents-admin')->group(function () {
            Route::get('/agents', [AgentAdminController::class, 'index']);
            Route::get('/agents/{agent}', [AgentAdminController::class, 'show']);
            Route::put('/agents/{agent}', [AgentAdminController::class, 'update']);
        });

        Route::middleware('permission:view-audit-logs')->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/audit-logs/categories', [AuditLogController::class, 'categories']);
        });

        // Roles et permissions : seuls les utilisateurs avec manage-roles peuvent y toucher.
        Route::middleware('permission:manage-roles')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::post('/roles/{role}/attach-permissions', [RoleController::class, 'attachPermissions']);
            Route::post('/roles/{role}/detach-permissions', [RoleController::class, 'detachPermissions']);
            Route::patch('/roles/{role}/toggle-active', [RoleController::class, 'toggleActive']);
            Route::get('/roles-liste', [RoleController::class, 'liste']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
        });

        // Validation des comptes inscrits via /inscription (workflow Phase 1).
        Route::middleware('permission:validate-accounts')->group(function () {
            Route::get('/comptes-en-attente', [\App\Http\Controllers\Api\Admin\ValidationComptesController::class, 'index']);
            Route::post('/comptes-en-attente/{user}/valider', [\App\Http\Controllers\Api\Admin\ValidationComptesController::class, 'valider']);
            Route::post('/comptes-en-attente/{user}/rejeter', [\App\Http\Controllers\Api\Admin\ValidationComptesController::class, 'rejeter']);
        });
    });
});
