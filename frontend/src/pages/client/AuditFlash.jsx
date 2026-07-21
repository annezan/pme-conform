/**
 * AuditFlash — Page client en self-service. Affiche (et cree au besoin)
 * le questionnaire Audit Flash de l'utilisateur, sans le rattacher a
 * une mission. Le client peut remplir / consulter / reinitialiser ;
 * l'admin AS Consulting accede aux memes resultats via un autre menu.
 */

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { chargerAuditFlashClient, reinitialiserAuditFlashClient } from '@/api/auditFlash';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    BoltIcon, ChartBarIcon, PencilSquareIcon, ArrowPathIcon,
    ShieldExclamationIcon, CheckCircleIcon,
} from '@heroicons/react/24/outline';

const STATUT = {
    brouillon: { label: 'À démarrer', variant: 'gray' },
    envoye: { label: 'À remplir', variant: 'info' },
    rempli: { label: 'Rempli', variant: 'success' },
    valide: { label: 'Validé', variant: 'success' },
};

export default function AuditFlash() {
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [resetting, setResetting] = useState(false);

    const charger = async () => {
        setLoading(true);
        try {
            const r = await chargerAuditFlashClient();
            setData(r);
        } catch (e) {
            alertError(e.response?.data?.message || 'Impossible de charger l\'Audit Flash');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, []);

    const reinitialiser = async () => {
        if (!(await confirmAction('Réinitialiser votre Audit Flash ? Vos réponses actuelles seront supprimées.', 'Réinitialisation'))) return;
        setResetting(true);
        try {
            await reinitialiserAuditFlashClient();
            alertSuccess('Audit Flash réinitialisé');
            await charger();
        } catch (e) {
            alertError(e.response?.data?.message || 'Erreur');
        } finally {
            setResetting(false);
        }
    };

    if (loading) return <div className="p-8 text-sm text-gray-500">Chargement de l'Audit Flash...</div>;
    if (!data?.questionnaire) return <div className="p-8 text-sm text-red-600">Audit Flash indisponible.</div>;

    const q = data.questionnaire;
    const cfg = STATUT[q.statut] || STATUT.envoye;
    const total = (q.questions || []).length;
    const repondues = (q.reponses || []).filter(r => r.repondu).length;
    const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;
    const aDesReponses = repondues > 0;

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Audit Flash"
                subtitle="Scan pénal du dirigeant — Auto-évaluation rapide de votre exposition aux risques RGPD / Loi 2013-450 / RGSSI."
            >
                <Badge variant="danger">Méthode 3 - Audit Flash</Badge>
            </PageHeader>

            <Card className="p-6 mb-6 border-2 border-rose-200 bg-gradient-to-br from-rose-50 to-red-50/50">
                <div className="flex items-start gap-4">
                    <div className="p-3 rounded-full bg-rose-600 text-white">
                        <BoltIcon className="w-7 h-7" />
                    </div>
                    <div className="flex-1">
                        <h2 className="text-xl font-bold text-rose-900 mb-1">{q.titre}</h2>
                        <p className="text-sm text-gray-700">{q.description}</p>
                        <div className="flex items-center gap-3 mt-3 flex-wrap">
                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                            <span className="text-xs text-gray-600">{total} question(s)</span>
                            <span className="text-xs text-gray-500">·</span>
                            <span className="text-xs text-gray-600">{repondues} répondue(s)</span>
                            {total > 0 && (
                                <>
                                    <span className="text-xs text-gray-500">·</span>
                                    <span className={`text-xs font-semibold ${pct === 100 ? 'text-emerald-700' : 'text-blue-700'}`}>{pct} %</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2 mt-5">
                    <Button onClick={() => navigate(`/questionnaires-generes/${q.id}`)}>
                        <PencilSquareIcon className="w-4 h-4" />
                        {aDesReponses ? 'Continuer / éditer' : 'Démarrer l\'Audit Flash'}
                    </Button>
                    {aDesReponses && (
                        <Button variant="success" onClick={() => navigate(`/questionnaires-generes/${q.id}/audit-flash-resultat`)}>
                            <ChartBarIcon className="w-4 h-4" /> Voir le résultat
                        </Button>
                    )}
                    {aDesReponses && (
                        <Button variant="secondary" onClick={reinitialiser} disabled={resetting}>
                            <ArrowPathIcon className="w-4 h-4" />
                            {resetting ? 'Réinitialisation...' : 'Réinitialiser'}
                        </Button>
                    )}
                </div>
            </Card>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                <Card className="p-4 flex items-start gap-3">
                    <ShieldExclamationIcon className="w-6 h-6 text-rose-600 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-gray-900">Indépendant des missions</p>
                        <p className="text-xs text-gray-600 mt-0.5">Cet Audit Flash vous est propre. Il n'est rattaché à aucune mission ; ASC peut néanmoins consulter le résultat.</p>
                    </div>
                </Card>
                <Card className="p-4 flex items-start gap-3">
                    <CheckCircleIcon className="w-6 h-6 text-emerald-600 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-gray-900">Réponses confidentielles</p>
                        <p className="text-xs text-gray-600 mt-0.5">Oui = 0 pt. Non / Je ne sais pas = +10 pts. Score 0-10 conforme, 20-40 zone de danger, 50-100 zone rouge.</p>
                    </div>
                </Card>
                <Card className="p-4 flex items-start gap-3">
                    <ChartBarIcon className="w-6 h-6 text-blue-600 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-gray-900">Restitution immédiate</p>
                        <p className="text-xs text-gray-600 mt-0.5">Une fois finalisé, le score et les alertes prioritaires s'affichent automatiquement.</p>
                    </div>
                </Card>
            </div>
        </div>
    );
}
