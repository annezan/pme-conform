/**
 * DashboardInterne — Tableau de bord premium pour admin/manager/consultant.
 *
 * Sections :
 *  1. Hero salutation contextuelle + raccourcis
 *  2. KPI cards (clients / missions / documents / IA)
 *  3. Agents IA + Missions recentes
 */

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import api from '@/api/client';
import StatCard from '@/components/ui/StatCard';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import {
    BuildingOffice2Icon, ClipboardDocumentListIcon, DocumentTextIcon,
    ChatBubbleLeftRightIcon, ArrowRightIcon, BoltIcon, PlusIcon,
    SparklesIcon, ChartBarIcon, FlagIcon, MagnifyingGlassCircleIcon,
} from '@heroicons/react/24/outline';

const statutBadge = { brouillon: 'gray', en_cours: 'info', en_revue: 'warning', termine: 'success', archive: 'gray' };
const prioriteBadge = { basse: 'gray', normale: 'info', haute: 'warning', urgente: 'danger' };

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Bonjour';
    if (h < 18) return 'Bon après-midi';
    return 'Bonsoir';
}

const RACCOURCIS = [
    { to: '/clients', label: 'Clients', icon: BuildingOffice2Icon, palette: 'from-blue-500 to-indigo-600' },
    { to: '/missions', label: 'Missions', icon: ClipboardDocumentListIcon, palette: 'from-emerald-500 to-teal-600' },
    { to: '/analyses', label: 'Analyses', icon: MagnifyingGlassCircleIcon, palette: 'from-purple-500 to-fuchsia-600' },
    { to: '/asc/audit-flash', label: 'Audit Flash', icon: BoltIcon, palette: 'from-rose-500 to-red-600' },
    { to: '/plans-actions', label: 'Plans d\'actions', icon: FlagIcon, palette: 'from-amber-500 to-orange-600' },
    { to: '/agents', label: 'Agents IA', icon: SparklesIcon, palette: 'from-cyan-500 to-blue-600' },
];

export default function DashboardInterne() {
    const { user } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/dashboard')
            .then(r => setData(r.data))
            .finally(() => setLoading(false));
    }, []);

    if (loading) return <Loader />;
    if (!data) return null;

    const { statistiques, agents_disponibles = [], missions_recentes = [] } = data;

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            {/* HERO */}
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 px-6 lg:px-10 py-8 lg:py-10 mb-7 text-white shadow-xl shadow-blue-900/10">
                {/* Decor */}
                <div className="absolute -top-20 -right-20 w-72 h-72 bg-blue-500/30 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-32 -left-32 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none" />
                <div
                    className="absolute inset-0 opacity-[0.06] pointer-events-none"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <div className="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div className="max-w-xl">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur mb-4">
                            <SparklesIcon className="w-3.5 h-3.5 text-amber-300" />
                            <span className="text-[11px] font-semibold text-white/90 uppercase tracking-wider">PME-CONFORM · Espace ASC</span>
                        </div>
                        <h1 className="text-3xl lg:text-4xl font-bold tracking-tight">
                            {greeting()}, {user?.prenom} <span className="inline-block">👋</span>
                        </h1>
                        <p className="mt-2 text-blue-100/80 text-sm lg:text-base">
                            Voici un aperçu de votre portefeuille et de l'activité du jour.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        <Link to="/missions">
                            <Button as="span" size="lg" variant="primary" className="!text-white !shadow-lg !shadow-blue-900/30">
                                <PlusIcon className="w-4 h-4" /> Nouvelle mission
                            </Button>
                        </Link>
                        <Link to="/clients">
                            <Button as="span" size="lg" variant="primary" className="!bg-white/10 !text-white !ring-1 !ring-white/20 !backdrop-blur hover:!bg-white/20 hover:!from-white/10 hover:!via-white/10 hover:!to-white/10 !shadow-none">
                                Portefeuille clients
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>

            {/* KPI */}
            <section className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <StatCard
                    titre="Clients actifs"
                    valeur={statistiques.clients_actifs}
                    icon={BuildingOffice2Icon}
                    couleur="blue"
                    soustitre="Entreprises accompagnées"
                />
                <StatCard
                    titre="Missions en cours"
                    valeur={statistiques.missions_en_cours}
                    icon={ClipboardDocumentListIcon}
                    couleur="emerald"
                    soustitre="Dossiers ouverts"
                />
                <StatCard
                    titre="Documents"
                    valeur={statistiques.documents_total}
                    icon={DocumentTextIcon}
                    couleur="purple"
                    soustitre="Pièces collectées"
                />
                <StatCard
                    titre="Conversations IA"
                    valeur={statistiques.conversations_actives}
                    icon={ChatBubbleLeftRightIcon}
                    couleur="cyan"
                    soustitre="Sessions actives"
                />
            </section>

            {/* Raccourcis */}
            <section className="mb-7">
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-xs font-bold uppercase tracking-wider text-gray-500">Accès rapides</h2>
                </div>
                <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
                    {RACCOURCIS.map(r => {
                        const Icon = r.icon;
                        return (
                            <Link
                                key={r.to}
                                to={r.to}
                                className="group relative overflow-hidden bg-white rounded-2xl ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)] p-4 flex flex-col items-center gap-2 hover:-translate-y-0.5 hover:shadow-md hover:ring-blue-200 transition-all"
                            >
                                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${r.palette} flex items-center justify-center shadow-md shadow-blue-500/10 group-hover:scale-110 transition-transform`}>
                                    <Icon className="w-5 h-5 text-white" />
                                </div>
                                <p className="text-xs font-semibold text-gray-700 text-center">{r.label}</p>
                            </Link>
                        );
                    })}
                </div>
            </section>

            {/* Agents + Missions */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Agents IA */}
                <Card className="lg:col-span-2 overflow-hidden">
                    <div className="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                        <div className="flex items-center gap-2.5">
                            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                                <SparklesIcon className="w-4.5 h-4.5 text-white" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-gray-900">Agents IA disponibles</h2>
                                <p className="text-xs text-gray-500">{agents_disponibles.length} agents spécialisés à portée de clic</p>
                            </div>
                        </div>
                        <Link to="/agents" className="text-sm text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                            Voir tout <ArrowRightIcon className="w-3.5 h-3.5" />
                        </Link>
                    </div>
                    <div className="p-5">
                        {agents_disponibles.length === 0 ? (
                            <EmptyState icon={SparklesIcon} title="Aucun agent actif" description="Les agents IA seront listés ici lorsqu'ils seront activés." accent="indigo" compact />
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                {agents_disponibles.slice(0, 6).map(agent => (
                                    <Link
                                        key={agent.id}
                                        to={`/agents/${agent.slug}/chat`}
                                        className="group relative overflow-hidden flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all"
                                    >
                                        <div
                                            className="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white text-sm font-bold shadow-md ring-1 ring-black/5"
                                            style={{ backgroundColor: agent.couleur || '#3b82f6' }}
                                        >
                                            {agent.nom.charAt(0)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-gray-900 group-hover:text-blue-700 transition-colors truncate">{agent.nom}</p>
                                            <p className="text-xs text-gray-500 truncate">{agent.module?.nom || 'Noyau'}</p>
                                        </div>
                                        <ArrowRightIcon className="w-4 h-4 text-gray-300 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-all" />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>

                {/* Missions recentes */}
                <Card className="overflow-hidden">
                    <div className="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                        <div className="flex items-center gap-2.5">
                            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                                <ClipboardDocumentListIcon className="w-4.5 h-4.5 text-white" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-gray-900">Missions récentes</h2>
                                <p className="text-xs text-gray-500">{missions_recentes.length} dernières missions</p>
                            </div>
                        </div>
                        <Link to="/missions" className="text-sm text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                            <ArrowRightIcon className="w-3.5 h-3.5" />
                        </Link>
                    </div>
                    <div className="p-3 space-y-1.5">
                        {missions_recentes.length === 0 ? (
                            <EmptyState
                                icon={ClipboardDocumentListIcon}
                                title="Aucune mission"
                                description="Créez votre première mission pour commencer."
                                accent="emerald"
                                compact
                            >
                                <Link to="/missions"><Button as="span" size="sm">Nouvelle mission</Button></Link>
                            </EmptyState>
                        ) : (
                            missions_recentes.map(m => (
                                <Link
                                    key={m.id}
                                    to={`/missions/${m.id}`}
                                    className="group block p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/40 transition-all"
                                >
                                    <div className="flex items-start justify-between gap-2 mb-1">
                                        <p className="text-sm font-semibold text-gray-900 group-hover:text-blue-700 transition-colors truncate flex-1">{m.titre}</p>
                                        <Badge variant={statutBadge[m.statut] || 'gray'} size="sm" dot>{m.statut?.replace('_', ' ')}</Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-xs text-gray-500 truncate">{m.client?.raison_sociale}</p>
                                        {m.priorite && <Badge variant={prioriteBadge[m.priorite] || 'gray'} size="xs">{m.priorite}</Badge>}
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>
                </Card>
            </div>

            {/* Bandeau footer (info) */}
            <section className="mt-7">
                <Card variant="gradient" className="p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <div className="shrink-0 w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                            <ChartBarIcon className="w-5 h-5 text-blue-700" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-gray-900">Pilotez vos missions avec l'IA</p>
                            <p className="text-xs text-gray-600 mt-0.5">Lancez une analyse d'écarts ou un audit flash sur un client en quelques clics.</p>
                        </div>
                    </div>
                    <Link to="/analyses">
                        <Button as="span" size="sm" variant="primary">Lancer une analyse</Button>
                    </Link>
                </Card>
            </section>
        </div>
    );
}
