/**
 * QuestionnaireRemplir — Page de saisie des reponses a un questionnaire genere.
 *
 * Deux modes de rendu selon le type :
 *  - Audit Flash (themes contiennent `audit_flash` ou `audit_flash_libre`) :
 *    wizard step-by-step avec barre de progression, une question a la fois,
 *    options radio (Oui / Non / Je ne sais pas), bouton Precedent. A la
 *    derniere etape, on finalise et on redirige vers la page de resultats
 *    (qui affiche zone + recommandations).
 *  - Autres questionnaires : liste verticale classique (saisie libre).
 */

import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { getCsrfCookie } from '@/api/client';
import { exporterQuestionnairePdf } from '@/api/questionnaires';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    ArrowLeftIcon, CheckIcon, ChartBarIcon, BoltIcon, ArrowDownTrayIcon,
    PlusIcon, TrashIcon,
} from '@heroicons/react/24/outline';

/**
 * Detecte si une question porte sur les finalites de la collecte (formulation
 * standard ARTCI generee par l'IA). Le matching est tolerant aux variantes
 * d'accentuation et de casse.
 */
export function estQuestionFinalites(texte) {
    if (!texte) return false;
    const t = texte
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');
    return t.includes('finalit') && (t.includes('collecte') || t.includes('traitement') || t.includes('donnee'));
}

// Options du wizard Audit Flash, alignees sur le scoring backend :
// `oui` = 0 pt (mesure operationnelle), `non` = +10 pts (mesure absente),
// `je ne sais pas` = +10 pts (a verifier en interne).
const AUDIT_FLASH_OPTIONS = [
    { value: 'oui', titre: 'Oui', sous_titre: 'Cette mesure est pleinement opérationnelle.' },
    { value: 'non', titre: 'Non', sous_titre: "Cette mesure n'est pas en place." },
    { value: 'je ne sais pas', titre: 'Je ne sais pas', sous_titre: 'Je dois vérifier ce point.' },
];

export default function QuestionnaireRemplir() {
    const { qid } = useParams();
    const navigate = useNavigate();
    const [q, setQ] = useState(null);
    const [reponses, setReponses] = useState({});
    const [saving, setSaving] = useState(false);
    const [exportingPdf, setExportingPdf] = useState(false);

    const exporterPdf = async () => {
        if (!q) return;
        setExportingPdf(true);
        try {
            const slug = (q.titre || `questionnaire_${qid}`).replace(/[^A-Za-z0-9_-]+/g, '_');
            await exporterQuestionnairePdf(qid, `${slug}_q${qid}.pdf`);
        } catch (e) {
            alertError(e.response?.data?.message || 'Export PDF impossible');
        } finally {
            setExportingPdf(false);
        }
    };

    useEffect(() => {
        api.get(`/questionnaires-generes/${qid}`).then(r => {
            setQ(r.data.questionnaire);
            const map = {};
            (r.data.questionnaire.reponses || []).forEach(rep => { map[rep.numero] = rep.reponse; });
            setReponses(map);
        }).catch(() => alertError('Impossible de charger le questionnaire'));
    }, [qid]);

    const themes = q?.themes || [];
    const isAuditFlash = themes.includes('audit_flash') || themes.includes('audit_flash_libre');
    const isAuditFlashLibre = themes.includes('audit_flash_libre');
    // Fallback si on a atterri ici via URL directe (pas d'historique) : on
    // privilegie le contexte du questionnaire (mission audit-flash-libre vs
    // mission normale vs vue client). Sinon, navigate(-1) revient sur la page
    // d'ou l'utilisateur vient (gestion ASC, mes formulaires, ou liste mission).
    const retourUrlFallback = isAuditFlashLibre
        ? '/audit-flash'
        : q?.mission_id ? `/missions/${q.mission_id}/questionnaires` : '/mes-formulaires';
    const retour = () => {
        // window.history.length > 1 signifie qu'on a un historique (pas une URL directe)
        if (window.history.length > 1) navigate(-1);
        else navigate(retourUrlFallback);
    };

    const enregistrer = async ({ finalise = false, silencieux = false } = {}) => {
        if (!q) return;
        if (finalise && !silencieux && !(await confirmAction(
            'Finaliser ce questionnaire ? Vous pourrez encore le consulter mais il sera marqué comme rempli.',
            'Finalisation',
        ))) return;
        setSaving(true);
        try {
            await getCsrfCookie();
            const payload = {
                reponses: (q.questions || []).map(qu => ({ numero: qu.numero, reponse: reponses[qu.numero] || '' })),
                finalise,
            };
            await api.put(`/questionnaires-generes/${qid}/reponses`, payload);
            if (!silencieux) alertSuccess(finalise ? 'Questionnaire finalisé' : 'Réponses enregistrées');
            if (finalise) {
                if (isAuditFlash) {
                    navigate(`/questionnaires-generes/${qid}/audit-flash-resultat`);
                } else {
                    retour();
                }
            }
        } catch (e) {
            alertError(e.response?.data?.message || 'Erreur');
        } finally {
            setSaving(false);
        }
    };

    if (!q) return <div className="p-8 text-sm text-gray-500">Chargement...</div>;

    // ------------------------------------------------------------------
    // WIZARD AUDIT FLASH
    // ------------------------------------------------------------------
    if (isAuditFlash) {
        return (
            <WizardAuditFlash
                q={q}
                reponses={reponses}
                setReponses={setReponses}
                saving={saving}
                onEnregistrer={enregistrer}
                onRetour={retour}
            />
        );
    }

    // ------------------------------------------------------------------
    // MODE CLASSIQUE (liste de cartes)
    // ------------------------------------------------------------------
    const repondues = Object.values(reponses).filter(r => r && r.trim()).length;

    return (
        <div className="p-6 lg:p-8 max-w-4xl mx-auto">
            <Button variant="ghost" onClick={retour} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour
            </Button>
            <PageHeader title={q.titre} subtitle={`${q.pole}${q.service ? ' - ' + q.service : ''}`}>
                <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant={q.source === 'ia' ? 'purple' : 'gray'}>{q.source === 'ia' ? 'Généré par IA' : 'Générique'}</Badge>
                    <Badge variant="info">{repondues}/{(q.questions || []).length}</Badge>
                    <Button variant="secondary" size="sm" onClick={exporterPdf} disabled={exportingPdf}>
                        <ArrowDownTrayIcon className="w-4 h-4" /> {exportingPdf ? 'Export...' : 'Exporter PDF'}
                    </Button>
                </div>
            </PageHeader>

            {q.description && (
                <Card className="p-4 mb-4 bg-blue-50 border-blue-200">
                    <p className="text-sm text-gray-700">{q.description}</p>
                </Card>
            )}

            <div className="space-y-3">
                {(q.questions || []).map(qu => (
                    <Card key={qu.numero} className="p-5">
                        <div className="flex items-start gap-3">
                            <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                {qu.numero}
                            </div>
                            <div className="flex-1">
                                {qu.domaine && (
                                    <p className="text-xs uppercase tracking-wide font-semibold text-rose-700 mb-1">Domaine : {qu.domaine}</p>
                                )}
                                <p className="font-medium text-gray-900 mb-2">{qu.texte}</p>
                                {(qu.themes || []).length > 0 && (
                                    <div className="flex flex-wrap gap-1 mb-2">
                                        {qu.themes.map(t => <span key={t} className="text-xs px-2 py-0.5 bg-purple-50 text-purple-700 rounded">{t}</span>)}
                                    </div>
                                )}
                                {qu.enjeu && (
                                    <div className="mb-2 p-2 rounded bg-amber-50 border border-amber-200 text-xs text-amber-900">
                                        <span className="font-semibold">Enjeu :</span> {qu.enjeu}
                                        {qu.source_legale && <span className="block text-amber-700 mt-0.5">{qu.source_legale}</span>}
                                    </div>
                                )}
                                {qu.type === 'oui_non' ? (
                                    <select value={reponses[qu.numero] || ''} onChange={e => setReponses({ ...reponses, [qu.numero]: e.target.value })} className="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="">--</option>
                                        <option value="oui">Oui</option>
                                        <option value="non">Non</option>
                                    </select>
                                ) : qu.type === 'liste' && (qu.options || []).length > 0 ? (
                                    <select value={reponses[qu.numero] || ''} onChange={e => setReponses({ ...reponses, [qu.numero]: e.target.value })} className="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="">--</option>
                                        {qu.options.map(o => <option key={o} value={o}>{o}</option>)}
                                    </select>
                                ) : estQuestionFinalites(qu.texte) ? (
                                    <FinalitesEditor
                                        valeur={reponses[qu.numero] || ''}
                                        onChange={(v) => setReponses({ ...reponses, [qu.numero]: v })}
                                    />
                                ) : (
                                    <textarea value={reponses[qu.numero] || ''} onChange={e => setReponses({ ...reponses, [qu.numero]: e.target.value })} rows={3} className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Votre réponse..." />
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>

            <div className="flex justify-end gap-3 mt-6 sticky bottom-4">
                <Button variant="secondary" onClick={() => enregistrer({ finalise: false })} disabled={saving}>
                    {saving ? 'Enregistrement...' : 'Enregistrer brouillon'}
                </Button>
                <Button variant="success" onClick={() => enregistrer({ finalise: true })} disabled={saving}>
                    <CheckIcon className="w-4 h-4" /> Finaliser le questionnaire
                </Button>
            </div>
        </div>
    );
}

/**
 * Editeur multi-finalites : une ligne par finalite, separation par "\n"
 * dans la valeur stockee. Etat local pour preserver les lignes vides en
 * cours d'edition (sinon le bouton "+" n'affichait rien car le parent
 * filtrait l'empty avant le re-render).
 */
export function FinalitesEditor({ valeur, onChange, readOnly = false }) {
    // Parse initial depuis la valeur parente (split par \n, sans trim pour
    // preserver l'edition d'une ligne vide).
    const [lignes, setLignes] = useState(() => {
        const arr = String(valeur || '').split('\n');
        return arr.length === 0 ? [''] : arr;
    });

    // Re-sync si la valeur parente change de l'exterieur (chargement initial,
    // reset...). On compare le contenu joint pour eviter une boucle infinie
    // quand c'est nous qui venons de propager.
    useEffect(() => {
        const courantJoint = lignes.join('\n');
        const exterieurJoint = String(valeur || '');
        if (courantJoint !== exterieurJoint && exterieurJoint !== courantJoint.replace(/\n+$/, '')) {
            const arr = exterieurJoint.split('\n');
            setLignes(arr.length === 0 ? [''] : arr);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [valeur]);

    const propager = (nouv) => {
        setLignes(nouv);
        // On enleve les lignes vides en queue uniquement, pour ne pas perdre
        // une ligne vide intermediaire en cours de saisie. Au final, le parent
        // recoit "Ligne 1\nLigne 2" sans \n traillant.
        const propre = nouv.slice();
        while (propre.length > 0 && propre[propre.length - 1].trim() === '') propre.pop();
        onChange(propre.join('\n'));
    };

    const modifier = (i, v) => {
        const next = lignes.slice();
        next[i] = v;
        propager(next);
    };
    const ajouter = () => propager([...lignes, '']);
    const supprimer = (i) => {
        const next = lignes.filter((_, idx) => idx !== i);
        propager(next.length === 0 ? [''] : next);
    };

    if (readOnly) {
        const nettes = lignes.filter(s => s.trim() !== '');
        if (nettes.length === 0) return <p className="text-sm text-gray-400 italic">(Aucune finalité saisie)</p>;
        return (
            <ol className="list-decimal pl-5 space-y-1 text-sm text-gray-800">
                {nettes.map((l, i) => <li key={i} className="leading-relaxed">{l}</li>)}
            </ol>
        );
    }

    return (
        <div className="space-y-2">
            {lignes.map((ligne, i) => (
                <div key={i} className="flex items-start gap-2">
                    <span className="text-xs font-bold text-blue-700 mt-2.5 w-6 text-right">{i + 1}.</span>
                    <textarea
                        value={ligne}
                        onChange={e => modifier(i, e.target.value)}
                        rows={2}
                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        placeholder={`Finalité ${i + 1} (ex: Gestion de la paie)`}
                    />
                    {lignes.length > 1 && (
                        <button
                            type="button"
                            onClick={() => supprimer(i)}
                            className="text-red-600 hover:bg-red-50 p-2 rounded mt-1.5"
                            title="Supprimer cette finalité"
                        >
                            <TrashIcon className="w-4 h-4" />
                        </button>
                    )}
                </div>
            ))}
            <button
                type="button"
                onClick={ajouter}
                className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 hover:bg-blue-50 px-3 py-1.5 rounded-lg"
            >
                <PlusIcon className="w-4 h-4" /> Ajouter une finalité
            </button>
            <p className="text-xs text-gray-500 italic">
                Chaque finalité saisie ici pourra générer automatiquement un traitement séparé dans le registre.
            </p>
        </div>
    );
}

// ----------------------------------------------------------------------
// WIZARD AUDIT FLASH : 1 question par etape, options radio Oui/Non/JNSP
// ----------------------------------------------------------------------
function WizardAuditFlash({ q, reponses, setReponses, saving, onEnregistrer, onRetour }) {
    const questions = useMemo(() => q.questions || [], [q]);
    const total = questions.length;
    const [etape, setEtape] = useState(() => {
        // Reprise : on positionne sur la premiere question non repondue
        const initial = q.reponses || [];
        const map = {};
        initial.forEach(r => { map[r.numero] = r.reponse; });
        const idx = questions.findIndex(qu => !map[qu.numero] || !String(map[qu.numero]).trim());
        return idx === -1 ? 0 : idx;
    });

    const questionCourante = questions[etape];
    const valeurCourante = questionCourante ? (reponses[questionCourante.numero] || '') : '';
    const repondues = useMemo(
        () => Object.values(reponses).filter(r => r && String(r).trim()).length,
        [reponses],
    );
    const pourcentage = total > 0 ? Math.round(((etape + 1) / total) * 100) : 0;
    const estDerniere = etape >= total - 1;

    const choisir = async (valeur) => {
        if (!questionCourante) return;
        const next = { ...reponses, [questionCourante.numero]: valeur };
        setReponses(next);
        // Auto-avance apres choix : si pas derniere, on passe a la suivante
        // apres une petite tempo pour le feedback visuel ; sinon on attend que
        // l'utilisateur clique "Voir le resultat".
        if (!estDerniere) {
            setTimeout(() => setEtape(e => Math.min(total - 1, e + 1)), 250);
        }
    };

    const precedent = () => setEtape(e => Math.max(0, e - 1));

    const voirResultat = async () => {
        // Sauvegarde + finalise + redirige vers l'ecran de scoring
        await onEnregistrer({ finalise: true, silencieux: true });
    };

    if (!questionCourante) {
        return <div className="p-8 text-sm text-gray-500">Questionnaire vide.</div>;
    }

    return (
        <div className="min-h-screen bg-slate-50/40">
            <div className="p-6 lg:p-8 max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-4">
                    <Button variant="ghost" onClick={onRetour}>
                        <ArrowLeftIcon className="w-4 h-4" /> Retour
                    </Button>
                    <Badge variant="warning" size="md" dot>
                        <BoltIcon className="w-3.5 h-3.5 inline mr-1" />
                        Audit Flash
                    </Badge>
                </div>

                <h1 className="text-2xl font-bold text-slate-900 mb-1">{q.titre}</h1>
                {q.description && (
                    <p className="text-sm text-slate-600 mb-6 leading-relaxed">{q.description}</p>
                )}

                {/* Barre de progression */}
                <div className="mb-8">
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-xs uppercase tracking-wider font-bold text-slate-800">
                            Étape <span className="text-amber-600">{etape + 1}</span> <span className="text-slate-400 font-normal">/ {total}</span>
                        </p>
                        <p className="text-xs font-bold text-amber-600">{pourcentage}%</p>
                    </div>
                    <div className="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div
                            className="h-full bg-linear-to-r from-amber-400 to-amber-600 transition-all duration-500"
                            style={{ width: `${pourcentage}%` }}
                        />
                    </div>
                </div>

                {/* Carte de question */}
                <Card className="p-6 lg:p-8 relative overflow-hidden ring-1 ring-slate-200/70 shadow-md">
                    <div className="absolute -top-12 -right-12 w-40 h-40 bg-amber-100/40 rounded-full pointer-events-none" />
                    <div className="relative">
                        <p className="text-xs uppercase tracking-wider font-bold text-amber-600 mb-3 flex items-center gap-1.5">
                            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                            Évaluation en cours
                        </p>
                        <h2 className="text-xl lg:text-2xl font-bold text-slate-900 leading-snug mb-2">
                            {questionCourante.texte}
                        </h2>
                        {questionCourante.domaine && (
                            <p className="text-xs uppercase tracking-wide font-semibold text-rose-700 mb-1">
                                Domaine : {questionCourante.domaine}
                            </p>
                        )}
                        {questionCourante.enjeu && (
                            <div className="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900">
                                <span className="font-semibold">Enjeu :</span> {questionCourante.enjeu}
                                {questionCourante.source_legale && (
                                    <span className="block text-amber-700 mt-0.5">{questionCourante.source_legale}</span>
                                )}
                            </div>
                        )}

                        {/* Options */}
                        <div className="space-y-3 mt-6">
                            {AUDIT_FLASH_OPTIONS.map(opt => {
                                const actif = valeurCourante === opt.value;
                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => choisir(opt.value)}
                                        disabled={saving}
                                        className={`w-full text-left p-4 rounded-xl ring-1 transition-all flex items-start justify-between gap-4 hover:shadow-sm ${
                                            actif
                                                ? 'bg-amber-50 ring-amber-400 shadow-sm'
                                                : 'bg-white ring-slate-200 hover:ring-slate-300'
                                        }`}
                                    >
                                        <div className="flex-1 min-w-0">
                                            <p className={`font-bold text-base ${actif ? 'text-amber-900' : 'text-slate-900'}`}>
                                                {opt.titre}
                                            </p>
                                            <p className="text-sm text-slate-500 mt-0.5">{opt.sous_titre}</p>
                                        </div>
                                        <div className={`shrink-0 mt-0.5 w-6 h-6 rounded-full ring-2 flex items-center justify-center transition-all ${
                                            actif
                                                ? 'bg-amber-500 ring-amber-500'
                                                : 'bg-white ring-slate-300'
                                        }`}>
                                            {actif && <span className="block w-2.5 h-2.5 rounded-full bg-white" />}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Navigation bas de carte : seul « Precedent » est manuel ;
                            l'avance se fait automatiquement apres chaque choix.
                            Sur la derniere question, on remplace l'auto-avance par
                            un bouton « Voir le resultat » pour eviter une finalisation
                            involontaire. */}
                        <div className="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={precedent}
                                disabled={etape === 0 || saving}
                                className="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider font-bold text-slate-700 hover:text-slate-900 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <ArrowLeftIcon className="w-4 h-4" />
                                Précédent
                            </button>

                            {estDerniere && (
                                <Button
                                    variant="success"
                                    onClick={voirResultat}
                                    disabled={saving || !valeurCourante}
                                    title={!valeurCourante ? 'Répondez à la dernière question pour voir le résultat' : ''}
                                >
                                    <ChartBarIcon className="w-4 h-4" />
                                    {saving ? 'Finalisation...' : 'Voir le résultat'}
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Pied : compteur + sauvegarde brouillon */}
                <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                    <span>{repondues} / {total} questions répondues</span>
                    <button
                        type="button"
                        onClick={() => onEnregistrer({ finalise: false })}
                        disabled={saving}
                        className="text-slate-600 hover:text-slate-900 underline"
                    >
                        Enregistrer comme brouillon
                    </button>
                </div>
            </div>
        </div>
    );
}
