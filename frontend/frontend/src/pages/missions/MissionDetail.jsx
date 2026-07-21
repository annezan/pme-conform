/**
 * Page MissionDetail premium — Detail d'une mission avec hero contextuel,
 * stats, sections methode-specifiques, formulaires, documents et conversations IA.
 */

import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import api from '@/api/client';
import {
    listQuestionnairesParMission, supprimerQuestionnaire,
} from '@/api/questionnaires';
import {
    listConsultants, listCandidatsConsultants,
    attacherConsultants, detacherConsultant,
} from '@/api/missions';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import StatCard from '@/components/ui/StatCard';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import Modal from '@/components/ui/Modal';
import {
    ArrowLeftIcon, ClipboardDocumentListIcon, DocumentTextIcon, ChatBubbleLeftRightIcon,
    BoltIcon, SparklesIcon, RectangleGroupIcon, BuildingOffice2Icon,
    UserIcon, UsersIcon, UserPlusIcon, FlagIcon, ChevronRightIcon, ChartBarIcon,
    PencilSquareIcon, TrashIcon, CloudArrowUpIcon, CheckBadgeIcon, ClockIcon,
    XMarkIcon, MagnifyingGlassIcon,
} from '@heroicons/react/24/outline';

const statutCfg = {
    brouillon: { label: 'Brouillon', variant: 'gray' },
    en_cours: { label: 'En cours', variant: 'info' },
    en_revue: { label: 'En revue', variant: 'warning' },
    termine: { label: 'Terminé', variant: 'success' },
    archive: { label: 'Archivé', variant: 'gray' },
};

const prioriteCfg = {
    basse: { label: 'Basse', variant: 'gray' },
    normale: { label: 'Normale', variant: 'info' },
    haute: { label: 'Haute', variant: 'warning' },
    urgente: { label: 'Urgente', variant: 'danger' },
};

export default function MissionDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [mission, setMission] = useState(null);
    const [loading, setLoading] = useState(true);
    const [questionnaires, setQuestionnaires] = useState([]);
    const [consultants, setConsultants] = useState([]);
    const [peutAttacherConsultants, setPeutAttacherConsultants] = useState(false);
    const [showAjoutConsultant, setShowAjoutConsultant] = useState(false);

    const chargerQuestionnaires = () => {
        listQuestionnairesParMission(id)
            .then(r => setQuestionnaires(r.data || []))
            .catch(() => setQuestionnaires([]));
    };

    const chargerConsultants = () => {
        listConsultants(id).then(setConsultants).catch(() => setConsultants([]));
    };

    const chargerMission = () => {
        api.get(`/missions/${id}`).then(r => {
            setMission(r.data.mission);
            setPeutAttacherConsultants(!!r.data.peut_attacher_consultants);
        });
    };

    useEffect(() => {
        Promise.resolve()
            .then(chargerMission)
            .finally(() => setLoading(false));
        chargerQuestionnaires();
        chargerConsultants();
    }, [id]);

    const handleAjoutConsultants = async (userIds) => {
        try {
            const r = await attacherConsultants(id, userIds);
            alertSuccess(r.message || 'Consultants ajoutes');
            setShowAjoutConsultant(false);
            chargerConsultants();
        } catch (err) {
            alertError(err.response?.data?.message || 'Ajout refuse');
        }
    };

    const handleRetraitConsultant = async (consultant) => {
        const nomComplet = `${consultant.prenom} ${consultant.nom}`;
        if (!(await confirmAction(`Retirer ${nomComplet} de cette mission ?`, 'Confirmation'))) return;
        try {
            await detacherConsultant(id, consultant.id);
            alertSuccess(`${nomComplet} retire de la mission`);
            chargerConsultants();
        } catch (err) {
            alertError(err.response?.data?.message || 'Retrait refuse');
        }
    };

    const supprimerForm = async (q) => {
        if (!(await confirmDelete(q.titre))) return;
        try {
            await supprimerQuestionnaire(q.id);
            alertSuccess('Formulaire supprimé');
            chargerQuestionnaires();
        } catch (err) {
            alertError(err.response?.data?.message || 'Suppression refusée');
        }
    };

    if (loading) return <Loader />;
    if (!mission) {
        return (
            <div className="p-8 max-w-7xl mx-auto">
                <EmptyState icon={ClipboardDocumentListIcon} title="Mission introuvable" description="Cette mission n'existe pas ou vous n'y avez pas accès." accent="rose">
                    <Link to="/missions"><Button as="span" variant="primary">Retour aux missions</Button></Link>
                </EmptyState>
            </div>
        );
    }

    const statut = statutCfg[mission.statut] || statutCfg.brouillon;
    const priorite = prioriteCfg[mission.priorite] || prioriteCfg.normale;
    const methodeAccent = mission.methode === 'methode_3' ? 'rose' : mission.methode === 'methode_2' ? 'indigo' : 'blue';
    const methodeLabel = mission.methode === 'methode_3' ? 'Audit Flash' : mission.methode === 'methode_2' ? 'IA dynamique' : 'Classique';
    const methodeIcon = mission.methode === 'methode_3' ? BoltIcon : mission.methode === 'methode_2' ? SparklesIcon : ClipboardDocumentListIcon;
    const heroGradient = mission.methode === 'methode_3'
        ? 'from-rose-900 via-red-900 to-slate-900'
        : mission.methode === 'methode_2'
            ? 'from-indigo-900 via-purple-900 to-slate-900'
            : 'from-slate-900 via-blue-900 to-indigo-900';

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <Link to="/missions" className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux missions
            </Link>

            {/* HERO */}
            <section className={`relative overflow-hidden rounded-3xl bg-gradient-to-br ${heroGradient} px-6 lg:px-10 py-8 lg:py-10 mb-6 text-white shadow-xl shadow-blue-900/10`}>
                <div className="absolute -top-20 -right-20 w-72 h-72 bg-white/10 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-32 -left-32 w-96 h-96 bg-white/5 rounded-full blur-3xl pointer-events-none" />
                <div
                    className="absolute inset-0 opacity-[0.06] pointer-events-none"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <div className="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap mb-3">
                            <span className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-white/90 font-mono">{mission.reference}</span>
                            </span>
                            <Badge variant={statut.variant} solid size="md" dot>{statut.label}</Badge>
                            <Badge variant={priorite.variant} solid size="md">{priorite.label}</Badge>
                        </div>
                        <h1 className="text-3xl lg:text-4xl font-bold tracking-tight leading-tight">{mission.titre}</h1>
                        <p className="mt-2 text-blue-100/80 text-base flex items-center gap-2">
                            <BuildingOffice2Icon className="w-4 h-4" />
                            {mission.client?.raison_sociale}
                            {mission.client?.sigle && <span className="text-blue-200/60 text-sm">({mission.client.sigle})</span>}
                        </p>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/10 ring-1 ring-white/15 backdrop-blur text-xs font-semibold uppercase tracking-wider">
                            {(() => { const I = methodeIcon; return <I className="w-4 h-4" />; })()}
                            Méthode {mission.methode === 'methode_3' ? '3' : mission.methode === 'methode_2' ? '2' : '1'} · {methodeLabel}
                        </span>
                    </div>
                </div>
            </section>

            {/* STATS */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard titre="Progression" valeur={`${mission.progression || 0}%`} icon={ChartBarIcon} couleur="emerald" soustitre="Avancement global" />
                <StatCard titre="Formulaires" valeur={questionnaires.length} icon={ClipboardDocumentListIcon} couleur="blue" soustitre="Liés à la mission" />
                <StatCard titre="Documents" valeur={mission.documents?.length || 0} icon={DocumentTextIcon} couleur="purple" soustitre="Pièces collectées" />
                <StatCard titre="Conversations" valeur={mission.conversations?.length || 0} icon={ChatBubbleLeftRightIcon} couleur="cyan" soustitre="Sessions IA" />
            </div>

            {/* Infos */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <InfoMini icon={FlagIcon} label="Statut" value={statut.label} accent="blue" />
                <InfoMini icon={FlagIcon} label="Priorité" value={priorite.label} accent="amber" />
                <InfoMini icon={ChartBarIcon} label="Progression" value={`${mission.progression || 0}%`} accent="emerald" />
                <InfoMini icon={UserIcon} label="Responsable" value={mission.responsable ? `${mission.responsable.prenom} ${mission.responsable.nom}` : '—'} accent="indigo" />
            </div>

            {mission.description && (
                <Card className="p-5 mb-6">
                    <h2 className="font-bold text-gray-900 mb-2 text-sm uppercase tracking-wider">Description</h2>
                    <p className="text-sm text-gray-600 whitespace-pre-wrap leading-relaxed">{mission.description}</p>
                </Card>
            )}

            {/* Methode 3 bandeau */}
            {mission.methode === 'methode_3' && (
                <Card variant="gradient" className="p-5 mb-6 border-2 border-rose-200">
                    <div className="flex items-start gap-4">
                        <div className="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center shadow-md shadow-rose-500/20">
                            <BoltIcon className="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-wider text-rose-700 mb-0.5">Méthode 3 — Audit Flash</p>
                            <p className="text-sm text-gray-700">
                                Mission Audit Flash : pas de matrice de collecte ni d'organigramme. Un questionnaire figé de 10 questions (Scan Pénal du Dirigeant) a été généré automatiquement et est disponible dans l'espace du client.
                            </p>
                        </div>
                    </div>
                </Card>
            )}

            {/* Methode 2 etapes */}
            {mission.methode === 'methode_2' && (
                <Card variant="gradient" className="p-5 mb-6 border-2 border-purple-200">
                    <div className="flex items-center gap-2 mb-4">
                        <SparklesIcon className="w-4 h-4 text-purple-700" />
                        <p className="text-[11px] font-bold uppercase tracking-wider text-purple-700">Méthode 2 — IA dynamique</p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <EtapeCard to={`/missions/${id}/matrice`} num={1} titre="Matrice de collecte" desc="Cartographie initiale + pièces" icon={RectangleGroupIcon} />
                        <EtapeCard to={`/missions/${id}/organigramme`} num={2} titre="Organigramme" desc="Saisie ou upload" icon={BuildingOffice2Icon} />
                        <EtapeCard to={`/missions/${id}/questionnaires`} num={3} titre="Questionnaires IA" desc="Générés par pôle" icon={ClipboardDocumentListIcon} />
                    </div>
                </Card>
            )}

            {/* Consultants affectes a la mission */}
            <Card variant="elevated" className="overflow-hidden mb-6">
                <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 flex items-center justify-center">
                            <UsersIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-gray-900">Consultants affectés</h2>
                            <p className="text-xs text-gray-500">{consultants.length} utilisateur(s) — createur, responsable et consultants additionnels.</p>
                        </div>
                    </div>
                    {peutAttacherConsultants && (
                        <Button size="sm" variant="primary" onClick={() => setShowAjoutConsultant(true)}>
                            <UserPlusIcon className="w-4 h-4" /> Ajouter un consultant
                        </Button>
                    )}
                </div>
                <div className="p-5">
                    {consultants.length === 0 ? (
                        <EmptyState
                            icon={UsersIcon}
                            title="Aucun consultant affecté"
                            description="Ajoutez un consultant pour partager la mission."
                            accent="purple"
                            compact
                        />
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {consultants.map(c => {
                                const initiales = `${c.prenom?.charAt(0) || ''}${c.nom?.charAt(0) || ''}`;
                                return (
                                    <span key={c.id} className="inline-flex items-center gap-2 pl-2 pr-1 py-1.5 bg-gradient-to-br from-blue-50 to-indigo-50 ring-1 ring-blue-100 rounded-xl text-sm">
                                        <div className="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                            {initiales}
                                        </div>
                                        <div className="flex flex-col leading-tight">
                                            <span className="font-semibold text-gray-800 text-sm">{c.prenom} {c.nom}</span>
                                            <span className="text-[10px] uppercase tracking-wider text-gray-500">
                                                {c.est_createur ? 'Créateur' : c.role_dans_mission || 'Consultant'}
                                            </span>
                                        </div>
                                        {peutAttacherConsultants && !c.est_createur && (
                                            <button
                                                onClick={() => handleRetraitConsultant(c)}
                                                className="ml-1 p-1 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                title={`Retirer ${c.prenom} ${c.nom}`}
                                            >
                                                <XMarkIcon className="w-4 h-4" />
                                            </button>
                                        )}
                                    </span>
                                );
                            })}
                        </div>
                    )}
                </div>
            </Card>

            {showAjoutConsultant && (
                <ModalAjoutConsultant
                    missionId={id}
                    onClose={() => setShowAjoutConsultant(false)}
                    onConfirm={handleAjoutConsultants}
                />
            )}

            {/* Formulaires de la mission */}
            <Card variant="elevated" className="overflow-hidden mb-6">
                <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <ClipboardDocumentListIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-gray-900">Formulaires de la mission</h2>
                            <p className="text-xs text-gray-500">{questionnaires.length} formulaire(s) — le client et l'agent ASC peuvent les remplir, éditer ou supprimer.</p>
                        </div>
                    </div>
                </div>
                <div className="p-4">
                    {questionnaires.length === 0 ? (
                        <EmptyState
                            icon={ClipboardDocumentListIcon}
                            title="Aucun formulaire"
                            description="Cette mission n'a pas encore de formulaire associé."
                            accent="blue"
                            compact
                        />
                    ) : (
                        <div className="space-y-2">
                            {questionnaires.map(q => {
                                const total = (q.questions || []).length;
                                const repondues = (q.reponses || []).filter(r => r.repondu).length;
                                const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;
                                return (
                                    <div key={q.id} className="group flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-all">
                                        <div className="flex-1 min-w-0">
                                            <p className="font-semibold text-gray-900 text-sm truncate">{q.titre}</p>
                                            <p className="text-xs text-gray-500 mt-0.5 truncate">
                                                {q.pole}{q.service ? ' / ' + q.service : ''} · {repondues}/{total} réponses · {q.statut}
                                            </p>
                                            {total > 0 && (
                                                <div className="mt-1.5 h-1 rounded-full bg-gray-100 overflow-hidden max-w-xs">
                                                    <div className={`h-full ${pct === 100 ? 'bg-emerald-500' : 'bg-blue-500'} transition-all`} style={{ width: `${pct}%` }} />
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {mission.methode === 'methode_3' && (q.statut === 'rempli' || q.statut === 'valide' || repondues > 0) && (
                                                <Button variant="success" size="sm" onClick={() => navigate(`/questionnaires-generes/${q.id}/audit-flash-resultat`)}>
                                                    <ChartBarIcon className="w-4 h-4" /> Résultat
                                                </Button>
                                            )}
                                            <Button variant="secondary" size="sm" onClick={() => navigate(`/questionnaires-generes/${q.id}`)}>
                                                <PencilSquareIcon className="w-4 h-4" /> Éditer
                                            </Button>
                                            <Button variant="danger" size="sm" iconOnly onClick={() => supprimerForm(q)}>
                                                <TrashIcon className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </Card>

            {/* Documents + Conversations */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <Card variant="elevated" className="overflow-hidden">
                    <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                        <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 flex items-center justify-center">
                                <DocumentTextIcon className="w-4.5 h-4.5 text-white" />
                            </div>
                            <h2 className="text-base font-bold text-gray-900">Documents ({mission.documents?.length || 0})</h2>
                        </div>
                        <Link to="/documents/upload" className="text-sm text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                            <CloudArrowUpIcon className="w-4 h-4" />
                            Uploader
                        </Link>
                    </div>
                    <div className="p-4">
                        {(mission.documents?.length || 0) === 0 ? (
                            <EmptyState icon={DocumentTextIcon} title="Aucun document" description="Aucun document n'a encore été uploadé." accent="indigo" compact />
                        ) : (
                            <div className="space-y-1.5">
                                {mission.documents?.map(d => (
                                    <div key={d.id} className="flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-all">
                                        <DocumentTextIcon className="w-4 h-4 text-gray-400 shrink-0" />
                                        <span className="text-sm text-gray-800 truncate flex-1">{d.titre}</span>
                                        <Badge variant={d.statut === 'indexe' ? 'success' : d.statut === 'erreur' ? 'danger' : 'gray'} size="xs">{d.statut}</Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>

                <Card variant="elevated" className="overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                            <ChatBubbleLeftRightIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <h2 className="text-base font-bold text-gray-900">Conversations IA ({mission.conversations?.length || 0})</h2>
                    </div>
                    <div className="p-4">
                        {(mission.conversations?.length || 0) === 0 ? (
                            <EmptyState icon={ChatBubbleLeftRightIcon} title="Aucune conversation" description="Démarrez une conversation avec un agent IA depuis /agents." accent="cyan" compact />
                        ) : (
                            <div className="space-y-1.5">
                                {mission.conversations?.map(c => (
                                    <Link key={c.id} to={`/agents/${c.agent?.slug}/chat/${c.id}`} className="flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-all group">
                                        <SparklesIcon className="w-4 h-4 text-cyan-600 shrink-0" />
                                        <span className="text-sm font-semibold text-gray-800 flex-1 truncate group-hover:text-blue-700 transition-colors">{c.agent?.nom}</span>
                                        <span className="text-[11px] text-gray-400">{new Date(c.updated_at).toLocaleDateString('fr-FR')}</span>
                                        <ChevronRightIcon className="w-3.5 h-3.5 text-gray-300 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-all" />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </div>
    );
}

function InfoMini({ icon: Icon, label, value, accent = 'blue' }) {
    const accents = {
        blue: 'from-blue-50 to-indigo-50 text-blue-700',
        emerald: 'from-emerald-50 to-teal-50 text-emerald-700',
        amber: 'from-amber-50 to-orange-50 text-amber-700',
        indigo: 'from-indigo-50 to-purple-50 text-indigo-700',
    };
    return (
        <div className="bg-white rounded-xl ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)] p-3 flex items-center gap-3">
            <div className={`shrink-0 w-9 h-9 rounded-lg bg-gradient-to-br ${accents[accent] || accents.blue} flex items-center justify-center`}>
                <Icon className="w-4.5 h-4.5" />
            </div>
            <div className="min-w-0">
                <p className="text-[10px] uppercase tracking-wider font-semibold text-gray-500">{label}</p>
                <p className="text-sm font-bold text-gray-900 truncate">{value}</p>
            </div>
        </div>
    );
}

function EtapeCard({ to, num, titre, desc, icon: Icon }) {
    return (
        <Link to={to} className="group block bg-white rounded-2xl ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)] p-4 hover:-translate-y-0.5 hover:shadow-md hover:ring-purple-200 transition-all">
            <div className="flex items-center justify-between mb-2">
                <span className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold">{num}</span>
                <ChevronRightIcon className="w-4 h-4 text-gray-300 group-hover:text-purple-600 group-hover:translate-x-0.5 transition-all" />
            </div>
            <div className="flex items-center gap-2 mb-1">
                <Icon className="w-4 h-4 text-purple-600" />
                <p className="font-bold text-gray-900 text-sm">{titre}</p>
            </div>
            <p className="text-xs text-gray-500">{desc}</p>
        </Link>
    );
}

/**
 * Modale — Ajouter un ou plusieurs consultants a la mission.
 * Charge la liste des candidats (users ASC non deja affectes) et permet
 * de cocher plusieurs users en une fois pour l'affectation en batch.
 */
function ModalAjoutConsultant({ missionId, onClose, onConfirm }) {
    const [candidats, setCandidats] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selection, setSelection] = useState(new Set());
    const [recherche, setRecherche] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        listCandidatsConsultants(missionId)
            .then(setCandidats)
            .catch(() => setCandidats([]))
            .finally(() => setLoading(false));
    }, [missionId]);

    const toggle = (id) => {
        setSelection(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id); else next.add(id);
            return next;
        });
    };

    const submit = async () => {
        if (selection.size === 0) return;
        setSaving(true);
        try {
            await onConfirm(Array.from(selection));
        } finally {
            setSaving(false);
        }
    };

    const filtres = recherche
        ? candidats.filter(c => `${c.prenom} ${c.nom} ${c.email}`.toLowerCase().includes(recherche.toLowerCase()))
        : candidats;

    return (
        <Modal
            open={true}
            onClose={onClose}
            title="Ajouter un consultant"
            subtitle="Choisir parmi les utilisateurs ASC (admin, manager, consultant)"
            icon={UserPlusIcon}
            accent="purple"
            size="md"
            footer={(
                <>
                    <Button variant="secondary" onClick={onClose} disabled={saving}>Annuler</Button>
                    <Button variant="primary" onClick={submit} loading={saving} disabled={selection.size === 0 || saving}>
                        {saving ? 'Ajout...' : `Ajouter ${selection.size > 0 ? `(${selection.size})` : ''}`}
                    </Button>
                </>
            )}
        >
            <div className="space-y-3">
                <div className="relative">
                    <MagnifyingGlassIcon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        value={recherche}
                        onChange={e => setRecherche(e.target.value)}
                        placeholder="Rechercher par nom, prenom ou email"
                        className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                {loading ? (
                    <p className="text-sm text-gray-500 text-center py-8">Chargement...</p>
                ) : filtres.length === 0 ? (
                    <p className="text-sm text-gray-500 text-center py-8 italic">
                        {candidats.length === 0
                            ? 'Aucun consultant disponible : tous les utilisateurs ASC sont deja affectes ou aucun compte actif.'
                            : 'Aucun resultat pour cette recherche.'}
                    </p>
                ) : (
                    <div className="max-h-80 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-100">
                        {filtres.map(c => {
                            const initiales = `${c.prenom?.charAt(0) || ''}${c.nom?.charAt(0) || ''}`;
                            const coche = selection.has(c.id);
                            return (
                                <button
                                    key={c.id}
                                    type="button"
                                    onClick={() => toggle(c.id)}
                                    className={`w-full flex items-center gap-3 p-3 text-left hover:bg-blue-50/40 transition-colors ${coche ? 'bg-blue-50' : ''}`}
                                >
                                    <input type="checkbox" checked={coche} readOnly className="w-4 h-4 rounded text-blue-600" />
                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-xs font-bold">
                                        {initiales}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-semibold text-gray-900 text-sm truncate">{c.prenom} {c.nom}</p>
                                        <p className="text-xs text-gray-500 truncate">{c.email} · <span className="uppercase">{c.role}</span></p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </Modal>
    );
}
