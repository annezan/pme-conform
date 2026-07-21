/**
 * AscQuestionnaireGestion — Page ASC pour reviewer + publier un questionnaire,
 * et gerer ses questions individuelles (ajouter, modifier, supprimer).
 *
 * Acces : utilisateurs avec permission view-all-questionnaires.
 * Route : /asc/questionnaires/:id
 */

import { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    getQuestionnaire, publierQuestionnaire, depublierQuestionnaire,
    ajouterQuestion, modifierQuestion, supprimerQuestion,
    regenererQuestionnaire, suivreRegenerationQuestionnaire,
} from '@/api/questionnaires';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { Input, Select, Textarea } from '@/components/ui/Input';
import {
    ArrowLeftIcon, PaperAirplaneIcon, EyeSlashIcon, PlusIcon,
    PencilSquareIcon, TrashIcon, CheckCircleIcon, ClipboardDocumentListIcon,
    SparklesIcon, ExclamationTriangleIcon, ChatBubbleLeftRightIcon, UserCircleIcon, ClockIcon,
} from '@heroicons/react/24/outline';
import { FinalitesEditor, estQuestionFinalites } from '@/pages/missions/QuestionnaireRemplir';

const QUESTION_INITIAL = {
    texte: '', type: 'texte', options: [], domaine: '', themes: [],
};

const TYPES = [
    { value: 'texte', label: 'Texte libre' },
    { value: 'liste', label: 'Liste de choix' },
    { value: 'oui_non', label: 'Oui / Non' },
    { value: 'nombre', label: 'Nombre' },
];

export default function AscQuestionnaireGestion() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [actionInProgress, setActionInProgress] = useState(false);
    const [showQuestionModal, setShowQuestionModal] = useState(false);
    const [questionForm, setQuestionForm] = useState(QUESTION_INITIAL);
    const [editingQuestionNum, setEditingQuestionNum] = useState(null);
    const [optionsText, setOptionsText] = useState('');
    const [regenProgress, setRegenProgress] = useState(null);
    const pollingRef = useRef(null);

    const charger = async () => {
        setLoading(true);
        try {
            const r = await getQuestionnaire(id);
            setData(r);
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de charger le questionnaire');
        } finally {
            setLoading(false);
        }
    };

    const arreterPolling = () => {
        if (pollingRef.current) { clearInterval(pollingRef.current); pollingRef.current = null; }
    };

    const demarrerPolling = () => {
        arreterPolling();
        pollingRef.current = setInterval(async () => {
            try {
                const r = await suivreRegenerationQuestionnaire(id);
                setRegenProgress(r.progress);
                if (r.progress?.etat === 'termine') {
                    arreterPolling();
                    alertSuccess(`Questionnaire regenere (source: ${r.progress.source}, ${r.progress.nb_questions} questions).`);
                    charger();
                } else if (r.progress?.etat === 'erreur') {
                    arreterPolling();
                    alertError("Regeneration echouee : " + (r.progress.message || 'erreur inconnue'));
                }
            } catch { /* silencieux : on continue */ }
        }, 4000);
    };

    const regenererIA = async () => {
        if (!data?.questionnaire) return;
        const confirmed = await confirmAction(
            "Régénérer ce questionnaire avec l'IA ? Les questions actuelles seront REMPLACÉES et toutes les réponses déjà saisies seront perdues.",
            "Régénération IA"
        );
        if (!confirmed) return;
        setActionInProgress(true);
        try {
            const r = await regenererQuestionnaire(id);
            setRegenProgress(r.progress);
            demarrerPolling();
        } catch (err) {
            if (err.response?.status === 409 && err.response?.data?.progress) {
                // Job deja en cours : on attache le polling sans reset
                setRegenProgress(err.response.data.progress);
                demarrerPolling();
                alertError(err.response.data.message);
            } else {
                alertError(err.response?.data?.message || 'Erreur regeneration');
            }
        } finally {
            setActionInProgress(false);
        }
    };

    useEffect(() => {
        charger();
        // Au montage, on regarde si une regen est deja en cours pour cet id
        suivreRegenerationQuestionnaire(id).then(r => {
            if (r.progress?.etat === 'en_file' || r.progress?.etat === 'en_cours') {
                setRegenProgress(r.progress);
                demarrerPolling();
            }
        }).catch(() => {});
        return () => arreterPolling();
    }, [id]);

    const publier = async () => {
        const confirmed = await confirmAction(
            'Publier ce questionnaire ? Il deviendra visible et accessible aux utilisateurs du pôle concerné.',
            'Publier le questionnaire'
        );
        if (!confirmed) return;
        setActionInProgress(true);
        try {
            await publierQuestionnaire(id);
            alertSuccess('Questionnaire publié. Les utilisateurs concernés peuvent désormais y répondre.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de publier');
        } finally {
            setActionInProgress(false);
        }
    };

    const depublier = async () => {
        const confirmed = await confirmAction(
            'Dépublier ce questionnaire ? Il deviendra invisible aux utilisateurs côté client.',
            'Dépublier'
        );
        if (!confirmed) return;
        setActionInProgress(true);
        try {
            await depublierQuestionnaire(id);
            alertSuccess('Questionnaire dépublié.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Impossible de dépublier');
        } finally {
            setActionInProgress(false);
        }
    };

    const ouvrirAjout = () => {
        setEditingQuestionNum(null);
        setQuestionForm(QUESTION_INITIAL);
        setOptionsText('');
        setShowQuestionModal(true);
    };

    const ouvrirEdition = (q) => {
        setEditingQuestionNum(q.numero);
        setQuestionForm({
            texte: q.texte || '',
            type: q.type || 'texte',
            options: q.options || [],
            domaine: q.domaine || '',
            themes: q.themes || [],
        });
        setOptionsText((q.options || []).join('\n'));
        setShowQuestionModal(true);
    };

    const enregistrerQuestion = async () => {
        const payload = {
            ...questionForm,
            options: optionsText.split('\n').map(s => s.trim()).filter(Boolean),
        };
        try {
            if (editingQuestionNum) {
                await modifierQuestion(id, editingQuestionNum, payload);
                alertSuccess('Question modifiée.');
            } else {
                await ajouterQuestion(id, payload);
                alertSuccess('Question ajoutée.');
            }
            setShowQuestionModal(false);
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de l\'enregistrement');
        }
    };

    const suppQuestion = async (q) => {
        if (!(await confirmDelete(`la question n°${q.numero}`))) return;
        try {
            await supprimerQuestion(id, q.numero);
            alertSuccess('Question supprimée.');
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Suppression impossible');
        }
    };

    if (loading) return <div className="p-8 text-sm text-gray-500">Chargement...</div>;
    if (!data?.questionnaire) return <div className="p-8 text-sm text-red-600">Questionnaire indisponible.</div>;

    const q = data.questionnaire;
    const questions = q.questions || [];

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <div className="flex items-center justify-between mb-3">
                <Button variant="ghost" onClick={() => navigate(-1)}>
                    <ArrowLeftIcon className="w-4 h-4" /> Retour
                </Button>
            </div>

            <PageHeader
                title={q.titre || 'Questionnaire'}
                subtitle={q.description}
                eyebrow="Gestion ASC du questionnaire"
                icon={ClipboardDocumentListIcon}
                accent="indigo"
            >
                <div className="flex items-center gap-2 flex-wrap">
                    {q.pole && <Badge variant="info">Pôle : {q.pole}</Badge>}
                    {q.est_publie ? (
                        <Badge variant="success" dot>
                            <CheckCircleIcon className="w-3 h-3" /> Publié
                        </Badge>
                    ) : (
                        <Badge variant="warning">Non publié</Badge>
                    )}
                    <Badge variant="gray">{q.statut}</Badge>
                </div>
            </PageHeader>

            <Card className="p-5 mb-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="text-sm text-gray-700">
                        {q.est_publie ? (
                            <p>
                                Ce questionnaire est <strong>publié</strong> et visible des utilisateurs du pôle{' '}
                                {q.pole && <strong>{q.pole}</strong>} de l'entreprise cliente.
                                {q.publie_le && (
                                    <span className="text-gray-500"> Publié le {new Date(q.publie_le).toLocaleString('fr-FR')}.</span>
                                )}
                            </p>
                        ) : (
                            <p>
                                Ce questionnaire est <strong>en brouillon</strong>. Revue les questions ci-dessous,
                                puis cliquez sur <strong>Publier</strong> pour le rendre visible aux clients.
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2 shrink-0 flex-wrap">
                        {!q.est_publie && (
                            <Button
                                variant="secondary"
                                onClick={regenererIA}
                                disabled={actionInProgress || (regenProgress && ['en_file','en_cours'].includes(regenProgress.etat))}
                                title="Re-prompt LLM uniquement sur ce pole/service"
                            >
                                <SparklesIcon className="w-4 h-4" /> Régénérer avec l'IA
                            </Button>
                        )}
                        {q.est_publie ? (
                            <Button variant="warning" onClick={depublier} disabled={actionInProgress}>
                                <EyeSlashIcon className="w-4 h-4" /> Dépublier
                            </Button>
                        ) : (
                            <Button variant="success" onClick={publier} disabled={actionInProgress || questions.length === 0}>
                                <PaperAirplaneIcon className="w-4 h-4" />
                                {actionInProgress ? 'Publication...' : 'Publier'}
                            </Button>
                        )}
                    </div>
                </div>
                {!q.est_publie && questions.length === 0 && (
                    <p className="text-xs text-amber-700 mt-2">
                        ⚠️ Vous devez ajouter au moins une question avant de pouvoir publier.
                    </p>
                )}
                {q.est_publie && (
                    <p className="text-xs text-gray-500 mt-2 flex items-center gap-1.5">
                        <ExclamationTriangleIcon className="w-3.5 h-3.5 text-amber-500" />
                        La régénération est désactivée tant que ce questionnaire est publié. Dépubliez-le d'abord si vous souhaitez relancer l'IA.
                    </p>
                )}
            </Card>

            {/* Indicateur de progression de la regeneration IA */}
            {regenProgress && (
                <Card className={`p-4 mb-4 border-l-4 ${
                    regenProgress.etat === 'erreur' ? 'border-l-red-500' :
                    regenProgress.etat === 'termine' ? 'border-l-emerald-500' : 'border-l-indigo-500'
                }`}>
                    {(regenProgress.etat === 'en_file' || regenProgress.etat === 'en_cours') && (
                        <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                <SparklesIcon className="w-5 h-5 animate-pulse" />
                            </div>
                            <div className="flex-1">
                                <p className="font-semibold text-gray-900 text-sm">
                                    {regenProgress.etat === 'en_file' ? "Régénération en file d'attente…" : 'Régénération IA en cours…'}
                                </p>
                                <p className="text-xs text-gray-500">
                                    Le LLM repompose les questions pour le pôle <strong>{q.pole}</strong>{q.service && <> · service <strong>{q.service}</strong></>}. Vous pouvez quitter cette page.
                                </p>
                            </div>
                            <Badge variant="info" dot>Async</Badge>
                        </div>
                    )}
                    {regenProgress.etat === 'termine' && (
                        <div className="flex items-center gap-3">
                            <CheckCircleIcon className="w-6 h-6 text-emerald-600 shrink-0" />
                            <p className="text-sm text-gray-700">
                                Régénération terminée — <strong>{regenProgress.nb_questions}</strong> question(s) ({regenProgress.source === 'ia' ? 'généré par IA' : 'fallback hors-ligne'}).
                            </p>
                        </div>
                    )}
                    {regenProgress.etat === 'erreur' && (
                        <div className="flex items-center gap-3">
                            <ExclamationTriangleIcon className="w-6 h-6 text-red-600 shrink-0" />
                            <div>
                                <p className="font-semibold text-sm text-gray-900">Régénération échouée</p>
                                <p className="text-xs text-red-700 mt-0.5">{regenProgress.message || 'Erreur inconnue'}</p>
                            </div>
                        </div>
                    )}
                </Card>
            )}

            <BlocReponsesClient questionnaire={q} />

            <div className="flex items-center justify-between mb-3">
                <h3 className="font-semibold text-gray-900">Questions ({questions.length})</h3>
                {!q.est_publie && (
                    <Button onClick={ouvrirAjout}>
                        <PlusIcon className="w-4 h-4" /> Ajouter une question
                    </Button>
                )}
            </div>

            <div className="space-y-2">
                {questions.length === 0 && (
                    <Card className="p-8 text-center text-gray-500 text-sm">
                        Aucune question pour le moment. Cliquez sur « Ajouter une question » pour commencer.
                    </Card>
                )}

                {questions.map(qst => (
                    <Card key={qst.numero} className="p-4">
                        <div className="flex items-start gap-3">
                            <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold shrink-0">
                                {qst.numero}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1 flex-wrap">
                                    <Badge variant="gray" size="sm">{qst.type || 'texte'}</Badge>
                                    {qst.domaine && <Badge variant="info" size="sm">{qst.domaine}</Badge>}
                                    {(qst.themes || []).map((t, i) => (
                                        <Badge key={i} variant="purple" size="sm">{t}</Badge>
                                    ))}
                                </div>
                                <p className="text-sm text-gray-800">{qst.texte}</p>
                                {(qst.options || []).length > 0 && (
                                    <div className="mt-1.5 text-xs text-gray-500">
                                        Options : {qst.options.join(' · ')}
                                    </div>
                                )}
                            </div>
                            {!q.est_publie && (
                                <div className="flex items-center gap-1 shrink-0">
                                    <Button variant="secondary" size="sm" onClick={() => ouvrirEdition(qst)}>
                                        <PencilSquareIcon className="w-4 h-4" />
                                    </Button>
                                    <Button variant="danger" size="sm" onClick={() => suppQuestion(qst)}>
                                        <TrashIcon className="w-4 h-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    </Card>
                ))}
            </div>

            <Modal
                open={showQuestionModal}
                onClose={() => setShowQuestionModal(false)}
                title={editingQuestionNum ? `Modifier la question n°${editingQuestionNum}` : 'Ajouter une question'}
                size="md"
            >
                <div className="space-y-3">
                    <Textarea
                        label="Texte de la question"
                        required
                        rows={3}
                        value={questionForm.texte}
                        onChange={e => setQuestionForm(f => ({ ...f, texte: e.target.value }))}
                        placeholder="Ex: Disposez-vous d'une politique de conservation des données ?"
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Select
                            label="Type de réponse"
                            value={questionForm.type}
                            onChange={e => setQuestionForm(f => ({ ...f, type: e.target.value }))}
                        >
                            {TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </Select>
                        <Input
                            label="Domaine (optionnel)"
                            value={questionForm.domaine}
                            onChange={e => setQuestionForm(f => ({ ...f, domaine: e.target.value }))}
                            placeholder="Ex: Finalité, Sécurité..."
                        />
                    </div>
                    {questionForm.type === 'liste' && (
                        <Textarea
                            label="Options (une par ligne)"
                            rows={3}
                            value={optionsText}
                            onChange={e => setOptionsText(e.target.value)}
                            placeholder={"Option 1\nOption 2\nOption 3"}
                        />
                    )}
                </div>

                <div className="flex justify-end gap-2 mt-5 pt-3 border-t border-gray-100">
                    <Button variant="ghost" onClick={() => setShowQuestionModal(false)}>Annuler</Button>
                    <Button onClick={enregistrerQuestion}>
                        {editingQuestionNum ? 'Enregistrer' : 'Ajouter'}
                    </Button>
                </div>
            </Modal>
        </div>
    );
}

/**
 * BlocReponsesClient — Section read-only pour ASC : affiche les reponses
 * deja saisies par le client en regard de chaque question, avec l'identite
 * du repondeur + la date de remplissage.
 *
 * Le bloc se masque automatiquement si aucune reponse n'a ete enregistree
 * (statut "brouillon" ou "envoye" cote client).
 */
function BlocReponsesClient({ questionnaire }) {
    const questions = questionnaire?.questions || [];
    const reponses = questionnaire?.reponses || [];

    // Map { numero -> reponse } pour lookup rapide
    const repIndex = new Map(reponses.map(r => [Number(r.numero), r]));

    const repondues = questions.filter(qu => {
        const r = repIndex.get(Number(qu.numero));
        return r && String(r.reponse ?? '').trim() !== '';
    }).length;
    const total = questions.length;
    const pct = total > 0 ? Math.round((repondues / total) * 100) : 0;

    // Pas de bloc si aucune reponse n'a encore ete saisie
    if (repondues === 0) {
        return (
            <Card className="p-4 mb-4 bg-gray-50 border-gray-200">
                <div className="flex items-center gap-2 text-sm text-gray-500">
                    <ChatBubbleLeftRightIcon className="w-4 h-4 text-gray-400" />
                    Aucune réponse n'a encore été saisie par le client.
                </div>
            </Card>
        );
    }

    const repondeur = questionnaire?.repondeur;
    const remplilA = questionnaire?.rempli_a;

    return (
        <Card className="p-5 mb-6 border-l-4 border-l-emerald-500">
            <div className="flex items-center justify-between gap-3 mb-4 flex-wrap">
                <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <ChatBubbleLeftRightIcon className="w-5 h-5" />
                    </div>
                    <div>
                        <h3 className="font-semibold text-gray-900">Réponses du client</h3>
                        <div className="text-xs text-gray-500 flex items-center gap-3 flex-wrap mt-0.5">
                            <span>
                                <strong className="text-emerald-700">{repondues}</strong> / {total} questions répondues ({pct} %)
                            </span>
                            {repondeur && (
                                <span className="flex items-center gap-1">
                                    <UserCircleIcon className="w-3.5 h-3.5" />
                                    {repondeur.prenom} {repondeur.nom}
                                </span>
                            )}
                            {remplilA && (
                                <span className="flex items-center gap-1">
                                    <ClockIcon className="w-3.5 h-3.5" />
                                    {new Date(remplilA).toLocaleString('fr-FR')}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
                <Badge variant={questionnaire?.statut === 'valide' ? 'success' : questionnaire?.statut === 'rempli' ? 'warning' : 'info'} dot>
                    {questionnaire?.statut === 'valide' ? 'Validé' : questionnaire?.statut === 'rempli' ? 'Rempli' : 'En cours'}
                </Badge>
            </div>

            {/* Barre de progression */}
            <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-4">
                <div className="h-full bg-gradient-to-r from-emerald-500 to-teal-600 transition-all" style={{ width: `${pct}%` }} />
            </div>

            <ul className="space-y-3">
                {questions.map(qu => {
                    const r = repIndex.get(Number(qu.numero));
                    const txt = String(r?.reponse ?? '').trim();
                    const aRepondu = txt !== '';
                    return (
                        <li key={qu.numero} className="border border-gray-200 rounded-lg p-3 bg-white">
                            <div className="flex items-start gap-3">
                                <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${aRepondu ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400'}`}>
                                    {qu.numero}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900 mb-1.5">{qu.texte}</p>
                                    {aRepondu ? (
                                        estQuestionFinalites(qu.texte) ? (
                                            <div className="bg-emerald-50 border-l-3 border-emerald-400 rounded px-3 py-2">
                                                <FinalitesEditor valeur={txt} onChange={() => {}} readOnly />
                                            </div>
                                        ) : (
                                            <div className="text-sm text-gray-800 bg-emerald-50 border-l-3 border-emerald-400 rounded px-3 py-2 whitespace-pre-wrap break-words">
                                                {txt}
                                            </div>
                                        )
                                    ) : (
                                        <p className="text-xs text-gray-400 italic">(Non répondu)</p>
                                    )}
                                </div>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}
