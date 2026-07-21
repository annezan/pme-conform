/**
 * QuestionnairesGeneres — Liste des questionnaires generes par l'IA
 * pour une mission Methode 2 (apres figeage de l'organigramme).
 *
 * Permet :
 *  - Regeneration IA ciblee par questionnaire (bouton "Régénérer" par ligne)
 *  - Regeneration en lot de tous les questionnaires non-publies de la mission
 *  - Indicateur d'etat par ligne (polling toutes les 5s tant qu'au moins un
 *    job est actif)
 *
 * Les questionnaires DEJA PUBLIES ne peuvent pas etre regeneres : le bouton
 * est masque et la regeneration en lot les ignore (avertissement).
 */

import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '@/api/client';
import {
    regenererQuestionnaire, suivreRegenerationQuestionnaire,
    regenererTousLesQuestionnaires,
    publierTousLesQuestionnaires, depublierTousLesQuestionnaires,
    supprimerQuestionnaire,
} from '@/api/questionnaires';
import { alertError, alertSuccess, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ArrowLeftIcon, ClipboardDocumentListIcon, SparklesIcon,
    CheckCircleIcon, ExclamationTriangleIcon, PaperAirplaneIcon, EyeSlashIcon, TrashIcon,
} from '@heroicons/react/24/outline';

const STATUT = {
    brouillon: { label: 'Brouillon', variant: 'gray' },
    envoye: { label: 'Envoyé', variant: 'info' },
    rempli: { label: 'Rempli', variant: 'warning' },
    valide: { label: 'Validé', variant: 'success' },
};

export default function QuestionnairesGeneres() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [questionnaires, setQuestionnaires] = useState([]);
    const [loading, setLoading] = useState(true);
    const [progressById, setProgressById] = useState({}); // { [qid]: {etat, ...} }
    const [actionInProgress, setActionInProgress] = useState(false);
    const pollingRef = useRef(null);

    const charger = async () => {
        try {
            const r = await api.get(`/missions/${id}/questionnaires`);
            const list = r.data.data || [];
            setQuestionnaires(list);
            // Au chargement, on regarde si certains questionnaires ont deja un job en cours
            await syncTousLesProgress(list);
        } catch {
            alertError('Impossible de charger les questionnaires');
        } finally {
            setLoading(false);
        }
    };

    const syncTousLesProgress = async (list) => {
        const cibles = list.filter(q => !q.est_publie);
        if (cibles.length === 0) return;
        const results = await Promise.allSettled(cibles.map(q => suivreRegenerationQuestionnaire(q.id)));
        const next = {};
        results.forEach((res, i) => {
            if (res.status === 'fulfilled' && res.value.progress) {
                next[cibles[i].id] = res.value.progress;
            }
        });
        setProgressById(prev => ({ ...prev, ...next }));
        if (Object.values(next).some(p => ['en_file', 'en_cours'].includes(p.etat))) {
            demarrerPolling();
        }
    };

    const arreterPolling = () => {
        if (pollingRef.current) { clearInterval(pollingRef.current); pollingRef.current = null; }
    };

    const demarrerPolling = () => {
        arreterPolling();
        pollingRef.current = setInterval(async () => {
            const actifs = Object.entries(progressById)
                .filter(([, p]) => p && ['en_file', 'en_cours'].includes(p.etat))
                .map(([qid]) => Number(qid));
            // Snapshot a la volee : on retape sur l'ensemble des non-publies pour capter aussi les "termine" qui apparaitraient
            const aPoll = questionnaires.filter(q => !q.est_publie).map(q => q.id);
            if (aPoll.length === 0) { arreterPolling(); return; }

            const results = await Promise.allSettled(aPoll.map(qid => suivreRegenerationQuestionnaire(qid)));
            const next = {};
            let aChange = false;
            results.forEach((res, i) => {
                if (res.status === 'fulfilled') {
                    const prog = res.value.progress;
                    next[aPoll[i]] = prog;
                    const ancien = progressById[aPoll[i]];
                    if (ancien?.etat !== 'termine' && prog?.etat === 'termine') aChange = true;
                }
            });
            setProgressById(prev => ({ ...prev, ...next }));

            const encoreActifs = Object.values(next).some(p => p && ['en_file', 'en_cours'].includes(p.etat));
            if (!encoreActifs) {
                arreterPolling();
                if (aChange) {
                    alertSuccess('Régénération terminée.');
                    // Recharge pour avoir les questions/themes mis a jour
                    api.get(`/missions/${id}/questionnaires`).then(r => setQuestionnaires(r.data.data || [])).catch(() => {});
                }
            }
        }, 5000);
    };

    const supprimer = async (q) => {
        if (!(await confirmDelete(q.titre))) return;
        try {
            await supprimerQuestionnaire(q.id);
            alertSuccess('Questionnaire supprimé.');
            setQuestionnaires(prev => prev.filter(x => x.id !== q.id));
            setProgressById(prev => { const n = { ...prev }; delete n[q.id]; return n; });
        } catch (err) {
            alertError(err.response?.data?.message || 'Suppression refusée');
        }
    };

    const regenererUn = async (q) => {
        const confirmed = await confirmAction(
            `Régénérer le questionnaire « ${q.titre} » avec l'IA ? Les questions actuelles seront REMPLACÉES.`,
            'Régénération IA',
        );
        if (!confirmed) return;
        try {
            const r = await regenererQuestionnaire(q.id);
            setProgressById(prev => ({ ...prev, [q.id]: r.progress }));
            demarrerPolling();
        } catch (err) {
            if (err.response?.status === 409 && err.response?.data?.progress) {
                setProgressById(prev => ({ ...prev, [q.id]: err.response.data.progress }));
                demarrerPolling();
                alertError(err.response.data.message);
            } else {
                alertError(err.response?.data?.message || 'Erreur regeneration');
            }
        }
    };

    const publierTous = async () => {
        const nonPublies = questionnaires.filter(q => !q.est_publie);
        const sansQuestions = nonPublies.filter(q => (q.questions || []).length === 0).length;
        const publiables = nonPublies.length - sansQuestions;
        if (publiables === 0) {
            alertError("Aucun questionnaire publiable : tous sont déjà publiés ou vides de question.");
            return;
        }
        if (!(await confirmAction(
            `Publier ${publiables} questionnaire(s) ?${sansQuestions > 0 ? ` (${sansQuestions} sans question seront ignoré(s).)` : ''} Les utilisateurs concernés recevront un e-mail de notification.`,
            'Publier tous les questionnaires',
        ))) return;
        setActionInProgress(true);
        try {
            const r = await publierTousLesQuestionnaires(id);
            alertSuccess(r.message);
            const lr = await api.get(`/missions/${id}/questionnaires`);
            setQuestionnaires(lr.data.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Publication refusée');
        } finally {
            setActionInProgress(false);
        }
    };

    const depublierTous = async () => {
        const publies = questionnaires.filter(q => q.est_publie).length;
        if (publies === 0) {
            alertError("Aucun questionnaire à dépublier.");
            return;
        }
        if (!(await confirmAction(
            `Dépublier les ${publies} questionnaire(s) publié(s) ? Ils ne seront plus visibles côté client (les réponses déjà saisies restent conservées).`,
            'Dépublier tous',
        ))) return;
        setActionInProgress(true);
        try {
            const r = await depublierTousLesQuestionnaires(id);
            alertSuccess(r.message);
            const lr = await api.get(`/missions/${id}/questionnaires`);
            setQuestionnaires(lr.data.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Dépublication refusée');
        } finally {
            setActionInProgress(false);
        }
    };

    const regenererTous = async () => {
        const nonPublies = questionnaires.filter(q => !q.est_publie).length;
        const publies = questionnaires.length - nonPublies;
        if (nonPublies === 0) {
            alertError('Aucun questionnaire à régénérer : tous sont publiés.');
            return;
        }
        const confirmed = await confirmAction(
            `Régénérer ${nonPublies} questionnaire(s) avec l'IA ? ${publies > 0 ? `(${publies} déjà publié(s) seront ignoré(s).)` : ''} Toutes les questions actuelles seront remplacées.`,
            'Régénération en lot',
        );
        if (!confirmed) return;
        setActionInProgress(true);
        try {
            const r = await regenererTousLesQuestionnaires(id);
            alertSuccess(r.message);
            // Marque immediatement tous les eligibles comme "en_file" pour feedback instantane
            const next = {};
            questionnaires.filter(q => !q.est_publie).forEach(q => {
                next[q.id] = { etat: 'en_file', enqueue_at: new Date().toISOString() };
            });
            setProgressById(prev => ({ ...prev, ...next }));
            demarrerPolling();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur regeneration en lot');
        } finally {
            setActionInProgress(false);
        }
    };

    useEffect(() => { charger(); return () => arreterPolling(); }, [id]);

    const groupes = questionnaires.reduce((acc, q) => {
        if (!acc[q.pole]) acc[q.pole] = [];
        acc[q.pole].push(q);
        return acc;
    }, {});

    const nbRegenerables = questionnaires.filter(q => !q.est_publie).length;
    const nbEnCours = Object.values(progressById).filter(p => p && ['en_file', 'en_cours'].includes(p.etat)).length;
    const nbPublies = questionnaires.filter(q => q.est_publie).length;
    const tousPublies = questionnaires.length > 0 && nbPublies === questionnaires.length;

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <Button variant="ghost" onClick={() => navigate(`/missions/${id}`)} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour mission
            </Button>
            <PageHeader
                title="Questionnaires générés par IA"
                subtitle="Méthode 2 - Étape 3 : remplir les questionnaires d'audit produits depuis l'organigramme"
                eyebrow="IA dynamique · Étape 3/3"
                icon={SparklesIcon}
                accent="indigo"
            >
                {questionnaires.length > 0 && (
                    <div className="flex items-center gap-2 flex-wrap">
                        {tousPublies ? (
                            <Button
                                variant="warning"
                                onClick={depublierTous}
                                disabled={actionInProgress}
                                title="Dépublier tous les questionnaires de cette mission"
                            >
                                <EyeSlashIcon className="w-4 h-4" />
                                Dépublier ({nbPublies})
                            </Button>
                        ) : (
                            <Button
                                variant="success"
                                onClick={publierTous}
                                disabled={actionInProgress || nbRegenerables === 0}
                                title={nbRegenerables === 0 ? 'Tous sont déjà publiés' : 'Publier tous les questionnaires non publiés de cette mission'}
                            >
                                <PaperAirplaneIcon className="w-4 h-4" />
                                Publier ({nbRegenerables})
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            onClick={regenererTous}
                            disabled={actionInProgress || nbRegenerables === 0 || nbEnCours > 0}
                            title={nbRegenerables === 0 ? 'Tous les questionnaires sont publiés' : ''}
                        >
                            <SparklesIcon className="w-4 h-4" />
                            Tout régénérer{nbRegenerables > 0 ? ` (${nbRegenerables})` : ''}
                        </Button>
                    </div>
                )}
            </PageHeader>

            {nbEnCours > 0 && (
                <Card className="p-3 mb-4 border-l-4 border-l-indigo-500 bg-indigo-50/40">
                    <p className="text-sm text-indigo-900 flex items-center gap-2">
                        <SparklesIcon className="w-4 h-4 animate-pulse text-indigo-600" />
                        <strong>{nbEnCours}</strong> régénération(s) en cours — la liste se rafraîchira automatiquement.
                    </p>
                </Card>
            )}

            {loading && <p className="text-sm text-gray-500">Chargement...</p>}

            {!loading && questionnaires.length === 0 && (
                <Card className="p-10 text-center">
                    <SparklesIcon className="w-12 h-12 text-purple-300 mx-auto mb-3" />
                    <p className="text-gray-700">Aucun questionnaire généré. <button onClick={() => navigate(`/missions/${id}/organigramme`)} className="text-blue-600 underline">Figer l'organigramme</button> pour déclencher la génération IA.</p>
                </Card>
            )}

            {Object.entries(groupes).map(([pole, qs]) => (
                <div key={pole} className="mb-5">
                    <h2 className="font-bold text-gray-900 text-sm uppercase tracking-wide mb-2 flex items-center gap-2">
                        <ClipboardDocumentListIcon className="w-4 h-4 text-blue-600" />
                        {pole}
                    </h2>
                    <div className="space-y-2">
                        {qs.map(q => {
                            const cfg = STATUT[q.statut] || STATUT.brouillon;
                            const reponses = q.reponses || [];
                            const repondues = reponses.filter(r => r.repondu).length;
                            const total = (q.questions || []).length;
                            const prog = progressById[q.id];
                            return (
                                <Card key={q.id} className="p-4 hover:shadow transition-shadow">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1 cursor-pointer" onClick={() => navigate(`/asc/questionnaires/${q.id}`)}>
                                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                                                <p className="font-semibold text-gray-900">{q.titre}</p>
                                                <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                {q.est_publie ? (
                                                    <Badge variant="success" dot>Publié</Badge>
                                                ) : (
                                                    <Badge variant="warning">Non publié</Badge>
                                                )}
                                                <Badge variant={q.source === 'ia' ? 'purple' : 'gray'}>{q.source === 'ia' ? 'IA' : 'Générique'}</Badge>
                                                {q.service && <span className="text-xs text-gray-500">{q.service}</span>}
                                            </div>
                                            {q.description && <p className="text-xs text-gray-600 mt-1">{q.description}</p>}
                                            <div className="flex items-center gap-3 text-xs text-gray-500 mt-2 flex-wrap">
                                                <span>{total} questions</span>
                                                <span>·</span>
                                                <span>{repondues}/{total} répondues</span>
                                                {(q.themes || []).length > 0 && (
                                                    <>
                                                        <span>·</span>
                                                        <span>thèmes : {q.themes.join(', ')}</span>
                                                    </>
                                                )}
                                                {prog && (
                                                    <span className="ml-1">
                                                        {prog.etat === 'en_file' && <Badge variant="info" dot>En file…</Badge>}
                                                        {prog.etat === 'en_cours' && <Badge variant="purple" dot>Régénération IA…</Badge>}
                                                        {prog.etat === 'termine' && (
                                                            <Badge variant="success">
                                                                <CheckCircleIcon className="w-3 h-3" /> Régénéré
                                                            </Badge>
                                                        )}
                                                        {prog.etat === 'erreur' && (
                                                            <Badge variant="danger" title={prog.message}>
                                                                <ExclamationTriangleIcon className="w-3 h-3" /> Échec
                                                            </Badge>
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {!q.est_publie && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={(e) => { e.stopPropagation(); regenererUn(q); }}
                                                    disabled={prog && ['en_file', 'en_cours'].includes(prog.etat)}
                                                    title="Re-prompt LLM pour ce pôle/service"
                                                >
                                                    <SparklesIcon className="w-4 h-4" /> Régénérer
                                                </Button>
                                            )}
                                            <Button variant="primary" size="sm" onClick={(e) => { e.stopPropagation(); navigate(`/asc/questionnaires/${q.id}`); }}>
                                                Gérer
                                            </Button>
                                            {!q.est_publie && (
                                                <Button
                                                    variant="danger"
                                                    size="sm"
                                                    onClick={(e) => { e.stopPropagation(); supprimer(q); }}
                                                    title="Supprimer définitivement ce questionnaire"
                                                >
                                                    <TrashIcon className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
