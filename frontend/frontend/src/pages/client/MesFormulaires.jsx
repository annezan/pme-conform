/**
 * MesFormulaires — Espace client : liste des formulaires (questionnaires)
 * lies aux missions du client. Le client peut consulter, remplir, editer et
 * exporter en PDF chaque formulaire. La SUPPRESSION est reservee aux roles
 * ASC (consultant/manager/admin via la permission view-all-questionnaires).
 */

import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { listMesFormulaires, supprimerQuestionnaire, exporterQuestionnairePdf } from '@/api/questionnaires';
import { useAuth } from '@/contexts/AuthContext';
import { alertSuccess, alertError, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ClipboardDocumentListIcon, PencilSquareIcon, TrashIcon, EyeIcon,
    SparklesIcon, ChartBarIcon, ArrowDownTrayIcon,
} from '@heroicons/react/24/outline';

const STATUT = {
    brouillon: { label: 'À remplir', variant: 'gray' },
    envoye: { label: 'Envoyé', variant: 'info' },
    rempli: { label: 'Rempli', variant: 'warning' },
    valide: { label: 'Validé', variant: 'success' },
};

export default function MesFormulaires() {
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    const peutSupprimer = hasPermission('view-all-questionnaires');
    const [questionnaires, setQuestionnaires] = useState([]);
    const [loading, setLoading] = useState(true);
    const [exportingId, setExportingId] = useState(null);

    const exporterPdf = async (q) => {
        setExportingId(q.id);
        try {
            const slug = (q.titre || `questionnaire_${q.id}`).replace(/[^A-Za-z0-9_-]+/g, '_');
            await exporterQuestionnairePdf(q.id, `${slug}_q${q.id}.pdf`);
        } catch (err) {
            alertError(err.response?.data?.message || 'Export PDF impossible');
        } finally {
            setExportingId(null);
        }
    };

    const charger = async () => {
        setLoading(true);
        try {
            const r = await listMesFormulaires();
            setQuestionnaires(r.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de charger les formulaires');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, []);

    const supprimer = async (q) => {
        if (!(await confirmDelete(q.titre))) return;
        try {
            await supprimerQuestionnaire(q.id);
            alertSuccess('Formulaire supprimé');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Suppression refusée');
        }
    };

    // Groupement par mission pour aider a se reperer
    const groupes = useMemo(() => {
        const map = new Map();
        for (const q of questionnaires) {
            const key = q.mission?.id ?? '_';
            if (!map.has(key)) map.set(key, { mission: q.mission, items: [] });
            map.get(key).items.push(q);
        }
        return Array.from(map.values());
    }, [questionnaires]);

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <PageHeader
                title="Mes formulaires"
                subtitle="Formulaires liés à vos missions de conformité. Vous pouvez les remplir, les modifier ou les supprimer."
                eyebrow="Espace client"
                icon={ClipboardDocumentListIcon}
                accent="emerald"
            />

            {loading && <p className="text-sm text-gray-500">Chargement...</p>}

            {!loading && questionnaires.length === 0 && (
                <Card className="p-10 text-center">
                    <SparklesIcon className="w-12 h-12 text-purple-300 mx-auto mb-3" />
                    <p className="text-gray-700">
                        Aucun formulaire pour le moment. Lorsque AS Consulting crée une mission à votre nom,
                        un formulaire de collecte est mis à votre disposition ici.
                    </p>
                </Card>
            )}

            {groupes.map(({ mission, items }) => (
                <section key={mission?.id ?? 'sans-mission'} className="mb-6">
                    <div className="mb-2 flex items-center gap-2">
                        <ClipboardDocumentListIcon className="w-4 h-4 text-blue-600" />
                        <h2 className="text-sm font-bold uppercase tracking-wide text-gray-700">
                            {mission ? `${mission.reference} — ${mission.titre}` : 'Formulaires'}
                        </h2>
                        {mission?.methode && (
                            <Badge variant={mission.methode === 'methode_2' ? 'purple' : mission.methode === 'methode_3' ? 'danger' : 'gray'}>
                                {mission.methode === 'methode_2'
                                    ? 'Méthode 2 - IA'
                                    : mission.methode === 'methode_3'
                                        ? 'Méthode 3 - Audit Flash'
                                        : 'Méthode 1 - Classique'}
                            </Badge>
                        )}
                    </div>

                    <div className="space-y-2">
                        {items.map(q => {
                            const cfg = STATUT[q.statut] || STATUT.brouillon;
                            const total = (q.questions || []).length;
                            const repondues = (q.reponses || []).filter(r => r.repondu).length;
                            const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;

                            return (
                                <Card key={q.id} className="p-4 hover:shadow transition-shadow">
                                    <div className="flex items-start justify-between gap-4 flex-wrap">
                                        <div className="flex-1 min-w-[220px]">
                                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                                                <p className="font-semibold text-gray-900">{q.titre}</p>
                                                <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                <Badge variant={q.source === 'ia' ? 'purple' : 'gray'}>
                                                    {q.source === 'ia' ? 'Généré par IA' : 'Conçu par AS Consulting'}
                                                </Badge>
                                                {q.service && <span className="text-xs text-gray-500">{q.service}</span>}
                                            </div>
                                            {q.description && (
                                                <p className="text-xs text-gray-600 mt-1 mb-2">{q.description}</p>
                                            )}
                                            <div className="flex items-center gap-3 text-xs text-gray-500 mt-2 flex-wrap">
                                                <span>{total} question(s)</span>
                                                <span>·</span>
                                                <span>{repondues} répondue(s)</span>
                                                {total > 0 && (
                                                    <>
                                                        <span>·</span>
                                                        <span className={pct === 100 ? 'text-emerald-700 font-semibold' : 'text-blue-700 font-semibold'}>
                                                            {pct} %
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {q.mission?.methode === 'methode_3' && (q.statut === 'rempli' || q.statut === 'valide' || repondues > 0) && (
                                                <Button variant="success" onClick={() => navigate(`/questionnaires-generes/${q.id}/audit-flash-resultat`)}>
                                                    <ChartBarIcon className="w-4 h-4" /> Voir le résultat
                                                </Button>
                                            )}
                                            <Button variant="secondary" onClick={() => navigate(`/questionnaires-generes/${q.id}`)}>
                                                {total === 0 ? <><EyeIcon className="w-4 h-4" /> Consulter</> : <><PencilSquareIcon className="w-4 h-4" /> Remplir / éditer</>}
                                            </Button>
                                            <Button variant="secondary" size="sm" onClick={() => exporterPdf(q)} disabled={exportingId === q.id} title="Exporter en PDF">
                                                <ArrowDownTrayIcon className="w-4 h-4" />
                                                {exportingId === q.id ? '...' : 'PDF'}
                                            </Button>
                                            {peutSupprimer && (
                                                <Button variant="danger" onClick={() => supprimer(q)} title="Supprimer ce formulaire (réservé ASC)">
                                                    <TrashIcon className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}
