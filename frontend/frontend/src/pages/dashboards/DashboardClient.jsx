/**
 * DashboardClient — Tableau de bord premium pour client / client_admin.
 *
 * Vue compliance personnelle :
 *  1. Hero salutation + nom de l'entreprise
 *  2. KPI cards (formulaires a remplir, score audit flash, matrices, documents)
 *  3. Audit Flash express CTA
 *  4. Actions a faire + raccourcis
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
    ClipboardDocumentListIcon, FolderOpenIcon, RectangleGroupIcon, BoltIcon,
    ArrowRightIcon, ShieldCheckIcon, SparklesIcon, FlagIcon, BookOpenIcon,
    PencilSquareIcon, CheckCircleIcon, ExclamationTriangleIcon, FireIcon,
} from '@heroicons/react/24/outline';

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Bonjour';
    if (h < 18) return 'Bon après-midi';
    return 'Bonsoir';
}

// Chaque raccourci porte la permission requise pour etre affiche. Les standard
// users (sans la permission correspondante) verront un dashboard reduit.
const RACCOURCIS = [
    { to: '/mes-documents', label: 'Mes documents', icon: FolderOpenIcon, palette: 'from-blue-500 to-indigo-600', permission: 'view-client-documents' },
    { to: '/mes-formulaires', label: 'Formulaires', icon: ClipboardDocumentListIcon, palette: 'from-emerald-500 to-teal-600', permission: 'view-client-questionnaires' },
    { to: '/mes-matrices', label: 'Cartographie', icon: RectangleGroupIcon, palette: 'from-purple-500 to-fuchsia-600', permission: 'view-matrice' },
    { to: '/audit-flash', label: 'Audit Flash', icon: BoltIcon, palette: 'from-rose-500 to-red-600', permission: 'access-audit-flash' },
    { to: '/plans-actions', label: 'Plans d\'actions', icon: FlagIcon, palette: 'from-amber-500 to-orange-600', permission: 'view-plans-actions' },
    { to: '/chartes', label: 'Chartes', icon: BookOpenIcon, palette: 'from-cyan-500 to-blue-600', permission: 'view-chartes' },
];

const STATUT_LABEL = {
    brouillon: { label: 'À démarrer', variant: 'gray' },
    envoye: { label: 'À remplir', variant: 'info' },
    rempli: { label: 'Rempli', variant: 'success' },
    valide: { label: 'Validé', variant: 'success' },
};

const ZONE_AUDIT = {
    conforme: { label: 'Conforme', variant: 'success', icon: CheckCircleIcon },
    danger: { label: 'Zone de danger', variant: 'warning', icon: ExclamationTriangleIcon },
    rouge: { label: 'Zone rouge', variant: 'danger', icon: FireIcon },
};

function calculerScoreAuditFlash(q) {
    if (!q) return null;
    const questions = q.questions || [];
    const reponses = q.reponses || [];
    if (questions.length === 0) return null;
    const repIndex = new Map(reponses.map(r => [Number(r.numero), r]));
    let score = 0;
    let repondues = 0;
    for (const qu of questions) {
        const r = repIndex.get(Number(qu.numero));
        const text = String(r?.reponse ?? '').trim().toLowerCase();
        if (text !== '') repondues++;
        if (text === '' || text === 'oui') continue;
        score += 10;
    }
    let zone = 'conforme';
    if (score > 40) zone = 'rouge';
    else if (score > 10) zone = 'danger';
    return { score, repondues, total: questions.length, zone };
}

export default function DashboardClient() {
    const { user, hasPermission } = useAuth();
    const [formulaires, setFormulaires] = useState([]);
    const [auditFlash, setAuditFlash] = useState(null);
    const [clientNom, setClientNom] = useState('');
    const [loading, setLoading] = useState(true);

    const peutAuditFlash = hasPermission('access-audit-flash');

    useEffect(() => {
        // L'endpoint /client/audit-flash est gate par access-audit-flash : on ne le
        // requete que si l'utilisateur en a le droit, sinon on aurait une 403.
        const calls = [
            api.get('/client/questionnaires').then(r => r.data?.data || []),
            api.get('/client/documents').then(r => r.data?.data || []).catch(() => []),
        ];
        if (peutAuditFlash) {
            calls.push(api.get('/client/audit-flash').then(r => r.data));
        }
        Promise.allSettled(calls).then((results) => {
            const [fRes, _docsRes, afRes] = results;
            if (fRes.status === 'fulfilled') setFormulaires(fRes.value);
            if (afRes && afRes.status === 'fulfilled') {
                setAuditFlash(afRes.value);
                const c = afRes.value?.client?.raison_sociale;
                if (c) setClientNom(c);
            }
            setLoading(false);
        });
    }, [peutAuditFlash]);

    if (loading) return <Loader />;

    const aRemplir = formulaires.filter(q => q.statut === 'envoye' || q.statut === 'brouillon');
    const remplis = formulaires.filter(q => q.statut === 'rempli' || q.statut === 'valide');
    const scoreAudit = calculerScoreAuditFlash(auditFlash?.questionnaire);
    const zoneInfo = scoreAudit ? ZONE_AUDIT[scoreAudit.zone] : null;
    const ZoneIcon = zoneInfo?.icon;

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            {/* HERO */}
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-blue-900 to-slate-900 px-6 lg:px-10 py-8 lg:py-10 mb-7 text-white shadow-xl shadow-indigo-900/10">
                <div className="absolute -top-24 -right-24 w-80 h-80 bg-indigo-500/30 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-32 -left-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl pointer-events-none" />
                <div
                    className="absolute inset-0 opacity-[0.06] pointer-events-none"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <div className="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
                    <div className="max-w-xl">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur mb-4">
                            <ShieldCheckIcon className="w-3.5 h-3.5 text-emerald-300" />
                            <span className="text-[11px] font-semibold text-white/90 uppercase tracking-wider">Espace conformité</span>
                        </div>
                        <h1 className="text-3xl lg:text-4xl font-bold tracking-tight">
                            {greeting()}, {user?.prenom} <span className="inline-block">👋</span>
                        </h1>
                        {clientNom && (
                            <p className="mt-1 text-lg text-blue-100 font-medium">{clientNom}</p>
                        )}
                        <p className="mt-2 text-blue-100/80 text-sm lg:text-base">
                            Pilotez votre conformité et complétez vos formulaires en quelques minutes.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        {peutAuditFlash && (
                            <Link to="/audit-flash">
                                <Button as="span" size="lg" variant="primary" className="!bg-white !text-rose-700 !ring-0 hover:!bg-rose-50 hover:!from-white hover:!via-white hover:!to-white !shadow-lg">
                                    <BoltIcon className="w-4 h-4" /> Lancer Audit Flash
                                </Button>
                            </Link>
                        )}
                        <Link to="/mes-formulaires">
                            <Button as="span" size="lg" variant="primary" className="!bg-white/10 !text-white !ring-1 !ring-white/20 !backdrop-blur hover:!bg-white/20 hover:!from-white/10 hover:!via-white/10 hover:!to-white/10 !shadow-none">
                                Mes formulaires
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>

            {/* KPI */}
            <section className={`grid grid-cols-2 ${peutAuditFlash ? 'lg:grid-cols-4' : 'lg:grid-cols-3'} gap-4 mb-7`}>
                <StatCard
                    titre="À remplir"
                    valeur={aRemplir.length}
                    icon={PencilSquareIcon}
                    couleur="amber"
                    soustitre="Formulaires en attente"
                    onClick={() => window.location.href = '/mes-formulaires'}
                />
                <StatCard
                    titre="Finalisés"
                    valeur={remplis.length}
                    icon={CheckCircleIcon}
                    couleur="emerald"
                    soustitre="Formulaires déposés"
                />
                {peutAuditFlash && (
                    <StatCard
                        titre="Audit Flash"
                        valeur={scoreAudit ? `${scoreAudit.score}/100` : '—'}
                        icon={BoltIcon}
                        couleur={scoreAudit?.zone === 'rouge' ? 'rose' : scoreAudit?.zone === 'danger' ? 'amber' : 'emerald'}
                        soustitre={scoreAudit ? `${scoreAudit.repondues}/${scoreAudit.total} questions` : 'Pas encore lancé'}
                        onClick={() => window.location.href = '/audit-flash'}
                    />
                )}
                <StatCard
                    titre="Documents"
                    valeur={formulaires.length}
                    icon={FolderOpenIcon}
                    couleur="blue"
                    soustitre="Total pièce"
                />
            </section>

            {/* Audit Flash CTA / Score — reserve aux users avec access-audit-flash */}
            {peutAuditFlash && (
            <section className="mb-7">
                {!scoreAudit || scoreAudit.repondues === 0 ? (
                    <Card variant="gradient" className="relative overflow-hidden p-6 lg:p-7">
                        <div className="absolute -right-10 -top-10 w-44 h-44 bg-rose-500/15 rounded-full blur-3xl pointer-events-none" />
                        <div className="relative flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                            <div className="flex items-start gap-4 max-w-2xl">
                                <div className="shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center shadow-lg shadow-rose-500/30">
                                    <BoltIcon className="w-7 h-7 text-white" />
                                </div>
                                <div>
                                    <p className="text-[11px] font-bold uppercase tracking-wider text-rose-700 mb-1">Méthode 3 · Audit Flash</p>
                                    <h2 className="text-xl font-bold text-gray-900 mb-1">Découvrez votre exposition pénale en 3 minutes</h2>
                                    <p className="text-sm text-gray-600">
                                        10 questions C-Level pour évaluer immédiatement votre conformité à la Loi 2013-450, au RGSSI et aux arrêtés ARTCI.
                                    </p>
                                </div>
                            </div>
                            <Link to="/audit-flash">
                                <Button as="span" size="lg" variant="danger" className="shrink-0">
                                    <BoltIcon className="w-4 h-4" /> Démarrer
                                </Button>
                            </Link>
                        </div>
                    </Card>
                ) : (
                    <Card className="relative overflow-hidden p-6">
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                            <div className="flex items-start gap-4">
                                <div className={`shrink-0 w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg ${
                                    scoreAudit.zone === 'rouge' ? 'bg-gradient-to-br from-rose-500 to-red-600 shadow-rose-500/30' :
                                    scoreAudit.zone === 'danger' ? 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/30' :
                                    'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/30'
                                }`}>
                                    {ZoneIcon && <ZoneIcon className="w-7 h-7 text-white" />}
                                </div>
                                <div>
                                    <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1">Votre dernier Audit Flash</p>
                                    <h2 className="text-xl font-bold text-gray-900 mb-1.5">
                                        Score {scoreAudit.score}/100 · <span className={
                                            scoreAudit.zone === 'rouge' ? 'text-rose-700' :
                                            scoreAudit.zone === 'danger' ? 'text-amber-700' : 'text-emerald-700'
                                        }>{zoneInfo?.label}</span>
                                    </h2>
                                    <p className="text-sm text-gray-600">
                                        {scoreAudit.repondues}/{scoreAudit.total} questions répondues
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link to={`/questionnaires-generes/${auditFlash.questionnaire.id}/audit-flash-resultat`}>
                                    <Button as="span" variant="primary">Voir le résultat</Button>
                                </Link>
                                <Link to="/audit-flash">
                                    <Button as="span" variant="secondary">Reprendre</Button>
                                </Link>
                            </div>
                        </div>
                    </Card>
                )}
            </section>
            )}

            {/* Raccourcis */}
            <section className="mb-7">
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-xs font-bold uppercase tracking-wider text-gray-500">Accès rapides</h2>
                </div>
                <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
                    {RACCOURCIS.filter(r => !r.permission || hasPermission(r.permission)).map(r => {
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

            {/* Formulaires a remplir */}
            <Card className="overflow-hidden">
                <div className="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                    <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                            <PencilSquareIcon className="w-4.5 h-4.5 text-white" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-gray-900">Formulaires à remplir</h2>
                            <p className="text-xs text-gray-500">{aRemplir.length} formulaire(s) en attente de réponse</p>
                        </div>
                    </div>
                    <Link to="/mes-formulaires" className="text-sm text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                        Tout voir <ArrowRightIcon className="w-3.5 h-3.5" />
                    </Link>
                </div>
                <div className="p-4">
                    {aRemplir.length === 0 ? (
                        <EmptyState
                            icon={SparklesIcon}
                            title="Tout est à jour"
                            description="Vous n'avez aucun formulaire en attente. ASC vous notifiera dès qu'un nouveau formulaire sera disponible."
                            accent="emerald"
                            compact
                        />
                    ) : (
                        <div className="space-y-2">
                            {aRemplir.slice(0, 5).map(q => {
                                const cfg = STATUT_LABEL[q.statut] || STATUT_LABEL.envoye;
                                const total = (q.questions || []).length;
                                const repondues = (q.reponses || []).filter(r => r.repondu).length;
                                const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;
                                return (
                                    <Link
                                        key={q.id}
                                        to={`/questionnaires-generes/${q.id}`}
                                        className="group block p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-all"
                                    >
                                        <div className="flex items-start justify-between gap-3 mb-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-gray-900 group-hover:text-blue-700 transition-colors truncate">{q.titre}</p>
                                                <p className="text-xs text-gray-500 mt-0.5">
                                                    {q.mission?.reference ? `${q.mission.reference} — ` : ''}{q.pole}
                                                </p>
                                            </div>
                                            <Badge variant={cfg.variant} size="sm" dot>{cfg.label}</Badge>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                                <div className={`h-full ${pct === 100 ? 'bg-emerald-500' : 'bg-blue-500'} transition-all`} style={{ width: `${pct}%` }} />
                                            </div>
                                            <span className="text-xs font-semibold text-gray-600 tabular-nums">{repondues}/{total}</span>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            </Card>
        </div>
    );
}
