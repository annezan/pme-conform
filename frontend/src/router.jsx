/**
 * Configuration du routeur React — PME-CONFORM.
 */

import { Routes, Route } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import ProtectedRoute from '@/components/ProtectedRoute';

// Auth
import Login from '@/pages/auth/Login';
import Register from '@/pages/auth/Register';

// Pages principales
import Dashboard from '@/pages/Dashboard';
import AgentsList from '@/pages/agents/AgentsList';
import AgentChat from '@/pages/agents/AgentChat';
import DocumentGeneration from '@/pages/agents/DocumentGeneration';
import DocumentUpload from '@/pages/documents/DocumentUpload';
import ClientsList from '@/pages/clients/ClientsList';
import ClientDetail from '@/pages/clients/ClientDetail';
import MissionsList from '@/pages/missions/MissionsList';
import MissionDetail from '@/pages/missions/MissionDetail';
import MatriceCollecte from '@/pages/missions/MatriceCollecte';
import OrganigrammePage from '@/pages/missions/OrganigrammePage';
import QuestionnairesGeneres from '@/pages/missions/QuestionnairesGeneres';
import QuestionnaireRemplir from '@/pages/missions/QuestionnaireRemplir';
import AuditFlashResultat from '@/pages/missions/AuditFlashResultat';
import ReferentielsList from '@/pages/referentiels/ReferentielsList';
import MesDocuments from '@/pages/client/MesDocuments';
import MesFormulaires from '@/pages/client/MesFormulaires';
import MesMatrices from '@/pages/client/MesMatrices';
import MatriceClient from '@/pages/client/MatriceClient';
import AuditFlash from '@/pages/client/AuditFlash';
import AuditFlashLibres from '@/pages/asc/AuditFlashLibres';
import TraitementsList from '@/pages/traitements/TraitementsList';
import TraitementForm from '@/pages/traitements/TraitementForm';
import TraitementDetail from '@/pages/traitements/TraitementDetail';
import ChartesList from '@/pages/chartes/ChartesList';
import CharteSignature from '@/pages/chartes/CharteSignature';
import RegistreKyc from '@/pages/registre/RegistreKyc';
import PlansActionsList from '@/pages/plansActions/PlansActionsList';
import PlanActionForm from '@/pages/plansActions/PlanActionForm';
import PlanActionDetail from '@/pages/plansActions/PlanActionDetail';
import AscPortefeuille from '@/pages/asc/AscPortefeuille';
import AscClientDetail from '@/pages/asc/AscClientDetail';
import AnalysesList from '@/pages/analyses/AnalysesList';
import NouvelleAnalyse from '@/pages/analyses/NouvelleAnalyse';
import AnalyseDetail from '@/pages/analyses/AnalyseDetail';

// Profil
import Profile from '@/pages/profile/Profile';
import Settings from '@/pages/profile/Settings';

// Admin
import AdminUsers from '@/pages/admin/AdminUsers';
import AdminModules from '@/pages/admin/AdminModules';
import AdminAgents from '@/pages/admin/AdminAgents';
import AdminAudit from '@/pages/admin/AdminAudit';
import AdminComptesEnAttente from '@/pages/admin/AdminComptesEnAttente';
import MesUtilisateurs from '@/pages/client/MesUtilisateurs';
import ChangerMotDePasse from '@/pages/auth/ChangerMotDePasse';
import MotDePasseOublie from '@/pages/auth/MotDePasseOublie';
import ResetPassword from '@/pages/auth/ResetPassword';
import AscQuestionnaireGestion from '@/pages/asc/AscQuestionnaireGestion';
import AdminSecteurs from '@/pages/admin/AdminSecteurs';
import AdminRoles from '@/pages/admin/AdminRoles';
import AdminPermissionsParRole from '@/pages/admin/AdminPermissionsParRole';

export default function AppRoutes() {
    return (
        <Routes>
            {/* Routes publiques */}
            <Route path="/login" element={<Login />} />
            <Route path="/inscription" element={<Register />} />
            <Route path="/mot-de-passe-oublie" element={<MotDePasseOublie />} />
            <Route path="/reset-password/:token" element={<ResetPassword />} />

            {/* Changement du mot de passe temporaire (accessible meme avec must_change_password) */}
            <Route element={<ProtectedRoute allowDuringPasswordChange />}>
                <Route path="/changer-mot-de-passe" element={<ChangerMotDePasse />} />
            </Route>

            {/* Routes authentifiees */}
            <Route element={<ProtectedRoute />}>
                <Route element={<AppLayout />}>
                    <Route path="/" element={<Dashboard />} />

                    {/* Agents IA */}
                    <Route path="/agents" element={<AgentsList />} />
                    <Route path="/agents/:slug/chat" element={<AgentChat />} />
                    <Route path="/agents/:slug/chat/:conversationId" element={<AgentChat />} />

                    {/* Documents */}
                    <Route path="/documents/generer" element={<DocumentGeneration />} />
                    <Route path="/documents/upload" element={<DocumentUpload />} />

                    {/* Clients */}
                    <Route path="/clients" element={<ClientsList />} />
                    <Route path="/clients/:id" element={<ClientDetail />} />

                    {/* Missions */}
                    <Route path="/missions" element={<MissionsList />} />
                    <Route path="/missions/:id" element={<MissionDetail />} />
                    <Route path="/missions/:id/matrice" element={<MatriceCollecte />} />
                    <Route path="/missions/:id/organigramme" element={<OrganigrammePage />} />
                    <Route path="/missions/:id/questionnaires" element={<QuestionnairesGeneres />} />
                    <Route path="/questionnaires-generes/:qid" element={<QuestionnaireRemplir />} />
                    <Route path="/questionnaires-generes/:qid/audit-flash-resultat" element={<AuditFlashResultat />} />

                    {/* Referentiels */}
                    <Route path="/referentiels" element={<ReferentielsList />} />

                    {/* Espace client */}
                    <Route path="/mes-documents" element={<MesDocuments />} />
                    <Route path="/mes-formulaires" element={<MesFormulaires />} />
                    <Route path="/mes-matrices" element={<MesMatrices />} />
                    <Route path="/mes-matrices/:missionId" element={<MatriceClient />} />
                    <Route path="/audit-flash" element={<AuditFlash />} />

                    {/* Audit Flash — vue ASC consolidee */}
                    <Route path="/asc/audit-flash" element={<AuditFlashLibres />} />

                    {/* Traitements de donnees (PME-CONFORM) */}
                    <Route path="/traitements" element={<TraitementsList />} />
                    <Route path="/traitements/nouveau" element={<TraitementForm />} />
                    <Route path="/traitements/:id" element={<TraitementDetail />} />
                    <Route path="/traitements/:id/modifier" element={<TraitementForm />} />

                    {/* Chartes & signatures */}
                    <Route path="/chartes" element={<ChartesList />} />
                    <Route path="/chartes/:id" element={<CharteSignature />} />

                    {/* Registre KYC */}
                    <Route path="/registre-kyc" element={<RegistreKyc />} />

                    {/* Plans d'actions */}
                    <Route path="/plans-actions" element={<PlansActionsList />} />
                    <Route path="/plans-actions/nouveau" element={<PlanActionForm />} />
                    <Route path="/plans-actions/:id" element={<PlanActionDetail />} />

                    {/* Portefeuille ASC (consultant/admin/manager) */}
                    <Route path="/asc/portefeuille" element={<AscPortefeuille />} />
                    <Route path="/asc/portefeuille/:clientId" element={<AscClientDetail />} />

                    {/* Analyses d'ecarts */}
                    <Route path="/analyses" element={<AnalysesList />} />
                    <Route path="/analyses/nouvelle" element={<NouvelleAnalyse />} />
                    <Route path="/analyses/:id" element={<AnalyseDetail />} />

                    {/* Profil et Parametres */}
                    <Route path="/profil" element={<Profile />} />
                    <Route path="/parametres" element={<Settings />} />

                    {/* Admin — chaque route est gatee par sa permission specifique. */}
                    {/* Si un user tape une URL admin sans permission, redirection vers / . */}
                    <Route element={<ProtectedRoute permission="manage-users" />}>
                        <Route path="/admin/utilisateurs" element={<AdminUsers />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="manage-roles" />}>
                        <Route path="/admin/roles" element={<AdminRoles />} />
                        <Route path="/admin/permissions" element={<AdminPermissionsParRole />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="view-all-secteurs" />}>
                        <Route path="/admin/secteurs" element={<AdminSecteurs />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="manage-modules" />}>
                        <Route path="/admin/modules" element={<AdminModules />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="view-all-agents-admin" />}>
                        <Route path="/admin/agents" element={<AdminAgents />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="view-audit-logs" />}>
                        <Route path="/admin/audit" element={<AdminAudit />} />
                    </Route>
                    <Route element={<ProtectedRoute permission="validate-accounts" />}>
                        <Route path="/admin/comptes-en-attente" element={<AdminComptesEnAttente />} />
                    </Route>

                    {/* Espace client_admin uniquement : exclu pour ASC (view-portefeuille) */}
                    <Route element={<ProtectedRoute permission="manage-client-users" excludePermission="view-portefeuille" />}>
                        <Route path="/mes-utilisateurs" element={<MesUtilisateurs />} />
                    </Route>

                    {/* ASC : gestion d'un questionnaire (review + publication + CRUD questions) */}
                    <Route element={<ProtectedRoute permission="view-all-questionnaires" />}>
                        <Route path="/asc/questionnaires/:id" element={<AscQuestionnaireGestion />} />
                    </Route>
                </Route>
            </Route>
        </Routes>
    );
}
