/**
 * AuditFlashResultat — Page de restitution du questionnaire Audit Flash
 * (Methode 3). Affiche le score, la zone de risque et le detail des
 * alertes par domaine. Accessible au client et aux agents AS Consulting.
 */

import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getAuditFlashResultat } from '@/api/questionnaires';
import { alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ArrowLeftIcon, ShieldCheckIcon, ExclamationTriangleIcon, FireIcon,
    CheckCircleIcon, XCircleIcon, QuestionMarkCircleIcon, PrinterIcon,
} from '@heroicons/react/24/outline';

const ZONE_CFG = {
    conforme: {
        label: 'Conforme',
        bg: 'from-emerald-50 to-green-50',
        border: 'border-emerald-300',
        text: 'text-emerald-800',
        accent: 'bg-emerald-600',
        icon: ShieldCheckIcon,
        ring: 'ring-emerald-500/40',
    },
    danger: {
        label: 'Zone de danger',
        bg: 'from-amber-50 to-orange-50',
        border: 'border-amber-300',
        text: 'text-amber-800',
        accent: 'bg-amber-600',
        icon: ExclamationTriangleIcon,
        ring: 'ring-amber-500/40',
    },
    rouge: {
        label: 'Zone rouge — urgence absolue',
        bg: 'from-rose-50 to-red-100',
        border: 'border-rose-400',
        text: 'text-rose-900',
        accent: 'bg-rose-700',
        icon: FireIcon,
        ring: 'ring-rose-600/40',
    },
};

const STATUT_REPONSE = {
    conforme: { label: 'Oui — mesure opérationnelle', icon: CheckCircleIcon, color: 'text-emerald-700', badge: 'success' },
    alerte: { label: 'Non — mesure absente', icon: XCircleIcon, color: 'text-rose-700', badge: 'danger' },
    a_verifier: { label: 'Je ne sais pas — à vérifier', icon: QuestionMarkCircleIcon, color: 'text-amber-700', badge: 'warning' },
    sans_reponse: { label: 'Non répondu', icon: QuestionMarkCircleIcon, color: 'text-gray-500', badge: 'gray' },
};

export default function AuditFlashResultat() {
    const { qid } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        getAuditFlashResultat(qid)
            .then(setData)
            .catch(e => alertError(e.response?.data?.message || 'Impossible de charger le résultat'))
            .finally(() => setLoading(false));
    }, [qid]);

    if (loading) return <div className="p-8 text-sm text-gray-500">Chargement du résultat...</div>;
    if (!data) return <div className="p-8 text-sm text-red-600">Résultat indisponible.</div>;

    const { questionnaire, resultat } = data;
    const zone = ZONE_CFG[resultat.zone] || ZONE_CFG.danger;
    const ZoneIcon = zone.icon;
    const pct = resultat.score_max > 0 ? Math.round((resultat.score_total / resultat.score_max) * 100) : 0;

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto print:p-0">
            <div className="flex items-center justify-between mb-3 print:hidden">
                <Button variant="ghost" onClick={() => navigate(-1)}>
                    <ArrowLeftIcon className="w-4 h-4" /> Retour
                </Button>
                <Button variant="secondary" onClick={() => window.print()}>
                    <PrinterIcon className="w-4 h-4" /> Imprimer
                </Button>
            </div>

            <PageHeader
                title={`Résultat — ${questionnaire.titre}`}
                subtitle={`Mission ${questionnaire.mission?.reference || ''} · ${questionnaire.client?.raison_sociale || ''}`}
            >
                <Badge variant="danger">Méthode 3 - Audit Flash</Badge>
            </PageHeader>

            <Card className={`relative overflow-hidden border-2 ${zone.border} bg-gradient-to-br ${zone.bg} p-6 mb-6`}>
                <div className="flex items-start gap-4">
                    <div className={`p-3 rounded-full ${zone.accent} text-white ring-4 ${zone.ring}`}>
                        <ZoneIcon className="w-8 h-8" />
                    </div>
                    <div className="flex-1">
                        <p className="text-xs uppercase tracking-wider font-semibold text-gray-600">Zone de risque</p>
                        <h2 className={`text-3xl font-bold ${zone.text}`}>{zone.label}</h2>
                        <p className="mt-2 text-sm text-gray-700 max-w-2xl">{resultat.zone_message}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-xs uppercase tracking-wider font-semibold text-gray-600">Score</p>
                        <p className={`text-5xl font-extrabold ${zone.text}`}>{resultat.score_total}</p>
                        <p className="text-xs text-gray-500">/ {resultat.score_max} points</p>
                    </div>
                </div>
                <div className="mt-5 w-full bg-white/60 rounded-full h-3 overflow-hidden">
                    <div className={`h-full ${zone.accent} transition-all`} style={{ width: `${pct}%` }} />
                </div>
            </Card>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                <Card className="p-4">
                    <p className="text-xs text-gray-500">Questions</p>
                    <p className="text-2xl font-bold text-gray-900">{resultat.total_questions}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-xs text-gray-500">Répondues</p>
                    <p className="text-2xl font-bold text-blue-700">{resultat.repondues}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-xs text-gray-500">Alertes</p>
                    <p className="text-2xl font-bold text-rose-700">{resultat.alertes_count}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-xs text-gray-500">Rempli par</p>
                    <p className="text-sm font-semibold text-gray-900 truncate">
                        {questionnaire.rempli_par ? `${questionnaire.rempli_par.prenom} ${questionnaire.rempli_par.nom}` : '—'}
                    </p>
                    <p className="text-xs text-gray-500">
                        {questionnaire.rempli_a ? new Date(questionnaire.rempli_a).toLocaleString('fr-FR') : 'Non finalisé'}
                    </p>
                </Card>
            </div>

            {resultat.alertes_count > 0 && (
                <Card className="p-5 mb-6 border-2 border-rose-200 bg-rose-50/40">
                    <h3 className="font-bold text-rose-900 text-sm uppercase tracking-wide mb-3 flex items-center gap-2">
                        <ExclamationTriangleIcon className="w-5 h-5" />
                        Alertes prioritaires ({resultat.alertes_count})
                    </h3>
                    <div className="space-y-2">
                        {resultat.alertes.map(a => (
                            <div key={a.numero} className="flex items-start gap-3 p-3 bg-white rounded-lg border border-rose-100">
                                <Badge variant={a.statut === 'alerte' ? 'danger' : 'warning'}>
                                    {a.statut === 'alerte' ? 'NON' : 'NSP'}
                                </Badge>
                                <div className="flex-1">
                                    <p className="text-sm font-semibold text-gray-900">
                                        #{a.numero} — {a.domaine}
                                    </p>
                                    {a.enjeu && <p className="text-xs text-gray-700 mt-1">{a.enjeu}</p>}
                                    {a.source_legale && (
                                        <p className="text-xs text-rose-700 mt-0.5 font-medium">{a.source_legale}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wide mb-3">
                Détail par question
            </h3>
            <div className="space-y-2">
                {resultat.details.map(d => {
                    const cfg = STATUT_REPONSE[d.statut] || STATUT_REPONSE.sans_reponse;
                    const Icon = cfg.icon;
                    return (
                        <Card key={d.numero} className="p-4">
                            <div className="flex items-start gap-3">
                                <div className="w-8 h-8 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    {d.numero}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap mb-1">
                                        {d.domaine && (
                                            <span className="text-xs uppercase tracking-wide font-semibold text-gray-700">
                                                {d.domaine}
                                            </span>
                                        )}
                                        <Badge variant={cfg.badge}>{cfg.label}</Badge>
                                        <span className="text-xs text-gray-500">+{d.score} pt{d.score > 1 ? 's' : ''}</span>
                                    </div>
                                    <p className="text-sm text-gray-800">{d.texte}</p>
                                    {d.enjeu && (
                                        <p className="text-xs text-gray-600 mt-1 italic">Enjeu : {d.enjeu}</p>
                                    )}
                                    {d.source_legale && (
                                        <p className="text-xs text-gray-500 mt-0.5">{d.source_legale}</p>
                                    )}
                                </div>
                                <Icon className={`w-6 h-6 flex-shrink-0 ${cfg.color}`} />
                            </div>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
